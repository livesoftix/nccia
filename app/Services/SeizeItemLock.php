<?php

namespace App\Services;

use App\Models\CaseActivity;
use App\Models\EnquiryActivity;
use App\Models\ForensicRequest;
use Illuminate\Support\Facades\Schema;

class SeizeItemLock
{
    public static function key(array $item): string
    {
        $parts = [
            strtolower(trim((string) ($item['item_type'] ?? ''))),
            strtolower(trim((string) ($item['imei'] ?? ''))),
            strtolower(trim((string) ($item['imei2'] ?? ''))),
            strtolower(trim((string) ($item['serial_no'] ?? ''))),
            strtolower(trim((string) ($item['make_model'] ?? ''))),
        ];
        $joined = implode('|', $parts);
        if ($joined === '||||') {
            return 'desc:' . strtolower(trim((string) ($item['description'] ?? '')));
        }

        return $joined;
    }

    public static function isLocked(array $item): bool
    {
        return ! empty($item['locked']) || ! empty($item['submitted']);
    }

    public static function mapIncoming(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = [
                'item_type'        => $item['item_type'] ?? null,
                'make_model'       => $item['make_model'] ?? null,
                'imei'             => $item['imei'] ?? null,
                'imei2'            => $item['imei2'] ?? null,
                'serial_no'        => $item['serial_no'] ?? null,
                'quantity'         => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                'storage_capacity' => $item['storage_capacity'] ?? null,
                'condition'        => $item['condition'] ?? null,
                'description'      => $item['description'] ?? null,
                'owner_type'       => $item['owner_type'] ?? null,
                'owner_ref'        => $item['owner_ref'] ?? null,
                'locked'           => self::isLocked($item),
                'submitted'        => self::isLocked($item),
            ];
            if (
                empty($row['item_type']) && empty($row['make_model'])
                && empty($row['imei']) && empty($row['serial_no'])
                && empty($row['description'])
            ) {
                continue;
            }
            $out[] = $row;
        }

        return array_values($out);
    }

    public static function merge(array $incoming, array $existing, array $forensicKeys = []): array
    {
        $lockedExisting = [];
        foreach ($existing as $ex) {
            if (! is_array($ex)) {
                continue;
            }
            $itemKey = self::key($ex);
            if (self::isLocked($ex) || isset($forensicKeys[$itemKey])) {
                $ex['locked'] = true;
                $ex['submitted'] = true;
                $lockedExisting[$itemKey] = $ex;
            }
        }

        $out = [];
        $seen = [];
        foreach ($incoming as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemKey = self::key($item);
            if (isset($lockedExisting[$itemKey])) {
                $out[] = $lockedExisting[$itemKey];
                $seen[$itemKey] = true;
                continue;
            }
            if (isset($forensicKeys[$itemKey])) {
                $item['locked'] = true;
                $item['submitted'] = true;
            }
            $out[] = $item;
            $seen[$itemKey] = true;
        }

        foreach ($lockedExisting as $itemKey => $item) {
            if (empty($seen[$itemKey])) {
                $out[] = $item;
            }
        }

        return array_values($out);
    }

    public static function forensicKeysForEnquiry(?int $enquiryId): array
    {
        if (! $enquiryId) {
            return [];
        }

        return self::keysFromQuery(static function ($query) use ($enquiryId) {
            $query->where('enquiry_id', $enquiryId);
        });
    }

    public static function forensicKeysForCase(?int $caseId, ?int $enquiryId = null): array
    {
        if (! $caseId && ! $enquiryId) {
            return [];
        }

        return self::keysFromQuery(static function ($query) use ($caseId, $enquiryId) {
            $query->where(function ($inner) use ($caseId, $enquiryId) {
                if ($caseId) {
                    $inner->where('case_id', $caseId);
                }
                if ($enquiryId) {
                    if ($caseId) {
                        $inner->orWhere('enquiry_id', $enquiryId);
                    } else {
                        $inner->where('enquiry_id', $enquiryId);
                    }
                }
            });
        });
    }

    public static function activityHasLockedItems($activity, array $forensicKeys = []): bool
    {
        $meta = is_array($activity->meta ?? null) ? $activity->meta : [];
        $items = is_array($meta['seize_items'] ?? null) ? $meta['seize_items'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (self::isLocked($item) || isset($forensicKeys[self::key($item)])) {
                return true;
            }
        }

        return false;
    }

    public static function lockSubmittedItems(?int $enquiryId, ?int $caseId, array $submittedItems): void
    {
        $keys = [];
        foreach ($submittedItems as $item) {
            if (is_array($item)) {
                $keys[self::key($item)] = true;
            }
        }

        if ($enquiryId) {
            $keys = $keys + self::forensicKeysForEnquiry($enquiryId);
            self::lockActivities(
                EnquiryActivity::where('enquiry_id', $enquiryId)->whereIn('type', ['seizures', 'seizure'])->get(),
                $keys
            );
        }

        if ($caseId) {
            $keys = $keys + self::forensicKeysForCase($caseId, $enquiryId);
            self::lockActivities(
                CaseActivity::where('case_id', $caseId)->whereIn('type', ['seizure', 'seizures'])->get(),
                $keys
            );
        }
    }

    private static function lockActivities($activities, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        foreach ($activities as $act) {
            $meta = is_array($act->meta) ? $act->meta : [];
            $items = is_array($meta['seize_items'] ?? null) ? $meta['seize_items'] : [];
            $changed = false;
            foreach ($items as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (isset($keys[self::key($row)]) && ! self::isLocked($row)) {
                    $items[$index]['locked'] = true;
                    $items[$index]['submitted'] = true;
                    $changed = true;
                }
            }
            if ($changed) {
                $meta['seize_items'] = $items;
                $act->meta = $meta;
                $act->save();
            }
        }
    }

    private static function keysFromQuery(callable $scope): array
    {
        try {
            if (! Schema::hasTable('forensic_requests') || ! Schema::hasTable('forensic_request_items')) {
                return [];
            }
        } catch (\Throwable $error) {
            return [];
        }

        try {
            $query = ForensicRequest::query();
            $scope($query);
            $keys = [];
            foreach ($query->with('items')->get() as $request) {
                foreach ($request->items as $fi) {
                    $keys[self::key([
                        'item_type'   => $fi->item_type,
                        'imei'        => $fi->imei,
                        'imei2'       => $fi->imei2,
                        'serial_no'   => $fi->serial_no,
                        'make_model'  => $fi->make_model,
                        'description' => $fi->description,
                    ])] = true;
                }
            }

            return $keys;
        } catch (\Throwable $error) {
            return [];
        }
    }
}
