<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\ComplaintPdfImport;
use App\Models\ComplaintPdfImportPage;
use App\Services\ClientError;
use App\Services\Ocr\OcrPipeline;
use App\Services\Ocr\OcrFieldRules;
use App\Services\Ocr\OcrRecordWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Circle-scoped OCR import API. Every record endpoint re-checks visibility
 * server-side; the circle is fixed at upload and never taken from OCR output.
 */
class OcrImportController extends Controller
{
    public function __construct(private OcrPipeline $pipeline)
    {
    }

    /** Circles the current user may upload into. */
    public function circles(Request $request)
    {
        $user = $request->user();
        $query = Circle::query()->orderBy('name');
        if (!$user->seesAllData()) {
            $zoneId = $user->isZonalHead() ? ($user->zone_id ?: $user->circle?->zone_id) : null;
            $zoneId ? $query->where('zone_id', $zoneId) : $query->whereKey($user->circle_id ?: 0);
        }

        return response()->json($query->get(['id', 'name', 'code']));
    }

    public function store(Request $request)
    {
        $maxKb = (int) config('ocr.max_file_mb') * 1024;
        $data = $request->validate([
            'circle_id' => 'required|integer|exists:circles,id',
            'files' => 'required|array|min:1|max:50',
            'files.*' => "required|file|mimes:pdf|mimetypes:application/pdf|max:{$maxKb}",
            'batch_id' => 'nullable|string|max:64|regex:/\A[A-Za-z0-9_-]+\z/',
        ]);

        $user = $request->user();
        abort_unless($user->canAccessCircle((int) $data['circle_id']), 403, 'You are not authorized to upload into this circle.');
        $circle = Circle::findOrFail($data['circle_id']);

        $results = [];
        foreach ($request->file('files') as $file) {
            try {
                ['import' => $import, 'duplicate' => $duplicate] = $this->pipeline->register($file, $user, $circle, $data['batch_id'] ?? null);
                $results[] = ['id' => $import->id, 'file' => $import->original_filename, 'status' => $import->status, 'duplicate' => $duplicate];
            } catch (AuthorizationException $e) {
                abort(403, $e->getMessage());
            } catch (\Throwable $e) {
                $results[] = ['id' => null, 'file' => $file->getClientOriginalName(), 'status' => 'failed', 'error' => ClientError::message($e)];
            }
        }

        return response()->json(['imports' => $results], 202);
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|string|in:queued,processing,needs_review,imported,failed',
            'circle_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = ComplaintPdfImport::visibleTo($request->user())
            ->whereNotNull('circle_id')
            ->with('circle:id,name,code')
            ->latest('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($circleId = $request->query('circle_id')) {
            $query->where('circle_id', (int) $circleId);
        }
        if ($search = $request->query('search')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where(fn ($q) => $q->where('original_filename', 'like', $like)->orWhere('inquiry_ref', 'like', $like));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 25), [
            'id', 'circle_id', 'original_filename', 'status', 'page_count', 'pages_done', 'mean_confidence',
            'inquiry_ref', 'complaint_id', 'error_message', 'created_at', 'processed_at',
        ]));
    }

    public function stats(Request $request)
    {
        $rows = ComplaintPdfImport::visibleTo($request->user())->whereNotNull('circle_id')
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json($rows);
    }

    public function show(Request $request, ComplaintPdfImport $import, OcrRecordWriter $writer)
    {
        $this->ensureVisible($request, $import);
        $import->load(['circle:id,name,code', 'user:id,name', 'reviewer:id,name']);
        $pages = ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->orderBy('page_no')
            ->get(['page_no', 'method', 'confidence', 'rotation', 'engine', 'error']);
        $values = collect($import->field_results ?? [])->map(fn ($f) => $f['value'] ?? null)->filter()->all();

        return response()->json([
            'import' => $import->only([
                'id', 'circle_id', 'original_filename', 'file_hash', 'status', 'page_count', 'pages_done', 'layout',
                'field_results', 'review_reasons', 'circle_hint', 'mean_confidence', 'error_message', 'complaint_id',
                'verification_report_id', 'import_result', 'created_at', 'processed_at', 'reviewed_at',
            ]) + [
                'circle' => $import->circle,
                'uploaded_by' => $import->user?->name,
                'reviewed_by' => $import->reviewer?->name,
                'warnings' => $import->extracted_data['warnings'] ?? [],
                'failed_pages' => $import->extracted_data['failed_pages'] ?? [],
            ],
            'pages' => $pages,
            'possible_duplicates' => $import->status === ComplaintPdfImport::STATUS_IMPORTED ? [] : $writer->possibleDuplicates($import, $values),
            'editable_fields' => array_keys(OcrFieldRules::TYPES),
        ]);
    }

    public function page(Request $request, ComplaintPdfImport $import, int $pageNo)
    {
        $this->ensureVisible($request, $import);
        $page = ComplaintPdfImportPage::where('complaint_pdf_import_id', $import->id)->where('page_no', $pageNo)->firstOrFail();

        return response()->json(['page_no' => $page->page_no, 'method' => $page->method, 'confidence' => $page->confidence, 'text' => $page->text]);
    }

    public function file(Request $request, ComplaintPdfImport $import)
    {
        $this->ensureVisible($request, $import);
        $disk = Storage::disk(config('ocr.disk'));
        abort_unless($disk->exists($import->stored_path), 404);

        return $disk->response($import->stored_path, 'import-' . $import->id . '.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function review(Request $request, ComplaintPdfImport $import)
    {
        $this->ensureVisible($request, $import);
        $data = $request->validate([
            'fields' => 'nullable|array',
            'fields.*' => 'nullable|string|max:10000',
            'approve' => 'nullable|boolean',
            'link_complaint_id' => 'nullable|integer',
        ]);

        try {
            $import = $this->pipeline->review($import, $request->user(), $data['fields'] ?? [], (bool) ($data['approve'] ?? false),
                isset($data['link_complaint_id']) ? (int) $data['link_complaint_id'] : null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Linked complaint not found in this circle.'], 422);
        }

        return response()->json(['message' => 'Saved', 'status' => $import->status, 'complaint_id' => $import->complaint_id]);
    }

    public function retry(Request $request, ComplaintPdfImport $import)
    {
        $this->ensureVisible($request, $import);
        try {
            $import = $this->pipeline->retry($import);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Queued for reprocessing', 'status' => $import->status]);
    }

    private function ensureVisible(Request $request, ComplaintPdfImport $import): void
    {
        abort_unless(
            $import->circle_id
            && ComplaintPdfImport::visibleTo($request->user())->whereKey($import->id)->exists()
            && $request->user()->canAccessCircle($import->circle_id),
            404
        );
    }
}
