<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\EnquiryAccused;
use App\Models\EnquiryActivity;
use App\Models\EnquiryWitness;
use App\Models\ForensicRequest;
use App\Models\VerificationReport;

class EnquiryRecordHydrator
{
    /**
     * Copy complaint / verification accused, witnesses and seized devices
     * onto the enquiry the first time the assigned officer opens it.
     */
    public function hydrate(Enquiry $enquiry): void
    {
        $enquiry->loadMissing(['complaint.latestVerificationReport', 'accusedPersons', 'witnesses', 'activities']);

        $complaint = $enquiry->complaint;
        $report = $complaint?->latestVerificationReport;
        if (!$report && $complaint) {
            $report = VerificationReport::where('complaint_id', $complaint->id)->latest('id')->first();
        }

        $this->hydrateAccused($enquiry, $complaint, $report);
        $this->hydrateWitnesses($enquiry, $complaint, $report);
        $this->hydrateDevices($enquiry, $complaint, $report);

        if ($complaint?->priority_type && empty($enquiry->priority) && \Illuminate\Support\Facades\Schema::hasColumn('enquiries', 'priority')) {
            $enquiry->forceFill(['priority' => $complaint->priority_type])->save();
        }
    }

    private function hydrateAccused(Enquiry $enquiry, $complaint, $report): void
    {
        if ($enquiry->accusedPersons()->exists()) {
            return;
        }

        $source = [];
        if (is_array($report?->accused)) {
            $source = $report->accused;
        } elseif (is_array($complaint?->initial_accused)) {
            $source = $complaint->initial_accused;
        }

        foreach ($source as $a) {
            if (!is_array($a) || (empty($a['name']) && empty($a['cnic']))) {
                continue;
            }
            EnquiryAccused::create([
                'enquiry_id'         => $enquiry->id,
                'name'               => $a['name'] ?? null,
                'cnic'               => $a['cnic'] ?? null,
                'father_name'        => $a['father_name'] ?? null,
                'gender'             => $a['gender'] ?? null,
                'contact_no'         => $a['contact_no'] ?? ($a['phone'] ?? null),
                'whatsapp_no'        => $a['whatsapp_no'] ?? ($a['phone'] ?? ($a['contact_no'] ?? null)),
                'email'              => $a['email'] ?? null,
                'postal_address'     => $a['postal_address'] ?? ($a['post_address'] ?? ($a['address'] ?? null)),
                'permanent_address'  => $a['permanent_address'] ?? ($a['address'] ?? null),
                'description'        => $a['description'] ?? null,
            ]);
        }
    }

    private function hydrateWitnesses(Enquiry $enquiry, $complaint, $report): void
    {
        if ($enquiry->witnesses()->exists()) {
            return;
        }

        $source = [];
        foreach (['witnesses', 'initial_witnesses'] as $key) {
            $chunk = data_get($report, $key);
            if (is_array($chunk)) {
                $source = array_merge($source, $chunk);
            }
            $compChunk = data_get($complaint, $key);
            if (is_array($compChunk)) {
                $source = array_merge($source, $compChunk);
            }
        }

        if ($enquiry->witness_name) {
            $source[] = [
                'name'       => $enquiry->witness_name,
                'cnic'       => $enquiry->witness_cnic,
                'nationality'=> $enquiry->witness_nationality,
                'passport'   => $enquiry->witness_passport,
                'address'    => $enquiry->witness_address,
            ];
        }

        $seen = [];
        foreach ($source as $w) {
            if (!is_array($w) || (empty($w['name']) && empty($w['cnic']))) {
                continue;
            }
            $key = strtolower(trim(($w['cnic'] ?? '') . '|' . ($w['name'] ?? '')));
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            EnquiryWitness::create([
                'enquiry_id'         => $enquiry->id,
                'name'               => $w['name'] ?? null,
                'father_name'        => $w['father_name'] ?? null,
                'cnic'               => $w['cnic'] ?? null,
                'gender'             => $w['gender'] ?? null,
                'contact_no'         => $w['contact_no'] ?? ($w['phone'] ?? null),
                'whatsapp_no'        => $w['whatsapp_no'] ?? null,
                'mailing_address'    => $w['mailing_address'] ?? ($w['address'] ?? null),
                'permanent_address'  => $w['permanent_address'] ?? ($w['address'] ?? null),
                'address'            => $w['address'] ?? ($w['mailing_address'] ?? null),
                'nationality'        => $w['nationality'] ?? null,
                'passport'           => $w['passport'] ?? null,
                'occupation'         => $w['occupation'] ?? null,
            ]);
        }
    }

    private function hydrateDevices(Enquiry $enquiry, $complaint, $report): void
    {
        $hasSeize = $enquiry->activities->contains(function ($act) {
            if (!in_array($act->type, ['seizures', 'search_seize'], true)) {
                return false;
            }
            $items = $act->meta['seize_items'] ?? [];

            return is_array($items) && count($items) > 0;
        });
        if ($hasSeize) {
            return;
        }

        $devices = [];
        $push = function (array $it) use (&$devices) {
            $norm = [
                'item_type'        => (string) ($it['item_type'] ?? $it['type'] ?? 'phone'),
                'make_model'       => (string) ($it['make_model'] ?? $it['model'] ?? ''),
                'imei'             => (string) ($it['imei'] ?? ''),
                'imei2'            => (string) ($it['imei2'] ?? ''),
                'serial_no'        => (string) ($it['serial_no'] ?? ''),
                'storage_capacity' => (string) ($it['storage_capacity'] ?? ''),
                'condition'        => (string) ($it['condition'] ?? 'sealed'),
                'quantity'         => (string) ($it['quantity'] ?? '1'),
                'description'      => (string) ($it['description'] ?? $it['desc'] ?? ''),
                'seized_from'      => (string) ($it['seized_from'] ?? $it['owner'] ?? ''),
            ];
            if (!$norm['item_type'] && !$norm['make_model'] && !$norm['imei'] && !$norm['serial_no'] && !$norm['description']) {
                return;
            }
            $devices[] = $norm;
        };

        foreach ([data_get($report, 'evidence'), data_get($complaint, 'evidence'), data_get($report, 'devices'), data_get($report, 'seized_devices')] as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                if (!empty($ev['imei']) || !empty($ev['item_type']) || !empty($ev['make_model']) || !empty($ev['serial_no'])) {
                    $push($ev);
                }
            }
        }

        if ($complaint?->platform_mobile_involved) {
            $push([
                'item_type'   => 'phone',
                'imei'        => (string) $complaint->platform_mobile_involved,
                'description' => 'Mobile involved (from complaint)',
            ]);
        }

        $requests = ForensicRequest::with('items')->where('enquiry_id', $enquiry->id)->get();
        foreach ($requests as $fr) {
            foreach ($fr->items as $it) {
                $push($it->toArray());
            }
        }

        if (!$devices) {
            return;
        }

        EnquiryActivity::create([
            'enquiry_id'    => $enquiry->id,
            'type'          => 'seizures',
            'description'   => 'Auto-fetched seized / involved devices from complaint & verification record',
            'meta'          => ['seize_items' => $devices],
            'activity_date' => now()->toDateString(),
            'created_by'    => $enquiry->enquiry_officer_id,
        ]);
    }
}
