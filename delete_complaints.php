<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Arrest;
use App\Models\CaseActivity;
use App\Models\CaseApproval;
use App\Models\CaseFile;
use App\Models\CaseLegalOpinion;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\CourtHearing;
use App\Models\CourtReport;
use App\Models\CourtVerdict;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\EnquiryApproval;
use App\Models\EnquiryLegalOpinion;
use App\Models\Verification;
use App\Models\VerificationApproval;
use App\Models\VerificationReport;
use Illuminate\Support\Facades\DB;

DB::statement('PRAGMA foreign_keys = OFF;');

VerificationReport::query()->delete();
VerificationApproval::query()->delete();
Verification::query()->delete();
EnquiryApproval::query()->delete();
EnquiryLegalOpinion::query()->delete();
EnquiryActivity::query()->delete();
Enquiry::query()->delete();
CaseApproval::query()->delete();
CaseLegalOpinion::query()->delete();
Arrest::query()->delete();
CaseActivity::query()->delete();
CaseFile::query()->delete();
CourtVerdict::query()->delete();
CourtReport::query()->delete();
CourtHearing::query()->delete();
CourtCase::query()->delete();
$deleted = Complaint::query()->delete();

DB::statement('PRAGMA foreign_keys = ON;');

echo 'Complaints deleted: ' . $deleted . PHP_EOL;
echo 'Remaining complaints: ' . Complaint::count() . PHP_EOL;
echo 'Remaining verifications: ' . Verification::count() . PHP_EOL;
echo 'Remaining enquiries: ' . Enquiry::count() . PHP_EOL;
echo 'Remaining case files: ' . CaseFile::count() . PHP_EOL;
echo 'DONE' . PHP_EOL;
