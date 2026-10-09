<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ApprovalSetting;
use App\Models\WarrantRequest;

/**
 * Blocks issuing/printing a warrant, proclamation or attachment when the admin
 * has set that action to "mandatory" approval and no approved request exists
 * yet for the enquiry/case. When the setting is "open", it is a no-op.
 */
trait EnforcesWarrantApproval
{
    protected function assertWarrantApproved(string $actionKey, ?int $enquiryId = null, ?int $caseFileId = null): void
    {
        if (ApprovalSetting::isMandatory($actionKey)
            && ! WarrantRequest::hasApproved($actionKey, $enquiryId, $caseFileId)) {
            abort(403, 'Circle Incharge approval is required before this document can be issued.');
        }
    }
}
