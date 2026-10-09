<?php

namespace App\Http\Controllers;

use App\Models\Profession;
use App\Models\ReceivedViaOption;
use App\Models\ReceivedFromOption;
use App\Models\CMUOption;
use App\Models\OffenceType;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    public function professions()
    {
        $data = Cache::remember('lookup_professions', 3600, function () {
            return Profession::orderBy('name')->get(['id', 'name']);
        });
        return response()->json($data);
    }

    public function receivedVia()
    {
        $data = Cache::remember('lookup_received_via', 3600, function () {
            return ReceivedViaOption::orderBy('name')->get(['id', 'name']);
        });
        return response()->json($data);
    }

    public function receivedFrom()
    {
        $data = Cache::remember('lookup_received_from', 3600, function () {
            return ReceivedFromOption::orderBy('group')->orderBy('name')->get(['id', 'group', 'name']);
        });
        return response()->json($data);
    }

    public function cmuOptions()
    {
        $data = Cache::remember('lookup_cmu_options', 3600, function () {
            return CMUOption::orderBy('name')->get(['id', 'name']);
        });
        return response()->json($data);
    }

    public function offenceTypes()
    {
        $data = Cache::remember('lookup_offence_types', 3600, function () {
            return OffenceType::orderBy('group')->orderBy('name')->get(['id', 'group', 'name', 'value']);
        });
        return response()->json($data);
    }

    private function resolveScope(): array
    {
        $user = request()->user();
        if (!$user) {
            return ['type' => 'none', 'id' => 0];
        }
        if ($user->seesAllData()) {
            $reqCircle = (int) request()->query('circle_id', 0);
            $reqZone = (int) request()->query('zone_id', 0);
            if ($reqCircle > 0) return ['type' => 'circle', 'id' => $reqCircle];
            if ($reqZone > 0) return ['type' => 'zone', 'id' => $reqZone];
            return ['type' => 'all', 'id' => 0];
        }
        if ($user->isZonalHead()) {
            $effectiveZoneId = (int) ($user->zone_id ?: $user->circle?->zone_id);
            $reqCircle = (int) request()->query('circle_id', 0);
            if ($reqCircle > 0 && $user->canAccessCircle($reqCircle)) {
                return ['type' => 'circle', 'id' => $reqCircle];
            }
            return ['type' => 'zone', 'id' => $effectiveZoneId];
        }

        // Strictly locked to user's assigned circle
        return ['type' => 'circle', 'id' => (int) ($user->circle_id ?: 0)];
    }

    public function enquiryOfficers()
    {
        $scope = $this->resolveScope();
        $cacheKey = 'lookup_eo_' . $scope['type'] . '_' . $scope['id'];
        
        $data = Cache::remember($cacheKey, 60, function () use ($scope) {
            $query = \App\Models\User::role('enquiry_officer')
                ->with('circle:id,name,code', 'zone:id,name,code')
                ->orderBy('name');

            if ($scope['type'] === 'circle') {
                $query->where('circle_id', $scope['id']);
            } elseif ($scope['type'] === 'zone') {
                $query->where(function ($q) use ($scope) {
                    $q->where('zone_id', $scope['id'])
                      ->orWhereHas('circle', fn ($cq) => $cq->where('zone_id', $scope['id']));
                });
            }

            return $query->get(['id', 'name', 'designation', 'circle_id', 'zone_id']);
        });

        return response()->json($data);
    }

    public function legalOfficers()
    {
        $scope = $this->resolveScope();
        $cacheKey = 'lookup_legal_' . $scope['type'] . '_' . $scope['id'];

        $data = Cache::remember($cacheKey, 60, function () use ($scope) {
            $query = \App\Models\User::role(['ad_legal', 'additional_director', 'dd_legal'])
                ->with('circle:id,name,code', 'zone:id,name,code')
                ->orderBy('name');

            if ($scope['type'] === 'circle') {
                $query->where(function ($q) use ($scope) {
                    $q->where('circle_id', $scope['id'])->orWhereNull('circle_id');
                });
            } elseif ($scope['type'] === 'zone') {
                $query->where(function ($q) use ($scope) {
                    $q->where('zone_id', $scope['id'])
                      ->orWhereHas('circle', fn ($cq) => $cq->where('zone_id', $scope['id']))
                      ->orWhereNull('circle_id');
                });
            }

            return $query->get(['id', 'name', 'designation', 'circle_id', 'zone_id']);
        });

        return response()->json($data);
    }

    public function verificationOfficers()
    {
        $scope = $this->resolveScope();
        $cacheKey = 'lookup_vo_' . $scope['type'] . '_' . $scope['id'];

        $data = Cache::remember($cacheKey, 60, function () use ($scope) {
            $query = \App\Models\User::role('verification_officer')
                ->with('circle:id,name,code', 'zone:id,name,code')
                ->orderBy('name');

            if ($scope['type'] === 'circle') {
                $query->where('circle_id', $scope['id']);
            } elseif ($scope['type'] === 'zone') {
                $query->where(function ($q) use ($scope) {
                    $q->where('zone_id', $scope['id'])
                      ->orWhereHas('circle', fn ($cq) => $cq->where('zone_id', $scope['id']));
                });
            }

            return $query->get(['id', 'name', 'designation', 'circle_id', 'zone_id']);
        });

        return response()->json($data);
    }

    public function investigationOfficers()
    {
        $scope = $this->resolveScope();
        $cacheKey = 'lookup_io_' . $scope['type'] . '_' . $scope['id'];

        $data = Cache::remember($cacheKey, 60, function () use ($scope) {
            $query = \App\Models\User::role('investigation_officer')
                ->with('circle:id,name,code', 'zone:id,name,code')
                ->orderBy('name');

            if ($scope['type'] === 'circle') {
                $query->where('circle_id', $scope['id']);
            } elseif ($scope['type'] === 'zone') {
                $query->where(function ($q) use ($scope) {
                    $q->where('zone_id', $scope['id'])
                      ->orWhereHas('circle', fn ($cq) => $cq->where('zone_id', $scope['id']));
                });
            }

            return $query->get(['id', 'name', 'designation', 'circle_id', 'zone_id']);
        });

        return response()->json($data);
    }

    public function circleIncharges()
    {
        $scope = $this->resolveScope();
        $cacheKey = 'lookup_ci_' . $scope['type'] . '_' . $scope['id'];

        $data = Cache::remember($cacheKey, 60, function () use ($scope) {
            $query = \App\Models\User::role('circle_incharge')
                ->with('circle:id,name,code', 'zone:id,name,code')
                ->orderBy('name');

            if ($scope['type'] === 'circle') {
                $query->where('circle_id', $scope['id']);
            } elseif ($scope['type'] === 'zone') {
                $query->where(function ($q) use ($scope) {
                    $q->where('zone_id', $scope['id'])
                      ->orWhereHas('circle', fn ($cq) => $cq->where('zone_id', $scope['id']));
                });
            }

            return $query->get(['id', 'name', 'designation', 'circle_id', 'zone_id']);
        });

        return response()->json($data);
    }
}
