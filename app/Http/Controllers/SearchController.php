<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerificationReport;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global header search: tracking no / complainant / CNIC / verification id.
     * For EO role: also searches by enquiry_number, accused name, and returns enquiry-aware results.
     * process_url is role + lifecycle aware.
     */
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $isEo = $user?->hasRole('enquiry_officer')
            && !$user?->hasAnyRole(['admin', 'circle_incharge', 'director_general']);

        // ── EO-specific: search enquiries directly by enquiry_number or accused name ──
        $enquiryResults = collect();
        if ($isEo) {
            $enquiryQuery = Enquiry::with([
                    'complaint:id,tracking_no,complainant_name,cnic,circle_id',
                    'accusedPersons:id,enquiry_id,name,cnic',
                ])
                ->where('enquiry_officer_id', $user->id)
                ->where(function ($eq) use ($like, $q) {
                    $eq->where('enquiry_number', 'like', $q . '%')
                       ->orWhere('enquiry_number', 'like', $like);

                    // search by accused name or cnic
                    $eq->orWhereHas('accusedPersons', function ($aq) use ($like) {
                        $aq->where('name', 'like', $like)
                           ->orWhere('cnic', 'like', $like);
                    });

                    // if numeric, match enquiry id too
                    if (ctype_digit($q)) {
                        $eq->orWhere('id', (int) $q);
                    }
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            $enquiryResults = $enquiryQuery->map(function (Enquiry $e) {
                $c = $e->complaint;
                $accused = $e->accusedPersons->first();
                return [
                    'type'             => 'enquiry',
                    'id'               => $e->id,
                    'enquiry_id'       => $e->id,
                    'enquiry_number'   => $e->enquiry_number,
                    'tracking_no'      => $c?->tracking_no,
                    'complainant_name' => $c?->complainant_name,
                    'cnic'             => $c?->cnic,
                    'status'           => $e->status,
                    'accused_name'     => $accused?->name,
                    'process_url'      => '/enquiries/' . $e->id . '/edit',
                ];
            });
        }

        // ── Complaint search (all roles) ──
        $complaints = Complaint::visibleTo($user)
            ->with(['verification.officer', 'enquiry', 'caseFiles'])
            ->where(function ($query) use ($like, $q) {
                $query->where('tracking_no', 'like', $q . '%')
                    ->orWhere('diary_no', 'like', $q . '%')
                    ->orWhere('cnic', 'like', $q . '%')
                    ->orWhere('complainant_name', 'like', $q . '%');

                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                } elseif (str_contains($q, '/')) {
                    $query->orWhere('tracking_no', 'like', '%' . $q . '%');
                }
            })
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $extraVerificationIds = [];
        if (ctype_digit($q)) {
            $extraVerificationIds = Verification::visibleTo($user)
                ->where('id', (int) $q)
                ->whereNotIn('complaint_id', $complaints->pluck('id'))
                ->with(['complaint.enquiry', 'complaint.caseFiles', 'officer'])
                ->limit(5)
                ->get();
        }

        $reportIdsByComplaint = VerificationReport::query()
            ->whereIn('complaint_id', $complaints->pluck('id')->filter()->all())
            ->orderByDesc('id')
            ->get(['id', 'complaint_id'])
            ->groupBy('complaint_id')
            ->map(fn ($rows) => $rows->first()->id);

        $results = $complaints->map(function (Complaint $c) use ($user, $reportIdsByComplaint) {
            $v = $c->verification;
            $reportId = $reportIdsByComplaint[$c->id] ?? null;

            return [
                'type'                => 'complaint',
                'id'                  => $c->id,
                'tracking_no'         => $c->tracking_no,
                'complainant_name'    => $c->complainant_name,
                'cnic'                => $c->cnic,
                'status'              => $c->final_status ?: $c->status,
                'verification_id'     => $v?->id,
                'verification_no'     => $v ? ('V-' . $v->id) : null,
                'verification_status' => $v?->status,
                'officer_name'        => $v?->officer?->name,
                'enquiry_number'      => $c->enquiry?->enquiry_number,
                'enquiry_id'          => $c->enquiry?->id,
                'process_url'         => $this->resolveProcessUrl($c, $user, $reportId),
            ];
        });

        foreach ($extraVerificationIds as $v) {
            $c = $v->complaint;
            if (!$c) {
                continue;
            }
            $reportId = VerificationReport::where('complaint_id', $c->id)->orderByDesc('id')->value('id');
            $c->setRelation('verification', $v);

            $results->push([
                'type'                => 'verification',
                'id'                  => $c->id,
                'tracking_no'         => $c->tracking_no,
                'complainant_name'    => $c->complainant_name,
                'cnic'                => $c->cnic,
                'status'              => $c->final_status ?: $c->status,
                'verification_id'     => $v->id,
                'verification_no'     => 'V-' . $v->id,
                'verification_status' => $v->status,
                'officer_name'        => $v->officer?->name,
                'process_url'         => $this->resolveProcessUrl($c, $user, $reportId),
            ]);
        }

        // Merge EO enquiry results at the top, then complaint results
        $merged = $enquiryResults->values()->merge($results->values())->values();

        return response()->json(['data' => $merged]);
    }

    /**
     * Deepest relevant screen for the user's role.
     */
    protected function resolveProcessUrl(Complaint $c, ?User $user, ?int $reportId): string
    {
        $case = $c->caseFiles->first();
        $enquiry = $c->enquiry;
        $v = $c->verification;

        // EO: go directly to enquiry edit if one exists
        if ($user && $user->hasRole('enquiry_officer')
            && !$user->hasAnyRole(['admin', 'circle_incharge', 'director_general'])) {
            if ($enquiry) {
                return '/enquiries/' . $enquiry->id . '/edit';
            }
            return '/complaints/' . $c->id . '/edit';
        }

        // Verification officer: always open the victim verification REPORT (not assignment edit)
        if ($user && $user->hasRole('verification_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'director_general'])) {
            if ($reportId) {
                return '/verifications/reports/' . $reportId . '/edit';
            }
            if ($c->tracking_no) {
                return '/verifications/reports/create?tracking=' . rawurlencode($c->tracking_no);
            }

            return '/verifications/reports/create';
        }

        // Later lifecycle stages
        if ($case) {
            return '/cases/' . $case->id . '/edit';
        }
        if ($enquiry) {
            return '/enquiries/' . $enquiry->id . '/edit';
        }

        // CI / admin: prefer report when available, else verification assignment
        if ($reportId && $user && $user->hasAnyRole(['admin', 'circle_incharge', 'verification_officer'])) {
            return '/verifications/reports/' . $reportId . '/edit';
        }

        if ($v) {
            return '/verifications/' . $v->id . '/edit';
        }

        return '/complaints/' . $c->id . '/edit';
    }
}
