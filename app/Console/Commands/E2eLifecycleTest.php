<?php

namespace App\Console\Commands;

use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\Enquiry;
use App\Models\OffenceType;
use App\Models\User;
use App\Models\Verification;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Full lifecycle in DB: Complaint → Verification → Enquiry → Case → Court → Close.
 * Run on server: php artisan nccia:e2e-lifecycle
 */
class E2eLifecycleTest extends Command
{
    protected $signature = 'nccia:e2e-lifecycle
        {--email-suffix= : Optional suffix for unique tracking marker}
        {--cleanup : Delete the test records after verification}';

    protected $description = 'Create a full complaint→court-close lifecycle in the database and verify each stage';

    private array $checks = [];

    public function handle(): int
    {
        $marker = 'E2E-' . now()->format('YmdHis') . ($this->option('email-suffix') ?: '');
        $this->info("=== NCCIA E2E Lifecycle: {$marker} ===");

        try {
            $circle = $this->ensureCircle();
            $users = $this->ensureUsers($circle);
            $offence = OffenceType::query()->orderBy('id')->value('name') ?: 'Cyber Fraud';

            // 1) Complaint + VO assign
            $this->step('1. Complaint complete + VO assign');
            Auth::login($users['operator']);
            $complaint = $this->createComplaint($users, $circle, $offence, $marker);
            $this->assertTrue('complaint exists', Complaint::whereKey($complaint->id)->exists());
            $this->assertTrue('complaint status=complete', $complaint->fresh()->status === 'complete');
            $this->assertTrue('tracking_no set', filled($complaint->fresh()->tracking_no));

            $verification = Verification::where('complaint_id', $complaint->id)->first();
            $this->assertTrue('verification created', (bool) $verification);
            $this->assertTrue('verification assigned', $verification?->status === 'assigned');
            $this->assertTrue('VO linked', (int) $verification?->verification_officer_id === (int) $users['vo']->id);

            // 2) VO submit report
            $this->step('2. VO submit report → enquiry_registration');
            Auth::login($users['vo']);
            $verification->update([
                'status' => 'submitted',
                'report_text' => "{$marker} verification findings — recommend enquiry.",
                'recommendation' => 'enquiry_registration',
                'submitted_at' => now(),
                'completed_at' => now(),
            ]);
            $this->assertTrue('verification submitted', $verification->fresh()->status === 'submitted');

            // 3) CI approve → Enquiry
            $this->step('3. CI approve verification → Enquiry');
            Auth::login($users['ci']);
            $enquiry = DB::transaction(function () use ($verification, $complaint, $marker, $users) {
                $verification->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'recommendation' => 'enquiry_registration',
                ]);
                $verification->approvals()->create([
                    'circle_incharge_id' => $users['ci']->id,
                    'decision' => 'agree',
                    'recommendation' => 'enquiry_registration',
                    'remarks' => "{$marker} CI agreed",
                ]);

                $enquiry = Enquiry::create([
                    'complaint_id' => $complaint->id,
                    'enquiry_number' => 'ENQ-' . $marker,
                    'status' => 'registered',
                    'reg_date' => now(),
                ]);

                $complaint->update([
                    'status' => 'enquiry_registered',
                    'final_status' => 'enquiry_registered',
                    'enquiry_id' => $enquiry->id,
                ]);

                return $enquiry;
            });
            $this->assertTrue('enquiry exists', Enquiry::whereKey($enquiry->id)->exists());
            $this->assertTrue('complaint→enquiry_registered', $complaint->fresh()->status === 'enquiry_registered');

            // 4) Assign EO + CFR
            $this->step('4. Assign EO + submit CFR convert_to_case');
            Auth::login($users['ci']);
            $enquiry->update([
                'enquiry_officer_id' => $users['eo']->id,
                'status' => 'assigned',
                'assignment_date' => now()->toDateString(),
            ]);
            Auth::login($users['eo']);
            $enquiry->update([
                'status' => 'cfr_submitted',
                'cfr_summary' => "{$marker} CFR — convert to case",
                'recommendation' => 'convert_to_case',
            ]);
            $enquiry->activities()->create([
                'type' => 'cfr',
                'description' => "{$marker} CFR submitted",
                'activity_date' => now()->toDateString(),
                'created_by' => $users['eo']->id,
            ]);
            $this->assertTrue('enquiry cfr_submitted', $enquiry->fresh()->status === 'cfr_submitted');

            // 5) CI approve enquiry → Case
            $this->step('5. CI approve enquiry → Case');
            Auth::login($users['ci']);
            $case = DB::transaction(function () use ($enquiry, $complaint, $marker, $users) {
                $enquiry->approvals()->create([
                    'circle_incharge_id' => $users['ci']->id,
                    'decision' => 'agree',
                    'remarks' => "{$marker} convert to case",
                ]);

                $case = CaseFile::create([
                    'enquiry_id' => $enquiry->id,
                    'complaint_id' => $complaint->id,
                    'fir_no' => 'FIR-' . $marker,
                    'status' => 'registered',
                ]);

                $enquiry->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'recommendation' => 'convert_to_case',
                    'case_file_id' => $case->id,
                ]);
                $complaint->update([
                    'status' => 'case_registered',
                    'final_status' => 'case_registered',
                ]);

                return $case;
            });
            $this->assertTrue('case exists', CaseFile::whereKey($case->id)->exists());
            $this->assertTrue('complaint→case_registered', $complaint->fresh()->status === 'case_registered');

            // 6) Assign IO + file court
            $this->step('6. Assign IO + file court case');
            Auth::login($users['ci']);
            $case->update([
                'investigation_officer_id' => $users['io']->id,
                'status' => 'assigned',
            ]);
            Auth::login($users['io']);
            $court = DB::transaction(function () use ($case, $marker) {
                $case->update(['status' => 'in_progress']);

                return CourtCase::create([
                    'case_id' => $case->id,
                    'court_name' => 'Sessions Court — ' . $marker,
                    'judge_name' => 'Test Judge',
                    'filing_date' => now()->toDateString(),
                    'status' => 'filed',
                ]);
            });
            $this->assertTrue('court case filed', CourtCase::whereKey($court->id)->exists());
            $this->assertTrue('case in_progress', $case->fresh()->status === 'in_progress');

            // 7) Verdict → close
            $this->step('7. Verdict conviction → close case + complaint');
            Auth::login($users['io']);
            DB::transaction(function () use ($court, $case, $complaint, $marker) {
                $court->verdicts()->create([
                    'verdict' => 'conviction',
                    'verdict_date' => now()->toDateString(),
                    'details' => "{$marker} E2E conviction",
                ]);
                $court->update(['status' => 'verdict_given']);
                $case->update(['status' => 'closed']);
                $complaint->update([
                    'status' => 'closed',
                    'final_status' => 'closed_conviction',
                ]);
            });

            $complaint = $complaint->fresh();
            $case = $case->fresh();
            $court = $court->fresh();
            $enquiry = $enquiry->fresh();
            $verification = $verification->fresh();

            $this->assertTrue('court verdict_given', $court->status === 'verdict_given');
            $this->assertTrue('court_verdicts row', $court->verdicts()->exists());
            $this->assertTrue('case closed', $case->status === 'closed');
            $this->assertTrue('complaint closed', $complaint->status === 'closed');
            $this->assertTrue('final_status closed_conviction', $complaint->final_status === 'closed_conviction');

            $this->newLine();
            $this->info('=== DB RECORD IDS (persisted) ===');
            $this->table(
                ['Entity', 'ID', 'Key', 'Status'],
                [
                    ['Complaint', $complaint->id, $complaint->tracking_no, $complaint->status . ' / ' . $complaint->final_status],
                    ['Verification', $verification->id, 'VO=' . $verification->verification_officer_id, $verification->status],
                    ['Enquiry', $enquiry->id, $enquiry->enquiry_number, $enquiry->status],
                    ['Case', $case->id, $case->fir_no, $case->status],
                    ['CourtCase', $court->id, $court->court_name, $court->status],
                ]
            );

            $failed = collect($this->checks)->where('ok', false)->count();
            $this->newLine();
            foreach ($this->checks as $c) {
                $this->line(($c['ok'] ? '[OK] ' : '[FAIL] ') . $c['label']);
            }

            if ($this->option('cleanup')) {
                $this->warn('Cleanup: deleting test records...');
                $court->verdicts()->delete();
                $court->delete();
                $case->delete();
                $enquiry->activities()->delete();
                $enquiry->approvals()->delete();
                $enquiry->delete();
                $verification->approvals()->delete();
                $verification->delete();
                $complaint->delete();
            } else {
                $this->info("Kept in DB. Search tracking: {$complaint->tracking_no} / marker {$marker}");
            }

            Auth::logout();

            if ($failed > 0) {
                $this->error("FAILED: {$failed} check(s)");

                return self::FAILURE;
            }

            $this->info('ALL CHECKS PASSED — full lifecycle is in the database.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Auth::logout();
            $this->error('E2E crashed: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }
    }

    private function step(string $label): void
    {
        $this->newLine();
        $this->comment($label);
    }

    private function assertTrue(string $label, bool $ok): void
    {
        $this->checks[] = ['label' => $label, 'ok' => $ok];
        if (!$ok) {
            $this->error('  FAIL: ' . $label);
        } else {
            $this->line('  OK: ' . $label);
        }
    }

    private function ensureCircle(): Circle
    {
        $zone = Zone::firstOrCreate(['code' => 'NZ'], ['name' => 'North Zone']);

        return Circle::firstOrCreate(
            ['code' => 'LHR'],
            ['name' => 'Lahore Circle', 'zone_id' => $zone->id]
        );
    }

    /**
     * @return array{operator: User, vo: User, ci: User, eo: User, io: User}
     */
    private function ensureUsers(Circle $circle): array
    {
        $mk = function (string $email, string $name, string $role) use ($circle): User {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'designation' => $role,
                    'circle_id' => $circle->id,
                    'zone_id' => $circle->zone_id,
                ]
            );
            $user->update([
                'circle_id' => $circle->id,
                'zone_id' => $circle->zone_id,
            ]);
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            return $user->fresh();
        };

        return [
            'operator' => $mk('operator@nccia.gov.pk', 'Operator', 'operator'),
            'vo' => $mk('verification.officer@nccia.gov.pk', 'Verification Officer', 'verification_officer'),
            'ci' => $mk('circle.incharge@nccia.gov.pk', 'Circle Incharge', 'circle_incharge'),
            'eo' => $mk('enquiry.officer@nccia.gov.pk', 'Enquiry Officer', 'enquiry_officer'),
            'io' => $mk('investigation.officer@nccia.gov.pk', 'Investigation Officer', 'investigation_officer'),
        ];
    }

    private function createComplaint(array $users, Circle $circle, string $offence, string $marker): Complaint
    {
        $tracking = 'TRK-' . substr(preg_replace('/\W/', '', $marker), 0, 20);

        $complaint = Complaint::create([
            'complainant_name' => 'E2E Complainant ' . $marker,
            'cnic' => '35202-1234567-1',
            'contact_no' => '03001234567',
            'address' => 'Lahore E2E Address',
            'report_date' => now()->toDateString(),
            'diary_no' => 'DY-' . $marker,
            'received_via' => 'Walk-in',
            'received_from' => 'Public',
            'offence_type' => $offence,
            'occurrence_date' => now()->subDays(3)->toDateString(),
            'description' => "{$marker} full lifecycle test complaint — must reach court close.",
            'operator_name' => $users['operator']->name,
            'operator_designation' => 'ASI',
            'operator_id' => $users['operator']->id,
            'user_id' => $users['operator']->id,
            'circle_id' => $circle->id,
            'entry_time' => now(),
            'scrutiny_result' => 'complete',
            'status' => 'complete',
            'tracking_no' => $tracking,
        ]);

        Verification::create([
            'complaint_id' => $complaint->id,
            'verification_officer_id' => $users['vo']->id,
            'priority_type' => 'normal',
            'status' => 'assigned',
            'assigned_at' => now(),
            'assigned_by' => $users['operator']->id,
        ]);

        return $complaint->fresh();
    }
}
