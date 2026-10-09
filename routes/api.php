<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\ComplaintPdfImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\DsrReportController;
use App\Http\Controllers\DoLetterController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\CaseFileController;
use App\Http\Controllers\CourtCaseController;
use App\Http\Controllers\OffenceTypeController;
use App\Http\Controllers\InvestigationOfficerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SidebarCountsController;
use App\Http\Controllers\DepartmentProgressController;
use App\Http\Controllers\Forensic\ForensicUserController;
use App\Http\Controllers\Forensic\ForensicRequestController;
use App\Http\Controllers\OfficerAssignmentHistoryController;
use App\Http\Controllers\OfficerHandoverController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\DocumentVerifyController;
use App\Http\Controllers\ApprovalSettingController;
use App\Http\Controllers\WarrantRequestController;
use App\Http\Controllers\ProclamationController;
use App\Http\Controllers\SecureFileController;
use App\Http\Controllers\AuditLedgerController;
use App\Http\Controllers\SyncController;

Route::middleware(['web', 'auth:sanctum', 'account.security', 'throttle:dashboard'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'apiIndex']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/department-progress', [DepartmentProgressController::class, 'index'])
        ->middleware('role:director_general,additional_director,dd_legal,ad_legal,admin,circle_incharge');
    Route::get('/sidebar-counts', SidebarCountsController::class);
});

Route::middleware(['web', 'auth:sanctum', 'account.security', 'throttle:api'])->group(function () {
    Route::get('/search', SearchController::class)->name('api.search');

    // Authenticated serving of sensitive uploads (CNIC, evidence, forensic).
    // Signed + auth only; replaces public /storage and /uploads URLs.
    Route::get('/secure-file', [SecureFileController::class, 'show'])
        ->name('api.secure-file')
        ->middleware('signed');
    
    Route::get('/security-alerts', function (Request $request) {
        $user = $request->user();
        if ($user && \Illuminate\Support\Facades\Cache::has('login_alert_' . $user->id)) {
            return response()->json([
                'alert' => 'Security Alert: Someone just tried to login to your account using correct credentials from IP: ' . \Illuminate\Support\Facades\Cache::pull('login_alert_' . $user->id)
            ]);
        }
        return response()->json(null);
    });

    // Officer Lifecycle, Caseload Handover & Reassignment
    Route::middleware('role:admin,circle_incharge,director_general,additional_director')->group(function () {
        Route::get('/officers/{user}/caseload', [OfficerHandoverController::class, 'caseload']);
        Route::post('/officers/{user}/handover', [OfficerHandoverController::class, 'executeHandover']);
    });

    Route::middleware('role:admin,ad_administration,circle_incharge,director_general')->group(function () {
        Route::get('/dsr-reports', [DsrReportController::class, 'index']);
        Route::post('/dsr-reports/preview', [DsrReportController::class, 'preview']);
        Route::post('/dsr-reports', [DsrReportController::class, 'store'])->middleware('role:admin,ad_administration');
        Route::get('/dsr-reports/{dsrReport}', [DsrReportController::class, 'show']);
        Route::put('/dsr-reports/{dsrReport}', [DsrReportController::class, 'update'])->middleware('role:admin,ad_administration');
        Route::post('/dsr-reports/{dsrReport}/submit-ci', [DsrReportController::class, 'submitToCi'])->middleware('role:admin,ad_administration');
        Route::post('/dsr-reports/{dsrReport}/approve-ci', [DsrReportController::class, 'approveCi'])->middleware('role:admin,circle_incharge');
        Route::post('/dsr-reports/{dsrReport}/send-back', [DsrReportController::class, 'sendBack'])->middleware('role:admin,circle_incharge');
        Route::post('/dsr-reports/{dsrReport}/forward-hq', [DsrReportController::class, 'forwardToHq'])->middleware('role:admin,circle_incharge');
        Route::post('/dsr-reports/{dsrReport}/ack-hq', [DsrReportController::class, 'acknowledgeHq'])->middleware('role:admin,director_general');
        Route::get('/dsr-reports/{dsrReport}/export', [DsrReportController::class, 'export']);

        Route::get('/do-letters', [DoLetterController::class, 'index']);
        Route::post('/do-letters/preview', [DoLetterController::class, 'preview']);
        Route::post('/do-letters', [DoLetterController::class, 'store'])->middleware('role:admin,ad_administration');
        Route::get('/do-letters/{doLetter}', [DoLetterController::class, 'show']);
        Route::put('/do-letters/{doLetter}', [DoLetterController::class, 'update'])->middleware('role:admin,ad_administration');
        Route::post('/do-letters/{doLetter}/submit-ci', [DoLetterController::class, 'submitToCi'])->middleware('role:admin,ad_administration');
        Route::post('/do-letters/{doLetter}/approve-ci', [DoLetterController::class, 'approveCi'])->middleware('role:admin,circle_incharge');
        Route::post('/do-letters/{doLetter}/send-back', [DoLetterController::class, 'sendBack'])->middleware('role:admin,circle_incharge');
        Route::post('/do-letters/{doLetter}/forward-hq', [DoLetterController::class, 'forwardToHq'])->middleware('role:admin,circle_incharge');
        Route::post('/do-letters/{doLetter}/ack-hq', [DoLetterController::class, 'acknowledgeHq'])->middleware('role:admin,director_general');
        Route::get('/do-letters/{doLetter}/export', [DoLetterController::class, 'export']);
    });

    // Complaints
    Route::get('/complaints', [ComplaintController::class, 'index'])->name('api.complaints.index');
    Route::get('/complaints/search', [ComplaintController::class, 'search'])->name('api.complaints.search');
    Route::post('/complaints/bulk-action', [ComplaintController::class, 'bulkAction'])
        ->middleware('role:admin,circle_incharge');
    Route::post('/complaints', [ComplaintController::class, 'store'])
        ->middleware('role:operator,admin,circle_incharge,director_general')
        ->name('api.complaints.store');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('api.complaints.show');
    Route::put('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('api.complaints.update');
    Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('api.complaints.destroy');
    Route::get('/complaints/{complaint}/slip', [ComplaintController::class, 'slip'])->name('api.complaints.slip');
    Route::get('/complaints/{complaint}/report', [ComplaintController::class, 'report'])->name('api.complaints.report');
    Route::post('/complaints/{complaint}/scrutiny', [ComplaintController::class, 'scrutiny'])
        ->middleware('role:admin,circle_incharge');
    Route::post('/complaints/{complaint}/direct-assign', [ComplaintController::class, 'directAssign'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::post('/complaints/{complaint}/notify-complainant', [ComplaintController::class, 'notifyComplainant'])
        ->middleware('role:operator,admin,circle_incharge');

    Route::get('/complaint-pdf-imports', [ComplaintPdfImportController::class, 'index']);
    Route::get('/complaint-pdf-imports/stats', [ComplaintPdfImportController::class, 'stats']);
    Route::get('/complaint-pdf-imports/capabilities', [ComplaintPdfImportController::class, 'capabilities']);
    Route::post('/complaint-pdf-imports', [ComplaintPdfImportController::class, 'store'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::post('/complaint-pdf-imports/preview', [ComplaintPdfImportController::class, 'preview'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::post('/complaint-pdf-imports/render-page', [ComplaintPdfImportController::class, 'renderPage'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::get('/complaint-pdf-imports/{complaintPdfImport}', [ComplaintPdfImportController::class, 'show']);
    Route::post('/complaint-pdf-imports/{complaintPdfImport}/process', [ComplaintPdfImportController::class, 'process'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::post('/complaint-pdf-imports/{complaintPdfImport}/extract', [ComplaintPdfImportController::class, 'extract'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::post('/complaint-pdf-imports/{complaintPdfImport}/apply', [ComplaintPdfImportController::class, 'apply'])
        ->middleware('role:operator,admin,circle_incharge');

    Route::post('/adp/extract', [\App\Http\Controllers\AdpApplyController::class, 'extract'])
        ->middleware('role:operator,admin,circle_incharge');
    Route::post('/adp/apply', [\App\Http\Controllers\AdpApplyController::class, 'apply'])
        ->middleware('role:operator,admin,circle_incharge');

    // Verifications — SPECIFIC routes FIRST, then wildcard {verification} routes
    Route::get('/verifications', [VerificationController::class, 'index'])->name('api.verifications.index');
    Route::post('/verifications', [VerificationController::class, 'store'])
        ->middleware('role:operator,admin,circle_incharge')
        ->name('api.verifications.store');
    Route::get('/verifications/stats', [VerificationController::class, 'stats']);
    Route::get('/verifications/reports-list', [VerificationController::class, 'listReports']);
    Route::post('/verifications/reports/bulk-delete', [VerificationController::class, 'bulkDestroyReports'])
        ->middleware('role:admin');
    Route::get('/verifications/reports/{report}', [VerificationController::class, 'showReport']);
    Route::put('/verifications/reports/{report}', [VerificationController::class, 'updateReport']);
    Route::post('/verifications/reports/{report}', [VerificationController::class, 'updateReport']);
    Route::delete('/verifications/reports/{report}', [VerificationController::class, 'destroyReport'])
        ->middleware('role:admin');
    Route::post('/verifications/reports', [VerificationController::class, 'storeReport']);
Route::post('/verifications/bulk-close', [VerificationController::class, 'bulkClose'])
        ->middleware('role:admin,circle_incharge,verification_officer');
Route::post('/verifications/bulk-action', [VerificationController::class, 'bulkAction'])
        ->middleware('role:admin,circle_incharge,verification_officer');
    Route::get('/verifications/{verification}', [VerificationController::class, 'show'])->name('api.verifications.show');
    Route::get('/verifications/{verification}/officer-history', [OfficerAssignmentHistoryController::class, 'forVerification']);
    Route::put('/verifications/{verification}', [VerificationController::class, 'update'])->name('api.verifications.update');
    Route::delete('/verifications/{verification}', [VerificationController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('api.verifications.destroy');
    Route::post('/verifications/{verification}/submit-report', [VerificationController::class, 'submitReport']);
    Route::post('/verifications/{verification}/notify-complainant', [VerificationController::class, 'notifyComplainant']);
    Route::post('/verifications/{verification}/approve', [VerificationController::class, 'approve'])
        ->middleware('role:admin,circle_incharge');
    Route::post('/verifications/{verification}/change-officer', [VerificationController::class, 'changeOfficer'])
        ->middleware('role:admin,circle_incharge');

    // Enquiries — Verification Officer has no access (flowchart: separate Enquiry stage)
    Route::get('/enquiries', [EnquiryController::class, 'index'])->name('api.enquiries.index');
    Route::get('/enquiries/stats', [EnquiryController::class, 'stats']);
    Route::post('/enquiries', [EnquiryController::class, 'store'])
        ->middleware('role:admin,circle_incharge,operator,reader_branch,moharrar,enquiry_officer,director_general,ad_legal,dd_legal,additional_director')
        ->name('api.enquiries.store');
    Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('api.enquiries.show');
    Route::get('/enquiries/{enquiry}/officer-history', [OfficerAssignmentHistoryController::class, 'forEnquiry']);
    Route::put('/enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('api.enquiries.update');
    Route::delete('/enquiries/{enquiry}', [EnquiryController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('api.enquiries.destroy');
    Route::get('/enquiries/{enquiry}/notice-print', [EnquiryController::class, 'noticePrint'])->name('api.enquiries.notice-print');
    Route::get('/enquiries/{enquiry}/diary-print', [EnquiryController::class, 'diaryPrint'])->name('api.enquiries.diary-print');
    Route::get('/enquiries/{enquiry}/cfr-print', [EnquiryController::class, 'cfrPrint'])->name('api.enquiries.cfr-print');
    Route::get('/enquiries/{enquiry}/forensic-request-print', [EnquiryController::class, 'forensicRequestPrint'])->name('api.enquiries.forensic-request-print');
    Route::get('/enquiries/{enquiry}/raid-permission-print', [EnquiryController::class, 'raidPermissionPrint'])->name('api.enquiries.raid-permission-print');
    Route::get('/enquiries/{enquiry}/search-warrant-print', [EnquiryController::class, 'searchWarrantPrint'])->name('api.enquiries.search-warrant-print');
    Route::get('/enquiries/{enquiry}/arrest-warrant-print', [EnquiryController::class, 'arrestWarrantPrint'])->name('api.enquiries.arrest-warrant-print');
    Route::get('/enquiries/{enquiry}/account-opening-print', [EnquiryController::class, 'accountOpeningPrint'])->name('api.enquiries.account-opening-print');
    Route::get('/documents/{type}/{id}/qr', [DocumentVerifyController::class, 'qr']);
    Route::post('/enquiries/{enquiry}/assign', [EnquiryController::class, 'assign'])
        ->middleware('role:admin,circle_incharge');
    Route::post('/enquiries/{enquiry}/submit-cfr', [EnquiryController::class, 'submitCfr'])
        ->middleware('role:admin,circle_incharge,enquiry_officer');
    Route::post('/enquiries/{enquiry}/approve', [EnquiryController::class, 'approve'])
        ->middleware('role:admin,circle_incharge,ad_legal,dd_legal,additional_director,director_general');
    Route::post('/enquiries/{enquiry}/register-case', [EnquiryController::class, 'registerCase'])
        ->middleware('role:admin,circle_incharge,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::post('/enquiries/{enquiry}/change-officer', [EnquiryController::class, 'changeOfficer'])
        ->middleware('role:admin,circle_incharge');

    // DAC Cases
    Route::get('/cases', [CaseFileController::class, 'index'])->name('api.cases.index');
    Route::post('/cases', [CaseFileController::class, 'store'])->name('api.cases.store');
    Route::get('/cases/{caseFile}', [CaseFileController::class, 'show'])->name('api.cases.show');
    Route::get('/cases/{caseFile}/officer-history', [OfficerAssignmentHistoryController::class, 'forCase']);
    Route::get('/cases/{caseFile}/raid-permission-print', [CaseFileController::class, 'raidPermissionPrint']);
    Route::get('/cases/{caseFile}/search-warrant-print', [CaseFileController::class, 'searchWarrantPrint']);
    Route::get('/cases/{caseFile}/arrest-warrant-print', [CaseFileController::class, 'arrestWarrantPrint']);
    Route::get('/cases/{caseFile}/forensic-request-print', [CaseFileController::class, 'forensicRequestPrint']);
    Route::put('/cases/{caseFile}', [CaseFileController::class, 'update'])->name('api.cases.update');
    Route::delete('/cases/{caseFile}', [CaseFileController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('api.cases.destroy');
    Route::post('/cases/{caseFile}/assign-officer', [CaseFileController::class, 'assignOfficer'])
        ->middleware('role:admin,circle_incharge');
    Route::post('/cases/{caseFile}/submit-cfr', [CaseFileController::class, 'submitCfr'])
        ->middleware('role:admin,circle_incharge,investigation_officer,enquiry_officer,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::post('/cases/{caseFile}/approve', [CaseFileController::class, 'approve'])
        ->middleware('role:admin,circle_incharge,ad_legal,dd_legal,additional_director,director_general');

    // Court Cases — restricted to the legal chain / case-management roles.
    // (Previously these carried no role gate: any authenticated user could file
    //  a court case or record a verdict. Locked to the roles that run court work.)
    Route::get('/court-cases', [CourtCaseController::class, 'index'])->name('api.court-cases.index');
    Route::post('/court-cases', [CourtCaseController::class, 'store'])->name('api.court-cases.store')
        ->middleware('role:admin,circle_incharge,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::get('/court-cases/{courtCase}', [CourtCaseController::class, 'show'])->name('api.court-cases.show');
    Route::put('/court-cases/{courtCase}', [CourtCaseController::class, 'update'])->name('api.court-cases.update')
        ->middleware('role:admin,circle_incharge,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::delete('/court-cases/{courtCase}', [CourtCaseController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('api.court-cases.destroy');
    Route::post('/court-cases/{courtCase}/verdict', [CourtCaseController::class, 'verdict'])->name('api.court-cases.verdict')
        ->middleware('role:admin,circle_incharge,ad_legal,dd_legal,additional_director,director_general');
    Route::post('/court-cases/{courtCase}/forward-report', [CourtCaseController::class, 'forwardReport'])->name('api.court-cases.forward-report')
        ->middleware('role:admin,circle_incharge,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::get('/court-cases/{courtCase}/hearings', [\App\Http\Controllers\HearingController::class, 'index']);
    Route::post('/court-cases/{courtCase}/hearings', [\App\Http\Controllers\HearingController::class, 'store'])
        ->middleware('role:admin,circle_incharge,moharrar,ad_legal,dd_legal,additional_director,director_general');

    // Immutable audit ledger (admin / DG) — view + integrity verification
    Route::get('/audit-ledger', [AuditLedgerController::class, 'index'])
        ->middleware('role:admin,director_general');
    Route::get('/audit-ledger/verify', [AuditLedgerController::class, 'verify'])
        ->middleware('role:admin,director_general');

    // Near-real-time change feed (any authenticated user) — one cheap poll tells
    // the SPA which modules changed since `since`, so it refetches only those.
    Route::get('/sync/pulse', [SyncController::class, 'pulse'])
        ->middleware('throttle:dashboard');

    // Admin-configurable approval requirements (mandatory CI approval vs open)
    Route::get('/approval-settings', [ApprovalSettingController::class, 'index'])
        ->middleware('role:admin,director_general,circle_incharge');
    Route::put('/approval-settings/{actionKey}', [ApprovalSettingController::class, 'update'])
        ->middleware('role:admin');

    // Warrant / proclamation / attachment approval requests (officer → CI)
    Route::get('/warrant-requests', [WarrantRequestController::class, 'index']);
    Route::post('/warrant-requests', [WarrantRequestController::class, 'store']);
    Route::post('/warrant-requests/{warrantRequest}/approve', [WarrantRequestController::class, 'approve'])
        ->middleware('role:admin,circle_incharge');
    Route::post('/warrant-requests/{warrantRequest}/reject', [WarrantRequestController::class, 'reject'])
        ->middleware('role:admin,circle_incharge');

    // Proclamation (u/s 87 CrPC) + Property Attachment (u/s 88 CrPC) on a case
    Route::get('/cases/{caseFile}/proclamations', [ProclamationController::class, 'index']);
    Route::post('/cases/{caseFile}/proclamations', [ProclamationController::class, 'store'])
        ->middleware('role:admin,circle_incharge,investigation_officer,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::get('/proclamations/{proclamation}/print', [ProclamationController::class, 'printProclamation']);
    Route::post('/proclamations/{proclamation}/attachment', [ProclamationController::class, 'storeAttachment'])
        ->middleware('role:admin,circle_incharge,investigation_officer,moharrar,ad_legal,dd_legal,additional_director,director_general');
    Route::get('/attachments/{attachment}/print', [ProclamationController::class, 'printAttachment']);

    // Offence Types - everyone can view, only admin can modify
    Route::get('/offence-types', [OffenceTypeController::class, 'index'])->name('api.offence-types.index');
    Route::post('/offence-types', [OffenceTypeController::class, 'store'])->name('api.offence-types.store')
        ->middleware('role:admin');
    Route::put('/offence-types/{offenceType}', [OffenceTypeController::class, 'update'])->name('api.offence-types.update')
        ->middleware('role:admin');
    Route::delete('/offence-types/{offenceType}', [OffenceTypeController::class, 'destroy'])->name('api.offence-types.destroy')
        ->middleware('role:admin');

    // Investigation Officers - admin/circle_incharge can view, only admin can modify
    Route::get('/investigation-officers', [InvestigationOfficerController::class, 'index'])->name('api.investigation-officers.index')
        ->middleware('role:admin,circle_incharge,director_general');
    Route::post('/investigation-officers', [InvestigationOfficerController::class, 'store'])->name('api.investigation-officers.store')
        ->middleware('role:admin');
    Route::get('/investigation-officers/{investigationOfficer}', [InvestigationOfficerController::class, 'show'])->name('api.investigation-officers.show')
        ->middleware('role:admin');
    Route::put('/investigation-officers/{investigationOfficer}', [InvestigationOfficerController::class, 'update'])->name('api.investigation-officers.update')
        ->middleware('role:admin');
    Route::delete('/investigation-officers/{investigationOfficer}', [InvestigationOfficerController::class, 'destroy'])->name('api.investigation-officers.destroy')
        ->middleware('role:admin');
    Route::post('/investigation-officers/{investigation_officer}/grant-access', [InvestigationOfficerController::class, 'grantAccess'])
        ->middleware('role:admin');
    Route::post('/investigation-officers/{investigation_officer}/revoke-access', [InvestigationOfficerController::class, 'revokeAccess'])
        ->middleware('role:admin');
    Route::post('/investigation-officers/{investigation_officer}/reset-password', [InvestigationOfficerController::class, 'resetPassword'])
        ->middleware('role:admin');
    Route::post('/investigation-officers/{investigation_officer}/send-otp', [InvestigationOfficerController::class, 'sendOtp'])
        ->middleware('role:admin');
    Route::post('/investigation-officers/{investigation_officer}/verify-otp', [InvestigationOfficerController::class, 'verifyOtp'])
        ->middleware('role:admin');

    // User management — admin + director general + circle incharge (circle-scoped)
    Route::get('/users', [UserController::class, 'index'])->name('api.users.index')
        ->middleware('role:admin,director_general,circle_incharge');
    Route::post('/users', [UserController::class, 'store'])->name('api.users.store')
        ->middleware('role:admin,director_general,circle_incharge');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('api.users.show')
        ->middleware('role:admin,director_general,circle_incharge');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('api.users.update')
        ->middleware('role:admin,director_general,circle_incharge');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('api.users.destroy')
        ->middleware('role:admin,circle_incharge');
    Route::post('/users/{user}/grant-permission', [UserController::class, 'grantPermission'])
        ->middleware('role:admin');
    Route::post('/users/{user}/revoke-permission', [UserController::class, 'revokePermission'])
        ->middleware('role:admin');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->middleware('role:admin,director_general,circle_incharge');

    // Login History — admin + director general
    Route::get('/login-history', [LoginHistoryController::class, 'index'])
        ->middleware('role:admin,director_general');
    Route::get('/login-history/stats', [LoginHistoryController::class, 'stats'])
        ->middleware('role:admin,director_general');
    Route::get('/login-history/{user}', [LoginHistoryController::class, 'userHistory'])
        ->middleware('role:admin,director_general');

    // Circle management — admin + director general
    Route::get('/circles', function () {
        return response()->json(\App\Models\Circle::with('zone')->orderBy('name')->get());
    });
    Route::post('/circles', function (\Illuminate\Http\Request $req) {
        $data = $req->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:circles,code',
            'zone_id' => 'nullable|exists:zones,id',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:100',
            'jurisdiction' => 'nullable|string',
        ]);
        \Illuminate\Support\Facades\Cache::forget('lookup_all_circles');
        return response()->json(\App\Models\Circle::create($data), 201);
    })->middleware('role:admin,director_general');
    Route::put('/circles/{circle}', function (\Illuminate\Http\Request $req, \App\Models\Circle $circle) {
        $data = $req->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:circles,code,'.$circle->id,
            'zone_id' => 'nullable|exists:zones,id',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:100',
            'jurisdiction' => 'nullable|string',
        ]);
        $circle->update($data);
        \Illuminate\Support\Facades\Cache::forget('lookup_all_circles');
        return response()->json($circle->fresh()->load('zone'));
    })->middleware('role:admin,director_general');
    Route::delete('/circles/{circle}', function (\App\Models\Circle $circle) {
        $circle->delete();
        \Illuminate\Support\Facades\Cache::forget('lookup_all_circles');
        return response()->json(['message' => 'Circle deleted']);
    })->middleware('role:admin');

    // Lookups
    Route::get('/lookup/professions', [LookupController::class, 'professions']);
    Route::get('/lookup/received-via', [LookupController::class, 'receivedVia']);
    Route::get('/lookup/received-from', [LookupController::class, 'receivedFrom']);
    Route::get('/lookup/cmu-options', [LookupController::class, 'cmuOptions']);
    Route::get('/lookup/offence-types', [LookupController::class, 'offenceTypes']);
    Route::get('/lookup/roles', function () {
        return response()->json(\Illuminate\Support\Facades\Cache::remember('lookup_roles', 3600, function () {
            return \Spatie\Permission\Models\Role::orderBy('name')->pluck('name');
        }));
    });
    Route::get('/lookup/circles', function () {
        return response()->json(\Illuminate\Support\Facades\Cache::remember('lookup_all_circles', 1800, function () {
            return \App\Models\Circle::with('zone:id,name,code')->orderBy('name')->get(['id', 'name', 'code', 'zone_id', 'address', 'phone', 'jurisdiction']);
        }));
    });
    Route::get('/lookup/zones', function () {
        return response()->json(\Illuminate\Support\Facades\Cache::remember('lookup_all_zones', 3600, function () {
            return \App\Models\Zone::orderBy('name')->get(['id', 'name', 'code']);
        }));
    });
    Route::get('/lookup/enquiry-officers', [LookupController::class, 'enquiryOfficers']);
    Route::get('/lookup/legal-officers', [LookupController::class, 'legalOfficers']);
    Route::get('/lookup/verification-officers', [LookupController::class, 'verificationOfficers']);
    Route::get('/lookup/investigation-officers', [LookupController::class, 'investigationOfficers']);
    Route::get('/lookup/circle-incharges', [LookupController::class, 'circleIncharges']);

    // Reference data — Laws, Rules, SOPs, User Manuals (all authenticated users can read)
    Route::get('/laws', [ReferenceController::class, 'lawsIndex']);
    Route::get('/laws/{law}', [ReferenceController::class, 'lawsShow']);
    Route::post('/laws', [ReferenceController::class, 'lawsStore'])->middleware('role:admin,circle_incharge,director_general');
    Route::put('/laws/{law}', [ReferenceController::class, 'lawsUpdate'])->middleware('role:admin,circle_incharge,director_general');
    Route::delete('/laws/{law}', [ReferenceController::class, 'lawsDestroy'])->middleware('role:admin,circle_incharge,director_general');
    Route::get('/rules', [ReferenceController::class, 'rulesIndex']);
    Route::get('/rules/{rule}', [ReferenceController::class, 'rulesShow']);
    Route::post('/rules', [ReferenceController::class, 'rulesStore'])->middleware('role:admin,circle_incharge,director_general');
    Route::put('/rules/{rule}', [ReferenceController::class, 'rulesUpdate'])->middleware('role:admin,circle_incharge,director_general');
    Route::delete('/rules/{rule}', [ReferenceController::class, 'rulesDestroy'])->middleware('role:admin,circle_incharge,director_general');
    Route::get('/sops', [ReferenceController::class, 'sopsIndex']);
    Route::get('/sops/{sop}', [ReferenceController::class, 'sopsShow']);
    Route::post('/sops', [ReferenceController::class, 'sopsStore'])->middleware('role:admin,circle_incharge,director_general');
    Route::put('/sops/{sop}', [ReferenceController::class, 'sopsUpdate'])->middleware('role:admin,circle_incharge,director_general');
    Route::delete('/sops/{sop}', [ReferenceController::class, 'sopsDestroy'])->middleware('role:admin,circle_incharge,director_general');
    Route::get('/user-manuals', [ReferenceController::class, 'manualsIndex']);
    Route::get('/user-manuals/{manual}', [ReferenceController::class, 'manualsShow']);
    Route::post('/user-manuals', [ReferenceController::class, 'manualsStore'])->middleware('role:admin,circle_incharge,director_general');
    Route::put('/user-manuals/{manual}', [ReferenceController::class, 'manualsUpdate'])->middleware('role:admin,circle_incharge,director_general');
    Route::delete('/user-manuals/{manual}', [ReferenceController::class, 'manualsDestroy'])->middleware('role:admin,circle_incharge,director_general');

    // Forensic portal — isolated from the main NCCIA modules
    Route::get('/forensic/stats', [ForensicUserController::class, 'stats'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic');
    Route::get('/forensic/request-stats', [ForensicRequestController::class, 'stats'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic');
    Route::get('/forensic/team-officers', [ForensicRequestController::class, 'teamOfficers'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic');
    Route::get('/forensic/requests', [ForensicRequestController::class, 'index'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,circle_incharge,enquiry_officer,investigation_officer,director_general');
    Route::get('/forensic/requests/{forensicRequest}', [ForensicRequestController::class, 'show'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,circle_incharge,enquiry_officer,investigation_officer,director_general,moharrar,operator');
    Route::post('/forensic/requests/{forensicRequest}/forward-to-forensic', [ForensicRequestController::class, 'forwardToForensic'])
        ->middleware('role:admin,circle_incharge,ad_legal,dd_legal,additional_director,director_general');
    Route::post('/forensic/requests/{forensicRequest}/send-back', [ForensicRequestController::class, 'sendBackToEo'])
        ->middleware('role:admin,circle_incharge,ad_legal,dd_legal,additional_director,director_general,admin_forensic,dd_forensic,ad_forensic');
    Route::post('/forensic/requests/{forensicRequest}/send-back-to-dd', [ForensicRequestController::class, 'sendBackToDd'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,forensic_team');
    Route::post('/forensic/requests/{forensicRequest}/send-back-to-ci', [ForensicRequestController::class, 'sendBackToCi'])
        ->middleware('role:admin,admin_forensic,dd_forensic');
    Route::post('/forensic/requests/{forensicRequest}/assign', [ForensicRequestController::class, 'assign'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic');
    Route::post('/forensic/requests/{forensicRequest}/findings', [ForensicRequestController::class, 'updateFindings'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,forensic_team');
    Route::post('/forensic/requests/{forensicRequest}/status', [ForensicRequestController::class, 'updateStatus'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,forensic_team');
    Route::post('/forensic/requests/{forensicRequest}/items/{item}/condition', [ForensicRequestController::class, 'updateItemCondition'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,forensic_team');
    Route::post('/forensic/requests/{forensicRequest}/submit-to-ad', [ForensicRequestController::class, 'submitToAd'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic,forensic_team');
    Route::post('/forensic/requests/{forensicRequest}/mark-ready', [ForensicRequestController::class, 'markReady'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic');
    Route::post('/forensic/requests/{forensicRequest}/hand-over', [ForensicRequestController::class, 'handOver'])
        ->middleware('role:admin,admin_forensic,dd_forensic,ad_forensic');

    // Main app: submit seizure + EO lookup by report code
    Route::post('/forensic-requests', [ForensicRequestController::class, 'store']);
    Route::get('/forensic-requests', [ForensicRequestController::class, 'linkedIndex']);
    // Lookup-by-code is enumerable (sequential FREP codes). Restrict to the officer
    // roles that legitimately attach a report, and throttle to stop code-walking.
    Route::post('/forensic-requests/lookup', [ForensicRequestController::class, 'lookupByCode'])
        ->middleware(['role:admin,circle_incharge,operator,moharrar,enquiry_officer,investigation_officer,ad_legal,dd_legal,additional_director,director_general,admin_forensic,dd_forensic,ad_forensic', 'throttle:15,1']);

    Route::get('/forensic/roles', [ForensicUserController::class, 'rolesList'])
        ->middleware('role:admin_forensic');
    Route::get('/forensic/users', [ForensicUserController::class, 'index'])
        ->middleware('role:admin_forensic');
    Route::get('/forensic/users/{user}', [ForensicUserController::class, 'show'])
        ->middleware('role:admin_forensic');
    Route::post('/forensic/users', [ForensicUserController::class, 'store'])
        ->middleware('role:admin_forensic');
    Route::put('/forensic/users/{user}', [ForensicUserController::class, 'update'])
        ->middleware('role:admin_forensic');
    Route::delete('/forensic/users/{user}', [ForensicUserController::class, 'destroy'])
        ->middleware('role:admin_forensic');
    Route::post('/forensic/users/{user}/reset-password', [ForensicUserController::class, 'resetPassword'])
        ->middleware('role:admin_forensic');
    Route::post('/forensic/users/{user}/generate-password', [ForensicUserController::class, 'generatePassword'])
        ->middleware('role:admin_forensic');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/pending-tasks', [NotificationController::class, 'pendingTasks']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    // Internal messaging
    Route::get('/messages/contacts', [MessageController::class, 'contacts']);
    Route::get('/messages/conversations', [MessageController::class, 'conversations']);
    Route::get('/messages/conversations/{user}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
    Route::post('/messages/read-all', [MessageController::class, 'markAllRead']);

    // Case-specific collaboration chat
    Route::get('/messages/cases', [MessageController::class, 'caseConversations']);
    Route::get('/messages/case-thread', [MessageController::class, 'caseThread']);
    Route::get('/messages/cases/{caseId}/thread', [MessageController::class, 'caseThread']);
    Route::post('/messages/case-message', [MessageController::class, 'storeCaseMessage']);
    Route::post('/messages/cases/{caseId}/send', [MessageController::class, 'storeCaseMessage']);

    // SMS
    Route::get('/sms', [SmsController::class, 'index'])
        ->middleware('role:admin,director_general,circle_incharge,ad_administration');
    Route::post('/sms', [SmsController::class, 'store'])
        ->middleware('role:admin,director_general,circle_incharge');
    Route::get('/sms/templates', [SmsController::class, 'templates'])
        ->middleware('role:admin,director_general,circle_incharge');
});
