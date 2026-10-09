<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\DsrReport;
use App\Models\User;
use App\Services\DsrReportBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DsrReportController extends Controller
{
    public function __construct(private DsrReportBuilderService $builder)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = DsrReport::visibleTo($user)
            ->with(['circle:id,name', 'compiler:id,name'])
            ->latest('report_date');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('report_date', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->whereDate('report_date', '<=', $to);
        }

        if ($user->hasRole('director_general')) {
            $query->whereIn('status', [
                DsrReport::STATUS_FORWARDED_HQ,
                DsrReport::STATUS_HQ_ACK,
            ]);
        }

        return response()->json($query->paginate(min(50, max(10, (int) $request->query('per_page', 15)))));
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'circle_id' => 'required|exists:circles,id',
            'report_date' => 'required|date',
            'unit_name' => 'nullable|string|max:120',
        ]);

        $this->authorizeCircle($request->user(), (int) $data['circle_id']);

        if (!Schema::hasTable('dsr_reports')) {
            return response()->json([
                'message' => 'DSR tables are missing. Run: php artisan migrate --force',
            ], 503);
        }

        try {
            $built = $this->builder->build(
                (int) $data['circle_id'],
                Carbon::parse($data['report_date']),
                $data['unit_name'] ?? null
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => config('app.debug')
                    ? ('DSR compile failed: ' . $e->getMessage())
                    : 'DSR compile failed. Ensure migrations are up to date, then retry or contact admin.',
            ], 500);
        }

        return response()->json($built);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canCompile($user), 403);

        if (!Schema::hasTable('dsr_reports')) {
            return response()->json([
                'message' => 'DSR tables are missing. Run: php artisan migrate --force',
            ], 503);
        }

        $data = $request->validate([
            'circle_id' => 'required|exists:circles,id',
            'report_date' => [
                'required',
                'date',
                Rule::unique('dsr_reports')->where(fn ($q) => $q->where('circle_id', $request->input('circle_id'))),
            ],
            'unit_name' => 'nullable|string|max:120',
            'auto_compile' => 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
            'highlights' => 'nullable|array',
            'payload' => 'nullable|array',
        ]);

        $this->authorizeCircle($user, (int) $data['circle_id']);

        try {
            $built = $request->boolean('auto_compile', true)
                ? $this->builder->build((int) $data['circle_id'], Carbon::parse($data['report_date']), $data['unit_name'] ?? null)
                : ['highlights' => $data['highlights'] ?? [], 'payload' => $data['payload'] ?? [], 'unit_name' => $data['unit_name'] ?? 'CCRC-Lahore'];
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => config('app.debug')
                    ? ('DSR compile failed: ' . $e->getMessage())
                    : 'DSR compile failed. Ensure migrations are up to date, then retry or contact admin.',
            ], 500);
        }

        try {
            $report = DsrReport::create([
            'circle_id' => $data['circle_id'],
            'report_date' => $data['report_date'],
            'unit_name' => $built['unit_name'] ?? ($data['unit_name'] ?? 'CCRC-Lahore'),
            'status' => DsrReport::STATUS_DRAFT,
            'highlights' => $request->input('highlights', $built['highlights'] ?? []),
            'payload' => $request->input('payload', $built['payload'] ?? []),
            'notes' => $data['notes'] ?? null,
            'compiled_by' => $user->id,
        ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => config('app.debug')
                    ? ('Could not save DSR: ' . $e->getMessage())
                    : 'Could not save DSR. If this date already exists for the circle, open that report instead.',
            ], 500);
        }

        return response()->json(['message' => 'DSR draft created', 'report' => $report->load(['circle', 'compiler'])], 201);
    }

    public function show(DsrReport $dsrReport, Request $request)
    {
        abort_unless(DsrReport::visibleTo($request->user())->whereKey($dsrReport->id)->exists(), 403);

        return response()->json($dsrReport->load(['circle', 'compiler', 'ciReviewer', 'forwarder', 'hqAcknowledger']));
    }

    public function update(Request $request, DsrReport $dsrReport)
    {
        $user = $request->user();
        abort_unless(DsrReport::visibleTo($user)->whereKey($dsrReport->id)->exists(), 403);
        abort_unless(in_array($dsrReport->status, [DsrReport::STATUS_DRAFT, DsrReport::STATUS_SENT_BACK], true), 422);
        abort_unless($this->canCompile($user), 403);

        $circleId = (int) ($request->input('circle_id') ?: $dsrReport->circle_id);

        $data = $request->validate([
            'circle_id' => 'nullable|exists:circles,id',
            'report_date' => [
                'nullable',
                'date',
                Rule::unique('dsr_reports')
                    ->where(fn ($q) => $q->where('circle_id', $circleId))
                    ->ignore($dsrReport->id),
            ],
            'unit_name' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:5000',
            'highlights' => 'nullable|array',
            'payload' => 'nullable|array',
            'recompile' => 'nullable|boolean',
        ]);

        if (!empty($data['circle_id']) && (int) $data['circle_id'] !== (int) $dsrReport->circle_id) {
            $this->authorizeCircle($user, (int) $data['circle_id']);
            $dsrReport->circle_id = (int) $data['circle_id'];
        }

        $dateChanged = !empty($data['report_date'])
            && $data['report_date'] !== $dsrReport->report_date->toDateString();

        if ($dateChanged) {
            $dsrReport->report_date = $data['report_date'];
        }

        $shouldRecompile = $request->boolean('recompile') || $dateChanged;

        if ($shouldRecompile) {
            try {
                $built = $this->builder->build(
                    (int) $dsrReport->circle_id,
                    Carbon::parse($dsrReport->report_date),
                    $data['unit_name'] ?? $dsrReport->unit_name
                );
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'message' => config('app.debug')
                        ? ('DSR compile failed: ' . $e->getMessage())
                        : 'DSR compile failed. Ensure migrations are up to date, then retry or contact admin.',
                ], 500);
            }
            $dsrReport->highlights = $built['highlights'];
            $dsrReport->payload = $built['payload'];
            if (!empty($data['unit_name'])) {
                $dsrReport->unit_name = $data['unit_name'];
            }
        } else {
            if (array_key_exists('highlights', $data)) {
                $dsrReport->highlights = $data['highlights'];
            }
            if (array_key_exists('payload', $data)) {
                $dsrReport->payload = $data['payload'];
            }
            if (!empty($data['unit_name'])) {
                $dsrReport->unit_name = $data['unit_name'];
            }
        }

        $dsrReport->notes = $data['notes'] ?? $dsrReport->notes;
        $dsrReport->compiled_by = $user->id;
        $dsrReport->save();

        return response()->json(['message' => 'DSR updated', 'report' => $dsrReport->fresh()->load(['circle', 'compiler'])]);
    }

    public function submitToCi(Request $request, DsrReport $dsrReport)
    {
        $user = $request->user();
        abort_unless($this->canCompile($user), 403);
        abort_unless(in_array($dsrReport->status, [DsrReport::STATUS_DRAFT, DsrReport::STATUS_SENT_BACK], true), 422);

        $dsrReport->update([
            'status' => DsrReport::STATUS_SUBMITTED_CI,
            'submitted_to_ci_at' => now(),
            'send_back_remarks' => null,
        ]);

        $this->notifyCircleIncharge($dsrReport, 'DSR submitted for your review');

        return response()->json(['message' => 'DSR marked to Circle Incharge', 'report' => $dsrReport->fresh()]);
    }

    public function approveCi(Request $request, DsrReport $dsrReport)
    {
        $user = $request->user();
        abort_unless($this->canReviewCi($user), 403);
        abort_unless($dsrReport->status === DsrReport::STATUS_SUBMITTED_CI, 422);

        $data = $request->validate(['ci_remarks' => 'nullable|string|max:5000']);

        $dsrReport->update([
            'status' => DsrReport::STATUS_CI_APPROVED,
            'ci_reviewed_by' => $user->id,
            'ci_reviewed_at' => now(),
            'ci_remarks' => $data['ci_remarks'] ?? null,
        ]);

        return response()->json(['message' => 'DSR approved by Circle Incharge', 'report' => $dsrReport->fresh()]);
    }

    public function sendBack(Request $request, DsrReport $dsrReport)
    {
        $user = $request->user();
        abort_unless($this->canReviewCi($user), 403);
        abort_unless(in_array($dsrReport->status, [DsrReport::STATUS_SUBMITTED_CI, DsrReport::STATUS_CI_APPROVED], true), 422);

        $data = $request->validate(['send_back_remarks' => 'required|string|max:5000']);

        $dsrReport->update([
            'status' => DsrReport::STATUS_SENT_BACK,
            'send_back_remarks' => $data['send_back_remarks'],
        ]);

        return response()->json(['message' => 'DSR sent back to ADA for correction', 'report' => $dsrReport->fresh()]);
    }

    public function forwardToHq(Request $request, DsrReport $dsrReport)
    {
        $user = $request->user();
        abort_unless($this->canReviewCi($user), 403);
        abort_unless($dsrReport->status === DsrReport::STATUS_CI_APPROVED, 422);

        $dsrReport->update([
            'status' => DsrReport::STATUS_FORWARDED_HQ,
            'forwarded_by' => $user->id,
            'forwarded_to_hq_at' => now(),
        ]);

        $this->notifyHq($dsrReport, 'DSR forwarded from Circle Incharge to Islamabad HQ');

        return response()->json(['message' => 'DSR forwarded to Islamabad HQ', 'report' => $dsrReport->fresh()]);
    }

    public function acknowledgeHq(Request $request, DsrReport $dsrReport)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['admin', 'director_general']), 403);
        abort_unless($dsrReport->status === DsrReport::STATUS_FORWARDED_HQ, 422);

        $dsrReport->update([
            'status' => DsrReport::STATUS_HQ_ACK,
            'hq_acknowledged_by' => $user->id,
            'hq_acknowledged_at' => now(),
        ]);

        return response()->json(['message' => 'DSR acknowledged by HQ', 'report' => $dsrReport->fresh()]);
    }

    public function export(DsrReport $dsrReport, Request $request)
    {
        abort_unless(DsrReport::visibleTo($request->user())->whereKey($dsrReport->id)->exists(), 403);

        $html = view('exports.dsr-report', ['report' => $dsrReport->load('circle')])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="DSR-' . $dsrReport->report_date->format('Y-m-d') . '.html"',
        ]);
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

    private function notifyCircleIncharge(DsrReport $report, string $message): void
    {
        $users = User::role('circle_incharge')
            ->when($report->circle_id, fn ($q) => $q->where('circle_id', $report->circle_id))
            ->get();

        // Notification channel can be wired later; keep workflow status as source of truth.
        unset($message, $users);
    }

    private function notifyHq(DsrReport $report, string $message): void
    {
        unset($report, $message);
    }
}
