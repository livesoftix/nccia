<?php

namespace App\Http\Controllers;

use App\Models\AuditLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Requirement #3 — Near-real-time synchronisation.
 *
 * The immutable audit ledger (#1) already records EVERY create/update/delete
 * with a monotonically increasing id. That id is therefore a perfect global
 * "version counter" for the whole system. Instead of websockets (which need an
 * on-prem broker the client operates — see the Security Responsibilities doc),
 * the SPA polls this one very cheap endpoint on a short interval:
 *
 *   GET /api/sync/pulse?since=<last_seen_ledger_id>
 *
 * It answers "has anything changed, and which modules?" with a single indexed
 * `WHERE id > ?` scan — O(changes-since), not O(total-records) — so it stays
 * fast even at the scale targeted in requirement #5. The client refetches only
 * the modules named in `changed`, which makes updates appear near-instantly
 * without hammering the heavy list/dashboard endpoints.
 *
 * Broadcast-ready: the exact same `head` value can later be pushed over a
 * websocket (Laravel Reverb/Pusher) with no change to the client contract — the
 * client just stops polling and reacts to the pushed head instead.
 */
class SyncController extends Controller
{
    /** Map the audited Eloquent class basenames to the SPA's module keys. */
    private const MODULE_MAP = [
        'Complaint'          => 'complaints',
        'Verification'       => 'verifications',
        'VerificationReport' => 'verifications',
        'Enquiry'            => 'enquiries',
        'CaseFile'           => 'cases',
        'CourtCase'          => 'court',
        'CourtVerdict'       => 'court',
        'Arrest'             => 'cases',
        'ForensicRequest'    => 'forensic',
        'WarrantRequest'     => 'warrants',
        'Proclamation'       => 'proclamations',
        'PropertyAttachment' => 'proclamations',
        'User'               => 'users',
        'ApprovalSetting'    => 'settings',
    ];

    public function pulse(Request $request)
    {
        $now = now();

        // Ledger absent (e.g. migration not yet run) → degrade gracefully.
        if (! Schema::hasTable('audit_ledger')) {
            return response()->json([
                'head'        => 0,
                'server_time' => $now->toIso8601String(),
                'changed'     => [],
                'changes'     => 0,
                'ledger'      => false,
            ]);
        }

        $head = (int) (AuditLedger::max('id') ?? 0);
        $since = (int) $request->query('since', 0);

        $changedModules = [];
        $changeCount = 0;

        if ($since > 0 && $since < $head) {
            $rows = AuditLedger::query()
                ->where('id', '>', $since)
                ->get(['subject_type'])
                ->pluck('subject_type');

            $changeCount = $rows->count();

            $changedModules = $rows
                ->map(fn ($type) => self::MODULE_MAP[class_basename((string) $type)] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return response()->json([
            'head'        => $head,          // current global version
            'server_time' => $now->toIso8601String(),
            'changed'     => $changedModules, // module keys the client should refetch
            'changes'     => $changeCount,    // number of ledger entries since `since`
            'ledger'      => true,
        ]);
    }
}
