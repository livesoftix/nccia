<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessComplaintPdfImport;
use App\Models\ComplaintPdfImport;
use App\Services\ComplaintPdfImportService;
use Illuminate\Http\Request;

class ComplaintPdfImportController extends Controller
{
    public function index(Request $request)
    {
        $query = ComplaintPdfImport::visibleTo($request->user())->with(['complaint', 'verificationReport', 'user'])
            ->latest('id');

        if ($batch = $request->query('batch_id')) {
            $query->where('batch_id', $batch);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(min(50, max(10, (int) $request->query('per_page', 15)))));
    }

    public function stats(Request $request)
    {
        $rows = ComplaintPdfImport::visibleTo($request->user())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total'      => $rows->sum(),
            'pending'    => (int) ($rows[ComplaintPdfImport::STATUS_PENDING] ?? 0),
            'processing' => (int) ($rows[ComplaintPdfImport::STATUS_PROCESSING] ?? 0),
            'extracted'  => (int) ($rows[ComplaintPdfImport::STATUS_EXTRACTED] ?? 0),
            'imported'   => (int) ($rows[ComplaintPdfImport::STATUS_IMPORTED] ?? 0),
            'failed'     => (int) ($rows[ComplaintPdfImport::STATUS_FAILED] ?? 0),
        ]);
    }

    public function capabilities()
    {
        return response()->json([
            'imagick'   => extension_loaded('imagick'),
            'ghostscript' => $this->findBinary(['gs', 'ghostscript']) !== null,
            'pdftoppm'  => $this->findBinary(['pdftoppm']) !== null,
            'mutool'    => $this->findBinary(['mutool']) !== null,
        ]);
    }

    public function store(Request $request, ComplaintPdfImportService $service)
    {
        @set_time_limit(300);

        $request->validate([
            'files'      => 'required|array|min:1|max:100',
            'files.*'    => 'file|mimes:pdf|max:51200',
            'batch_id'   => 'nullable|string|max:64|regex:/\A[A-Za-z0-9_-]+\z/',
            'auto_apply' => 'nullable|boolean',
            'sync'       => 'nullable|boolean',
            'ocr_texts'  => 'nullable|array',
            'ocr_texts.*'=> 'nullable|string|max:500000',
        ]);

        $result = $service->storeUploads($request->file('files'), $request->user(), $request->input('batch_id'));
        $ocrTexts = $request->input('ocr_texts', []);
        $autoApply = $request->boolean('auto_apply', false);
        $runServerExtract = $request->boolean('sync', false) && $this->shouldProcessSync();

        $processed = [];
        foreach ($result['imports'] as $import) {
            $ocrText = $ocrTexts[$import->original_filename] ?? null;

            if ($ocrText && trim($ocrText) !== '') {
                $import = $service->process($import, $ocrText);
                if ($autoApply && $import->status === ComplaintPdfImport::STATUS_EXTRACTED) {
                    $import = $service->applyToSystem($import, $request->user(), true);
                }
                $processed[] = $import->load(['complaint', 'verificationReport']);
            } elseif ($runServerExtract) {
                $processed[] = $this->runImportJob($import->id, $request->user()->id, $autoApply, true);
            } else {
                ProcessComplaintPdfImport::dispatch($import->id, $request->user()->id, $autoApply);
                $processed[] = $import->load(['complaint', 'verificationReport']);
            }
        }

        $allDone = collect($processed)->every(fn ($i) => in_array($i->status, [
            ComplaintPdfImport::STATUS_IMPORTED,
            ComplaintPdfImport::STATUS_EXTRACTED,
            ComplaintPdfImport::STATUS_FAILED,
        ], true));

        return response()->json([
            'message'  => $allDone
                ? count($processed) . ' PDF(s) processed.'
                : count($processed) . ' PDF(s) uploaded — call /process with browser OCR text.',
            'batch_id' => $result['batch_id'],
            'imports'  => $processed,
        ], 200);
    }

    public function process(Request $request, ComplaintPdfImport $complaintPdfImport, ComplaintPdfImportService $service)
    {
        @set_time_limit(300);

        $request->validate([
            'auto_apply' => 'nullable|boolean',
            'ocr_text'   => 'required|string|min:20|max:500000',
        ]);

        $this->ensureVisible($complaintPdfImport);
        $import = $service->process($complaintPdfImport, $request->input('ocr_text'));

        if ($request->boolean('auto_apply', true) && $import->status === ComplaintPdfImport::STATUS_EXTRACTED) {
            $import = $service->applyToSystem($import, $request->user(), true);
        }

        $ok = in_array($import->status, [
            ComplaintPdfImport::STATUS_IMPORTED,
            ComplaintPdfImport::STATUS_EXTRACTED,
        ], true);

        return response()->json([
            'message' => $ok
                ? ($import->status === ComplaintPdfImport::STATUS_IMPORTED ? 'Imported successfully' : 'Extracted — review and apply')
                : ($import->error_message ?? 'Processing failed'),
            'import'  => $import->load(['complaint', 'verificationReport']),
        ], $ok ? 200 : 422);
    }

    private function shouldProcessSync(): bool
    {
        if (env('PDF_IMPORT_SYNC') !== null) {
            return filter_var(env('PDF_IMPORT_SYNC'), FILTER_VALIDATE_BOOL);
        }

        return false;
    }

    private function runImportJob(int $importId, int $userId, bool $autoApply, bool $sync): ComplaintPdfImport
    {
        if ($sync) {
            $job = new ProcessComplaintPdfImport($importId, $userId, $autoApply);
            app()->call([$job, 'handle']);

            return ComplaintPdfImport::with(['complaint', 'verificationReport'])->findOrFail($importId);
        }

        ProcessComplaintPdfImport::dispatch($importId, $userId, $autoApply);

        return ComplaintPdfImport::findOrFail($importId);
    }

    /** Data isolation: an import is reachable only within the user's circle scope. */
    private function ensureVisible(ComplaintPdfImport $import): void
    {
        abort_unless(
            ComplaintPdfImport::visibleTo(request()->user())->whereKey($import->id)->exists(),
            404
        );
    }

    public function show(ComplaintPdfImport $complaintPdfImport)
    {
        $this->ensureVisible($complaintPdfImport);

        return response()->json($complaintPdfImport->load(['complaint', 'verificationReport', 'user']));
    }

    public function extract(Request $request, ComplaintPdfImport $complaintPdfImport, ComplaintPdfImportService $service)
    {
        $this->ensureVisible($complaintPdfImport);
        $complaintPdfImport = $service->process($complaintPdfImport);

        return response()->json([
            'message' => $complaintPdfImport->status === ComplaintPdfImport::STATUS_FAILED ? 'Extraction failed' : 'PDF extracted',
            'import'  => $complaintPdfImport,
        ], $complaintPdfImport->status === ComplaintPdfImport::STATUS_FAILED ? 422 : 200);
    }

    public function apply(Request $request, ComplaintPdfImport $complaintPdfImport, ComplaintPdfImportService $service)
    {
        $request->validate([
            'create_if_missing' => 'nullable|boolean',
        ]);

        $this->ensureVisible($complaintPdfImport);
        $complaintPdfImport = $service->applyToSystem(
            $complaintPdfImport,
            $request->user(),
            $request->boolean('create_if_missing', true)
        );

        $ok = $complaintPdfImport->status === ComplaintPdfImport::STATUS_IMPORTED;

        return response()->json([
            'message' => $ok ? 'Imported into system successfully' : ($complaintPdfImport->error_message ?? 'Import failed'),
            'import'  => $complaintPdfImport->load(['complaint', 'verificationReport']),
        ], $ok ? 200 : 422);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file'      => 'nullable|file|mimes:pdf|max:51200',
            'ocr_text'  => 'nullable|string|max:500000',
            'filename'  => 'nullable|string|max:255',
        ]);

        $extractor = app(\App\Services\ComplaintPdfExtractor::class);

        if ($request->has('ocr_text')) {
            $ocrText = trim((string) $request->input('ocr_text', ''));
            $filename = $request->input('filename', 'preview.pdf');

            if ($ocrText === '') {
                return response()->json([
                    'extracted'  => [],
                    'fields_ok'  => false,
                    'used_ocr'   => true,
                    'error'      => 'OCR returned empty text — try Re-run OCR',
                ]);
            }

            $data = $extractor->parseVerificationReportText($ocrText, $filename);
            $data['used_ocr'] = true;
            $fieldsOk = $extractor->hasMinimumFields($data);

            return response()->json([
                'extracted'  => $data,
                'fields_ok'  => $fieldsOk,
                'used_ocr'   => true,
                'error'      => $fieldsOk ? null : 'CNIC/name not detected — try Re-run OCR or check PDF scan quality',
            ]);
        }

        $request->validate(['file' => 'required|file|mimes:pdf|max:51200']);

        $file = $request->file('file');
        $tmp = $file->store('imports/tmp', 'public');
        $absolute = storage_path('app/public/' . $tmp);

        $extract = $extractor->extract($absolute, $file->getClientOriginalName());

        @unlink($absolute);

        return response()->json([
            'extracted' => $extract['data'],
            'used_ocr'  => $extract['used_ocr'],
            'error'     => $extract['error'],
        ], $extract['ok'] ? 200 : 422);
    }

    public function renderPage(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:51200',
            'page' => 'nullable|integer|min:0|max:9',
        ]);

        $page = (int) $request->input('page', 0);
        $tmp = $request->file('file')->store('imports/tmp', 'public');
        $absolute = storage_path('app/public/' . $tmp);

        try {
            $image = $this->renderPdfPageImage($absolute, $page);
            if (!$image) {
                return response()->json([
                    'error'        => 'Could not render PDF page on server. Enable Imagick PHP extension or install Ghostscript (gs).',
                    'capabilities' => [
                        'imagick'     => extension_loaded('imagick'),
                        'ghostscript' => $this->findBinary(['gs', 'ghostscript']) !== null,
                        'pdftoppm'    => $this->findBinary(['pdftoppm']) !== null,
                        'mutool'      => $this->findBinary(['mutool']) !== null,
                    ],
                ], 422);
            }

            return response()->json([
                'image' => $image,
                'page'  => $page,
            ]);
        } finally {
            @unlink($absolute);
        }
    }

    private function renderPdfPageImage(string $pdfPath, int $page): ?string
    {
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(220, 220);
                $im->readImage($pdfPath . '[' . $page . ']');
                $im->setImageBackgroundColor(new \ImagickPixel('white'));
                $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                if (method_exists($im, 'mergeImageLayers')) {
                    $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                }
                $im->setImageFormat('png');

                return 'data:image/png;base64,' . base64_encode($im->getImageBlob());
            } catch (\Throwable $e) {
                \Log::warning('Imagick PDF page render failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->renderWithPdftoppm($pdfPath, $page)
            ?? $this->renderWithGhostscript($pdfPath, $page)
            ?? $this->renderWithMutool($pdfPath, $page);
    }

    private function findBinary(array $names): ?string
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $canExec = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);

        if ($canExec) {
            foreach ($names as $name) {
                try {
                    $path = trim((string) @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
                    if ($path !== '' && @is_executable($path)) {
                        return $path;
                    }
                } catch (\Throwable $e) {
                    // Ignore and fallback
                }
            }
        }

        foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/usr/bin/pdftoppm', '/usr/bin/mutool'] as $path) {
            if (@file_exists($path) && @is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function renderWithPdftoppm(string $pdfPath, int $page): ?string
    {
        $pdftoppm = $this->findBinary(['pdftoppm']);
        if (!$pdftoppm) {
            return null;
        }

        $outDir = sys_get_temp_dir() . '/nccia-pdf-' . uniqid();
        @mkdir($outDir, 0755, true);
        $prefix = $outDir . '/page';
        $cmd = escapeshellcmd($pdftoppm) . ' -png -f ' . ($page + 1) . ' -l ' . ($page + 1)
            . ' -r 220 ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix);
        @exec($cmd, $output, $code);
        $png = $prefix . '-' . str_pad((string) ($page + 1), strlen((string) ($page + 1)), '0', STR_PAD_LEFT) . '.png';
        if (!is_file($png)) {
            $png = $prefix . '-1.png';
        }
        if ($code === 0 && is_file($png)) {
            $data = file_get_contents($png);
            @unlink($png);
            @rmdir($outDir);

            return $data ? 'data:image/png;base64,' . base64_encode($data) : null;
        }

        return null;
    }

    private function renderWithGhostscript(string $pdfPath, int $page): ?string
    {
        $gs = $this->findBinary(['gs', 'ghostscript']);
        if (!$gs) {
            return null;
        }

        $outFile = sys_get_temp_dir() . '/nccia-' . uniqid() . '.png';
        $pageNum = $page + 1;
        $cmd = escapeshellcmd($gs)
            . ' -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r220'
            . ' -dFirstPage=' . $pageNum . ' -dLastPage=' . $pageNum
            . ' -sOutputFile=' . escapeshellarg($outFile)
            . ' ' . escapeshellarg($pdfPath);
        @exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($outFile)) {
            return null;
        }

        $data = file_get_contents($outFile);
        @unlink($outFile);

        return $data ? 'data:image/png;base64,' . base64_encode($data) : null;
    }

    private function renderWithMutool(string $pdfPath, int $page): ?string
    {
        $mutool = $this->findBinary(['mutool']);
        if (!$mutool) {
            return null;
        }

        $outFile = sys_get_temp_dir() . '/nccia-' . uniqid() . '.png';
        $cmd = escapeshellcmd($mutool) . ' draw -o ' . escapeshellarg($outFile)
            . ' -r 220 ' . escapeshellarg($pdfPath) . ' ' . ($page + 1);
        @exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($outFile)) {
            return null;
        }

        $data = file_get_contents($outFile);
        @unlink($outFile);

        return $data ? 'data:image/png;base64,' . base64_encode($data) : null;
    }
}
