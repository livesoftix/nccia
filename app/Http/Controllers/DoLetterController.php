<?php

namespace App\Http\Controllers;

use App\Models\DoLetter;
use App\Models\User;
use App\Services\DoLetterBuilderService;
use App\Services\DoLetterExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DoLetterController extends Controller
{
    public function __construct(private DoLetterBuilderService $builder)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = DoLetter::visibleTo($user)
            ->with(['circle:id,name', 'compiler:id,name'])
            ->latest('report_month');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('month_from')) {
            $query->whereDate('report_month', '>=', Carbon::parse($from)->startOfMonth()->toDateString());
        }

        if ($to = $request->query('month_to')) {
            $query->whereDate('report_month', '<=', Carbon::parse($to)->startOfMonth()->toDateString());
        }

        if ($user->hasRole('director_general')) {
            $query->whereIn('status', [
                DoLetter::STATUS_FORWARDED_HQ,
                DoLetter::STATUS_HQ_ACK,
            ]);
        }

        return response()->json($query->paginate(min(50, max(10, (int) $request->query('per_page', 15)))));
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'circle_id' => 'required|exists:circles,id',
            'report_month' => 'required|date',
        ]);

        $this->authorizeCircle($request->user(), (int) $data['circle_id']);

        $built = $this->builder->build((int) $data['circle_id'], Carbon::parse($data['report_month']));

        return response()->json($built);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canCompile($user), 403);

        $data = $request->validate([
            'circle_id' => 'required|exists:circles,id',
            'report_month' => 'required|date',
            'auto_compile' => 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
            'payload' => 'nullable|array',
        ]);

        $this->authorizeCircle($user, (int) $data['circle_id']);

        $month = Carbon::parse($data['report_month'])->startOfMonth();
        $exists = DoLetter::query()
            ->where('circle_id', $data['circle_id'])
            ->whereDate('report_month', $month->toDateString())
            ->exists();
        abort_if($exists, 422, 'A D.O. Letter for this circle and month already exists.');

        $built = $request->boolean('auto_compile', true)
            ? $this->builder->build((int) $data['circle_id'], $month)
            : ['month_label' => $month->format('F-Y'), 'payload' => $data['payload'] ?? []];

        $letter = DoLetter::create([
            'circle_id' => $data['circle_id'],
            'report_month' => $month->toDateString(),
            'month_label' => $built['month_label'] ?? $month->format('F-Y'),
            'status' => DoLetter::STATUS_DRAFT,
            'payload' => $request->input('payload', $built['payload'] ?? []),
            'notes' => $data['notes'] ?? null,
            'compiled_by' => $user->id,
        ]);

        return response()->json(['message' => 'D.O. Letter draft created', 'letter' => $letter->load(['circle', 'compiler'])], 201);
    }

    public function show(DoLetter $doLetter, Request $request)
    {
        abort_unless(DoLetter::visibleTo($request->user())->whereKey($doLetter->id)->exists(), 403);

        return response()->json($doLetter->load(['circle', 'compiler', 'ciReviewer', 'forwarder', 'hqAcknowledger']));
    }

    public function update(Request $request, DoLetter $doLetter)
    {
        $user = $request->user();
        abort_unless(DoLetter::visibleTo($user)->whereKey($doLetter->id)->exists(), 403);
        abort_unless(in_array($doLetter->status, [DoLetter::STATUS_DRAFT, DoLetter::STATUS_SENT_BACK], true), 422);
        abort_unless($this->canCompile($user), 403);

        $data = $request->validate([
            'circle_id' => 'nullable|exists:circles,id',
            'report_month' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'payload' => 'nullable|array',
            'recompile' => 'nullable|boolean',
        ]);

        if (!empty($data['circle_id']) && (int) $data['circle_id'] !== (int) $doLetter->circle_id) {
            $this->authorizeCircle($user, (int) $data['circle_id']);
            $doLetter->circle_id = (int) $data['circle_id'];
        }

        $newMonth = !empty($data['report_month'])
            ? Carbon::parse($data['report_month'])->startOfMonth()
            : null;

        if ($newMonth) {
            $exists = DoLetter::query()
                ->where('circle_id', $doLetter->circle_id)
                ->whereDate('report_month', $newMonth->toDateString())
                ->whereKeyNot($doLetter->id)
                ->exists();
            abort_if($exists, 422, 'A D.O. Letter for this circle and month already exists.');
        }

        $monthChanged = $newMonth
            && $newMonth->toDateString() !== Carbon::parse($doLetter->report_month)->startOfMonth()->toDateString();

        if ($monthChanged) {
            $doLetter->report_month = $newMonth->toDateString();
        }

        $shouldRecompile = $request->boolean('recompile') || $monthChanged;

        if ($shouldRecompile) {
            $built = $this->builder->build((int) $doLetter->circle_id, Carbon::parse($doLetter->report_month));
            $doLetter->payload = $built['payload'];
            $doLetter->month_label = $built['month_label'];
        } elseif (array_key_exists('payload', $data)) {
            $doLetter->payload = $data['payload'];
        }

        $doLetter->notes = $data['notes'] ?? $doLetter->notes;
        $doLetter->compiled_by = $user->id;
        $doLetter->save();

        return response()->json(['message' => 'D.O. Letter updated', 'letter' => $doLetter->fresh()->load(['circle', 'compiler'])]);
    }

    public function submitToCi(Request $request, DoLetter $doLetter)
    {
        abort_unless($this->canCompile($request->user()), 403);
        abort_unless(in_array($doLetter->status, [DoLetter::STATUS_DRAFT, DoLetter::STATUS_SENT_BACK], true), 422);

        $doLetter->update([
            'status' => DoLetter::STATUS_SUBMITTED_CI,
            'submitted_to_ci_at' => now(),
            'send_back_remarks' => null,
        ]);

        return response()->json(['message' => 'D.O. Letter marked to Circle Incharge', 'letter' => $doLetter->fresh()]);
    }

    public function approveCi(Request $request, DoLetter $doLetter)
    {
        abort_unless($this->canReviewCi($request->user()), 403);
        abort_unless($doLetter->status === DoLetter::STATUS_SUBMITTED_CI, 422);

        $data = $request->validate(['ci_remarks' => 'nullable|string|max:5000']);

        $doLetter->update([
            'status' => DoLetter::STATUS_CI_APPROVED,
            'ci_reviewed_by' => $request->user()->id,
            'ci_reviewed_at' => now(),
            'ci_remarks' => $data['ci_remarks'] ?? null,
        ]);

        return response()->json(['message' => 'D.O. Letter approved by Circle Incharge', 'letter' => $doLetter->fresh()]);
    }

    public function sendBack(Request $request, DoLetter $doLetter)
    {
        abort_unless($this->canReviewCi($request->user()), 403);
        abort_unless(in_array($doLetter->status, [DoLetter::STATUS_SUBMITTED_CI, DoLetter::STATUS_CI_APPROVED], true), 422);

        $data = $request->validate(['send_back_remarks' => 'required|string|max:5000']);

        $doLetter->update([
            'status' => DoLetter::STATUS_SENT_BACK,
            'send_back_remarks' => $data['send_back_remarks'],
        ]);

        return response()->json(['message' => 'D.O. Letter sent back to ADA', 'letter' => $doLetter->fresh()]);
    }

    public function forwardToHq(Request $request, DoLetter $doLetter)
    {
        abort_unless($this->canReviewCi($request->user()), 403);
        abort_unless($doLetter->status === DoLetter::STATUS_CI_APPROVED, 422);

        $doLetter->update([
            'status' => DoLetter::STATUS_FORWARDED_HQ,
            'forwarded_by' => $request->user()->id,
            'forwarded_to_hq_at' => now(),
        ]);

        return response()->json(['message' => 'D.O. Letter forwarded to Islamabad HQ', 'letter' => $doLetter->fresh()]);
    }

    public function acknowledgeHq(Request $request, DoLetter $doLetter)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'director_general']), 403);
        abort_unless($doLetter->status === DoLetter::STATUS_FORWARDED_HQ, 422);

        $doLetter->update([
            'status' => DoLetter::STATUS_HQ_ACK,
            'hq_acknowledged_by' => $request->user()->id,
            'hq_acknowledged_at' => now(),
        ]);

        return response()->json(['message' => 'D.O. Letter acknowledged by HQ', 'letter' => $doLetter->fresh()]);
    }

    public function export(DoLetter $doLetter, Request $request, DoLetterExcelExportService $excel)
    {
        abort_unless(DoLetter::visibleTo($request->user())->whereKey($doLetter->id)->exists(), 403);

        if ($request->query('format') === 'html') {
            $html = view('exports.do-letter', ['letter' => $doLetter->load('circle')])->render();

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="DO-Letter-' . $doLetter->month_label . '.html"',
            ]);
        }

        return $excel->download($doLetter->load('circle'));
    }

    private function canCompile(?User $user): bool
    {
        return $user && $user->hasAnyRole(['admin', 'ad_administration']);
    }

    private function canReviewCi(?User $user): bool
    {
        return $user && $user->hasAnyRole(['admin', 'circle_incharge']);
    }

    private function authorizeCircle(?User $user, int $circleId): void
    {
        if ($user?->hasAnyRole(['admin', 'director_general'])) {
            return;
        }

        abort_unless((int) $user?->circle_id === $circleId, 403);
    }
}
