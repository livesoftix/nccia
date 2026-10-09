<?php

namespace App\Console\Commands;

use App\Models\Arrest;
use App\Models\CaseActivity;
use App\Models\CaseApproval;
use App\Models\CaseFile;
use App\Models\CaseLegalOpinion;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\CourtHearing;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\EnquiryLegalOpinion;
use App\Models\EnquiryNotice;
use App\Models\EnquiryWitness;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerificationReport;
use App\Services\EnquiryNumberGenerator;
use App\Services\FirNumberGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds full nested details for Enquiry / Report / Case / Court into DB.
 * php artisan nccia:enrich-test
 * php artisan nccia:enrich-test --complaint=246
 */
class EnrichDetailsTest extends Command
{
    protected $signature = 'nccia:enrich-test {--complaint= : Existing complaint id to resume}';

    protected $description = 'Create/enrich enquiry activities, notices, witnesses, verification report, case actions/arrests, court hearing in DB';

    public function handle(): int
    {
        $marker = 'ENRICH-' . now()->format('YmdHis');
        $admin = User::role('admin')->first() ?? User::where('email', 'admin@admin.com')->first();
        $vo = User::role('verification_officer')->first();
        $eo = User::role('enquiry_officer')->first();
        $io = User::role('investigation_officer')->first() ?? User::where('email', 'like', '%@%')->whereHas('roles', fn ($q) => $q->where('name', 'investigation_officer'))->first();
        $ci = User::role('circle_incharge')->first() ?? $admin;

        if (!$admin || !$vo || !$eo) {
            $this->error('Need admin, verification_officer, enquiry_officer users');

            return self::FAILURE;
        }

        $ioId = $io?->id ?? $admin->id;

        try {
            $result = DB::transaction(function () use ($marker, $admin, $vo, $eo, $ci, $ioId) {
                $complaintId = $this->option('complaint');
                if ($complaintId) {
                    $complaint = Complaint::findOrFail($complaintId);
                } else {
                    $complaint = Complaint::create([
                        'complainant_name' => "Enrich $marker",
                        'cnic' => '35202-7654321-1',
                        'contact_no' => '03009876543',
                        'address' => 'Lahore Enrich',
                        'report_date' => now()->toDateString(),
                        'diary_no' => "DY-$marker",
                        'received_via' => 'Walk-in',
                        'received_from' => 'Public',
                        'offence_type' => 'Cyber Fraud',
                        'occurrence_date' => now()->subDays(3)->toDateString(),
                        'description' => "$marker enrich details test",
                        'operator_name' => $admin->name,
                        'operator_designation' => 'Admin',
                        'operator_id' => $admin->id,
                        'user_id' => $admin->id,
                        'circle_id' => $vo->circle_id,
                        'entry_time' => now(),
                        'scrutiny_result' => 'complete',
                        'status' => 'complete',
                        'tracking_no' => 'ENR-' . substr(preg_replace('/\W/', '', $marker), 0, 12),
                    ]);
                }

                $verification = Verification::firstOrCreate(
                    ['complaint_id' => $complaint->id],
                    [
                        'verification_officer_id' => $vo->id,
                        'assigned_by' => $admin->id,
                        'priority_type' => 'normal',
                        'status' => 'submitted',
                        'recommendation' => 'enquiry_registration',
                        'report_text' => "$marker VO report",
                        'assigned_at' => now(),
                        'submitted_at' => now(),
                        'completed_at' => now(),
                    ]
                );

                $report = VerificationReport::create([
                    'complaint_id' => $complaint->id,
                    'tracking_no' => $complaint->tracking_no,
                    'registration_at' => now(),
                    'assignment_date' => now(),
                    'verification_date' => now(),
                    'victim_name' => "Victim $marker",
                    'victim_father_name' => 'Father',
                    'victim_occupation' => 'Business',
                    'victim_gender' => 'male',
                    'victim_cnic' => '35202-7654321-1',
                    'victim_country_code' => '+92',
                    'victim_phone' => '03009876543',
                    'crime_category' => $complaint->offence_type,
                    'crime_description' => "$marker verification report body",
                    'city' => 'Lahore',
                    'accused_known' => true,
                    'accused' => [[
                        'name' => 'Accused One',
                        'cnic' => '35202-1111111-1',
                        'phone' => '03001112233',
                        'address' => 'Accused Address',
                        'nationality' => 'Pakistani',
                    ]],
                    'recommendation_short' => 'Recommend enquiry',
                    'recommendation_full' => "$marker full recommendation",
                    'comments' => "$marker comments",
                    'recommendation' => 'enquiry_registration',
                    'created_by' => $vo->id,
                ]);

                if (!$complaint->enquiry_id) {
                    $circleCode = $complaint->circle?->code;
                    $enquiry = Enquiry::create([
                        'complaint_id' => $complaint->id,
                        'enquiry_number' => app(EnquiryNumberGenerator::class)->generate($circleCode),
                        'status' => 'assigned',
                        'enquiry_officer_id' => $eo->id,
                        'assignment_date' => now(),
                        'reg_date' => now(),
                        'technical_report' => "$marker Technical report details",
                        'forensic_report' => "$marker Forensic report details",
                        'cfr_summary' => "$marker CFR notes",
                        'recommendation' => 'convert_to_case',
                    ]);
                    $complaint->update([
                        'status' => 'enquiry_registered',
                        'final_status' => 'enquiry_registered',
                        'enquiry_id' => $enquiry->id,
                    ]);
                    $verification->update(['status' => 'approved', 'approved_at' => now()]);
                } else {
                    $enquiry = Enquiry::findOrFail($complaint->enquiry_id);
                    $enquiry->update([
                        'enquiry_officer_id' => $enquiry->enquiry_officer_id ?: $eo->id,
                        'technical_report' => "$marker Technical report details",
                        'forensic_report' => "$marker Forensic report details",
                    ]);
                }

                foreach ([
                    ['dac_request', 'DAC Request'],
                    ['bank_record', 'Bank Record Obtained'],
                    ['search_seize', 'Search and Seize'],
                    ['notices', 'Notices Issued'],
                    ['diaries', 'Diaries Maintained'],
                    ['seizures', 'Seizures Made'],
                    ['recoveries', 'Recoveries Effected'],
                ] as [$type, $label]) {
                    EnquiryActivity::create([
                        'enquiry_id' => $enquiry->id,
                        'type' => $type,
                        'description' => "$marker $label",
                        'activity_date' => now()->toDateString(),
                        'created_by' => $eo->id,
                    ]);
                }

                EnquiryWitness::create([
                    'enquiry_id' => $enquiry->id,
                    'name' => "Witness Ali $marker",
                    'cnic' => '35202-2222222-2',
                    'nationality' => 'Pakistani',
                    'address' => 'Witness Address 1',
                ]);
                EnquiryWitness::create([
                    'enquiry_id' => $enquiry->id,
                    'name' => "Witness Sara $marker",
                    'cnic' => '35202-3333333-3',
                    'nationality' => 'Pakistani',
                    'address' => 'Witness Address 2',
                ]);

                EnquiryNotice::create([
                    'enquiry_id' => $enquiry->id,
                    'notice_number' => "N-$marker-1",
                    'notice_type' => 'appearance',
                    'receiver_name' => 'Accused One',
                    'person_type' => 'accused',
                    'notice_via' => 'post',
                    'address' => 'Accused Address',
                    'phone' => '03001112233',
                    'notice_date' => now()->toDateString(),
                    'description' => "$marker appearance notice",
                    'status' => 'issued',
                    'verification_token' => Str::random(32),
                ]);
                EnquiryNotice::create([
                    'enquiry_id' => $enquiry->id,
                    'notice_number' => "N-$marker-2",
                    'notice_type' => 'production',
                    'receiver_name' => 'Witness Ali',
                    'person_type' => 'witness',
                    'notice_via' => 'whatsapp',
                    'address' => 'Witness Address 1',
                    'phone' => '03002223344',
                    'notice_date' => now()->toDateString(),
                    'description' => "$marker witness notice",
                    'status' => 'served',
                    'served_at' => now(),
                    'verification_token' => Str::random(32),
                ]);

                EnquiryLegalOpinion::create([
                    'enquiry_id' => $enquiry->id,
                    'role' => 'ad_legal',
                    'opinion_text' => "$marker AD Legal - proceed",
                    'decision' => 'agree',
                    'created_by' => $admin->id,
                ]);
                EnquiryLegalOpinion::create([
                    'enquiry_id' => $enquiry->id,
                    'role' => 'dd_legal',
                    'opinion_text' => "$marker DD Legal concurs",
                    'decision' => 'agree',
                    'created_by' => $admin->id,
                ]);

                if (!$enquiry->case_file_id) {
                    $case = CaseFile::create([
                        'enquiry_id' => $enquiry->id,
                        'fir_no' => app(FirNumberGenerator::class)->generate($complaint->circle?->code),
                        'investigation_officer_id' => $ioId,
                        'status' => 'in_progress',
                    ]);
                    $enquiry->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'case_file_id' => $case->id,
                        'recommendation' => 'convert_to_case',
                    ]);
                    $complaint->update([
                        'status' => 'case_registered',
                        'final_status' => 'case_registered',
                    ]);
                } else {
                    $case = CaseFile::findOrFail($enquiry->case_file_id);
                }

                foreach ([
                    'dac_request' => 'DAC Request',
                    'mobile_record' => 'Mobile Record Obtained',
                    'bank_record' => 'Bank Record Obtained',
                    'notice' => 'Notice Issued',
                    'diary' => 'Diary Maintained',
                    'seizure' => 'Seizure Made',
                    'forensic_report' => 'Forensic Report',
                    'recovery' => 'Recovery Effected',
                    'raid' => 'Raid Conducted',
                ] as $type => $label) {
                    CaseActivity::firstOrCreate(
                        ['case_id' => $case->id, 'type' => $type],
                        [
                            'description' => "$marker $label",
                            'activity_date' => now()->toDateString(),
                            'created_by' => $ioId,
                        ]
                    );
                }

                Arrest::create([
                    'case_id' => $case->id,
                    'accused_name' => "Accused One $marker",
                    'cnic' => '35202-1111111-1',
                    'arrest_date' => now()->toDateString(),
                    'remand_details' => '14-day judicial remand',
                ]);

                CaseLegalOpinion::create([
                    'case_id' => $case->id,
                    'role' => 'ad_legal',
                    'opinion_text' => "$marker AD Legal challan ready",
                    'decision' => 'agree',
                    'created_by' => $admin->id,
                ]);
                CaseLegalOpinion::create([
                    'case_id' => $case->id,
                    'role' => 'dd_legal',
                    'opinion_text' => "$marker DD Legal approved",
                    'decision' => 'agree',
                    'created_by' => $admin->id,
                ]);
                CaseApproval::create([
                    'case_id' => $case->id,
                    'circle_incharge_id' => $ci->id,
                    'decision' => 'agree',
                    'remarks' => "$marker CI case approval",
                ]);

                $court = CourtCase::firstOrCreate(
                    ['case_id' => $case->id],
                    [
                        'court_name' => "Sessions Court $marker",
                        'judge_name' => 'Justice Enrich',
                        'filing_date' => now()->toDateString(),
                        'status' => 'filed',
                    ]
                );
                $case->update(['status' => 'in_progress']);

                $hearing = CourtHearing::create([
                    'court_case_id' => $court->id,
                    'hearing_date' => now()->toDateString(),
                    'type' => 'trial_commencement',
                    'notes' => "$marker First hearing - charges framed",
                    'next_hearing_date' => now()->addDays(14)->toDateString(),
                ]);

                return compact('complaint', 'verification', 'report', 'enquiry', 'case', 'court', 'hearing', 'marker');
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }

        $enquiry = $result['enquiry']->fresh()->load('activities', 'witnesses', 'notices', 'legalOpinions');
        $case = $result['case']->fresh()->load('activities', 'arrests', 'legalOpinions', 'approvals');
        $court = $result['court']->fresh()->load('hearings');

        $this->info('=== ENRICH DB OK ===');
        $this->table(['Entity', 'ID', 'Counts / Key'], [
            ['Complaint', $result['complaint']->id, $result['complaint']->tracking_no],
            ['VerificationReport', $result['report']->id, $result['report']->tracking_no],
            ['Enquiry', $enquiry->id, sprintf(
                'act=%d wit=%d not=%d legal=%d',
                $enquiry->activities->count(),
                $enquiry->witnesses->count(),
                $enquiry->notices->count(),
                $enquiry->legalOpinions->count()
            )],
            ['Case', $case->id, sprintf(
                '%s act=%d arr=%d legal=%d appr=%d',
                $case->fir_no,
                $case->activities->count(),
                $case->arrests->count(),
                $case->legalOpinions->count(),
                $case->approvals->count()
            )],
            ['Court', $court->id, 'hearings=' . $court->hearings->count()],
            ['Hearing', $result['hearing']->id, $result['hearing']->type],
        ]);
        $this->info('Marker: ' . $result['marker']);

        return self::SUCCESS;
    }
}
