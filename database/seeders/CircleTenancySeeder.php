<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Verification;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CircleTenancySeeder extends Seeder
{
    public function run(): void
    {
        $zonePunjab = Zone::where('code', 'PZ')->first();
        $zoneFederal = Zone::where('code', 'FCZ')->first();

        // 1. National Headquarters Accounts
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'        => 'National System Admin',
                'password'    => Hash::make('password123'),
                'role'        => 'admin',
                'designation' => 'System Administrator',
                'circle_id'   => null,
                'zone_id'     => null,
            ]
        );
        $admin->syncRoles(['admin']);

        $dg = User::firstOrCreate(
            ['email' => 'dg@nccia.gov.pk'],
            [
                'name'        => 'Director General NCCIA',
                'password'    => Hash::make('password123'),
                'role'        => 'director_general',
                'designation' => 'Director General NCCIA',
                'circle_id'   => null,
                'zone_id'     => null,
            ]
        );
        $dg->syncRoles(['director_general']);

        // 2. Punjab Regional Directorate / Zonal Head (Lahore Central Directorate)
        $circleLahore = Circle::where('code', 'LHR')->first();
        if ($circleLahore && $zonePunjab) {
            $directorPunjab = User::updateOrCreate(
                ['email' => 'director.punjab@nccia.gov.pk'],
                [
                    'name'        => 'Regional Director Punjab',
                    'password'    => Hash::make('password123'),
                    'role'        => 'additional_director',
                    'designation' => 'Regional Director (Punjab Zone)',
                    'circle_id'   => $circleLahore->id,
                    'zone_id'     => $zonePunjab->id,
                ]
            );
            $directorPunjab->syncRoles(['additional_director']);
        }

        // 3. Setup Per-Circle Stations (Lahore, Gujranwala, Rawalpindi, Multan, Faisalabad)
        $punjabStations = [
            'LHR' => ['name' => 'Lahore', 'prefix' => 'lhr'],
            'GRW' => ['name' => 'Gujranwala', 'prefix' => 'grw'],
            'RWP' => ['name' => 'Rawalpindi', 'prefix' => 'rwp'],
            'MUX' => ['name' => 'Multan', 'prefix' => 'mux'],
            'FSD' => ['name' => 'Faisalabad', 'prefix' => 'fsd'],
        ];

        foreach ($punjabStations as $code => $info) {
            $circle = Circle::where('code', $code)->first();
            if (!$circle) continue;

            $stationName = $info['name'];
            $p = $info['prefix'];

            // Circle Incharge (CI)
            $ci = User::updateOrCreate(
                ['email' => "ci.{$p}@nccia.gov.pk"],
                [
                    'name'        => "Circle Incharge {$stationName}",
                    'password'    => Hash::make('password123'),
                    'role'        => 'circle_incharge',
                    'designation' => "DSP / Circle Incharge {$stationName}",
                    'circle_id'   => $circle->id,
                    'zone_id'     => $circle->zone_id,
                ]
            );
            $ci->syncRoles(['circle_incharge']);

            // Front Desk Officer (FDO / Operator)
            $fdo = User::updateOrCreate(
                ['email' => "fdo.{$p}@nccia.gov.pk"],
                [
                    'name'        => "Front Desk Officer {$stationName}",
                    'password'    => Hash::make('password123'),
                    'role'        => 'operator',
                    'designation' => "Front Desk Officer (FDO) {$stationName}",
                    'circle_id'   => $circle->id,
                    'zone_id'     => $circle->zone_id,
                ]
            );
            $fdo->syncRoles(['operator']);

            // Verification Officer (VO)
            $vo = User::updateOrCreate(
                ['email' => "vo.{$p}@nccia.gov.pk"],
                [
                    'name'        => "VO {$stationName}",
                    'password'    => Hash::make('password123'),
                    'role'        => 'verification_officer',
                    'designation' => "Sub Inspector / Verification Officer {$stationName}",
                    'circle_id'   => $circle->id,
                    'zone_id'     => $circle->zone_id,
                ]
            );
            $vo->syncRoles(['verification_officer']);

            // Enquiry Officer (EO)
            $eo = User::updateOrCreate(
                ['email' => "eo.{$p}@nccia.gov.pk"],
                [
                    'name'        => "EO {$stationName}",
                    'password'    => Hash::make('password123'),
                    'role'        => 'enquiry_officer',
                    'designation' => "Inspector / Enquiry Officer {$stationName}",
                    'circle_id'   => $circle->id,
                    'zone_id'     => $circle->zone_id,
                ]
            );
            $eo->syncRoles(['enquiry_officer']);

            // Investigation Officer (IO)
            $io = User::updateOrCreate(
                ['email' => "io.{$p}@nccia.gov.pk"],
                [
                    'name'        => "IO {$stationName}",
                    'password'    => Hash::make('password123'),
                    'role'        => 'investigation_officer',
                    'designation' => "Inspector / Investigation Officer {$stationName}",
                    'circle_id'   => $circle->id,
                    'zone_id'     => $circle->zone_id,
                ]
            );
            $io->syncRoles(['investigation_officer']);

            // 4. Seed clean complaints for this specific circle
            $trackingPrefix = "{$code}-C-" . date('y');
            for ($i = 1; $i <= 3; $i++) {
                $trackingNo = "{$trackingPrefix}-" . str_pad($i, 4, '0', STR_PAD_LEFT);
                $complaint = Complaint::firstOrCreate(
                    ['tracking_no' => $trackingNo],
                    [
                        'complainant_name'    => "Citizen {$stationName} #{$i}",
                        'father_name'         => "Father {$i}",
                        'cnic'                => sprintf('3%04d-%07d-1', rand(1000, 9999), rand(1000000, 9999999)),
                        'contact_no'          => '0300123456' . $i,
                        'address'             => "Main Area, {$stationName}",
                        'profession'          => 'Private Sector',
                        'report_date'         => now()->subDays($i * 2),
                        'diary_no'            => "{$code}-D-" . str_pad($i, 3, '0', STR_PAD_LEFT) . '/26',
                        'received_via'        => 'Walk-in',
                        'received_from'       => 'General Public',
                        'cmu'                 => "NCCIA {$stationName}",
                        'priority_type'       => 'normal',
                        'offence_type'        => 'financial_fraud',
                        'amount_involved'     => 50000.00 * $i,
                        'occurrence_date'     => now()->subDays(10),
                        'description'         => "Cyber financial complaint reported at {$stationName} station.",
                        'operator_name'       => $fdo->name,
                        'operator_designation'=> $fdo->designation,
                        'entry_time'          => now()->subDays($i * 2),
                        'scrutiny_result'     => 'complete',
                        'status'              => 'complete',
                        'circle_id'           => $circle->id,
                        'user_id'             => $fdo->id,
                        'operator_id'         => $fdo->id,
                    ]
                );

                // Verification for complaint #1
                if ($i === 1) {
                    Verification::firstOrCreate(
                        ['complaint_id' => $complaint->id],
                        [
                            'verification_officer_id' => $vo->id,
                            'priority_type'           => 'normal',
                            'status'                  => 'assigned',
                            'assigned_by'             => $ci->id,
                            'assigned_at'             => now(),
                        ]
                    );
                }

                // Enquiry for complaint #2
                if ($i === 2) {
                    $enquiryNo = "{$code}-ENQ-" . str_pad($i, 3, '0', STR_PAD_LEFT) . '/26';
                    Enquiry::firstOrCreate(
                        ['enquiry_number' => $enquiryNo],
                        [
                            'complaint_id'       => $complaint->id,
                            'enquiry_officer_id' => $eo->id,
                            'status'             => 'assigned',
                            'reg_date'           => now()->subDays(1),
                            'assignment_date'    => now(),
                        ]
                    );
                }
            }
        }
    }
}
