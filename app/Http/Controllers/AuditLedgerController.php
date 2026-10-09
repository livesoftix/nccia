<?php

namespace App\Http\Controllers;

use App\Models\AuditLedger;
use App\Services\AuditLedgerService;
use Illuminate\Http\Request;

/**
 * Admin view of the immutable audit ledger + a one-click integrity check.
 */
class AuditLedgerController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLedger::query()->with('user:id,name')->orderByDesc('id');

        if ($event = $request->query('event')) {
            $q->where('event', 'like', $event . '%');
        }
        if ($type = $request->query('subject_type')) {
            $q->where('subject_type', 'like', '%' . $type);
        }
        if ($uid = $request->query('user_id')) {
            $q->where('user_id', $uid);
        }
        if ($from = $request->query('from')) {
            $q->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }

        return response()->json($q->paginate(min(100, max(10, (int) $request->query('per_page', 25)))));
    }

    public function verify()
    {
        $result = AuditLedgerService::verify();

        return response()->json([
            'intact'    => $result['ok'],
            'entries'   => $result['checked'],
            'broken_at' => $result['broken_at'],
            'message'   => $result['ok']
                ? "Audit chain intact — {$result['checked']} entries verified, no tampering detected."
                : "TAMPERING DETECTED — chain breaks at entry #{$result['broken_at']}.",
        ], $result['ok'] ? 200 : 409);
    }
}
