<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\Complaint;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $circles = Circle::all();
        $users = User::all();
        $statuses = ['complete', 'incomplete', 'invalid', 'irrelevant'];
        $priorities = ['regular', 'court', 'anti_state', 'higher_authority'];
        $offences = ['financial_fraud', 'online_scam', 'crypto_fraud', 'extortion', 'cyberstalking', 'impersonation', 'defamation', 'harassment', 'hacking', 'malware', 'data_breach', 'hate_speech', 'anti_state'];
        $names = [
            'Ahmad Hassan', 'Muhammad Ali', 'Fatima Zahra', 'Omar Farooq', 'Ayesha Bibi',
            'Bilal Ahmed', 'Sana Tariq', 'Kamran Iqbal', 'Zainab Noor', 'Tariq Mahmood',
            'Nadia Hussain', 'Imran Khan', 'Sadia Bhatti', 'Faisal Rafiq', 'Rabia Anjum',
            'Usman Ghani', 'Hina Tariq', 'Javed Ashraf', 'Saima Akram', 'Khalid Mehmood',
            'Amna Saeed', 'Naveed Ahmad', 'Rubina Shaheen', 'Asif Raza', 'Tahira Bibi',
            'Shahid Ali', 'Nargis Fatima', 'Irfan Ullah', 'Kiran Masood', 'Rashid Minhas',
            'Samina Yasmin', 'Farhan Akhtar', 'Nasreen Javed', 'Waqar Ahmed', 'Shabnam Kiran',
            'Adnan Rashid', 'Shazia Zafar', 'Junaid Iqbal', 'Najma Sultana', 'Sohail Ahmed',
            'Parveen Akhtar', 'Tanveer Hussain', 'Shamim Bano', 'Kashif Ali', 'Nasim Jahan',
            'Rizwan Asghar', 'Shahnaz Begum', 'Mudassar Ali', 'Zubaida Khatoon', 'Naeem Akhtar',
            'Ashraf Hussain', 'Shakeel Ahmed', 'Rukhsana Parveen', 'Masood Ahmed', 'Zahida Perveen',
            'Nadeem Asghar', 'Shaista Jabeen', 'Iqbal Hussain', 'Yasmeen Akhtar', 'Riaz Ahmed',
            'Musarat Jahan', 'Sajid Mahmood', 'Farzana Bibi', 'Arif Hussain', 'Safina Begum',
            'Zafar Iqbal', 'Samina Parveen', 'Abdul Majeed', 'Noreen Akhtar', 'Tariq Javed',
            'Shabana Kausar', 'Mohsin Ali', 'Nasreen Sultana', 'Rashid Mahmood', 'Fahmida Bibi',
            'Shahid Mahmood', 'Ghulam Fatima', 'Arshad Mehmood', 'Shamim Akhtar', 'Saleem Raza',
            'Sajida Parveen', 'Nisar Ahmed', 'Shakila Bano', 'Mazhar Iqbal', 'Zareen Taj',
            'Khurshid Ahmed', 'Riffat Jahan', 'Ashfaq Ahmed', 'Khalida Perveen', 'Shafiq Ahmed',
            'Zakia Sultana', 'Fida Hussain', 'Naseem Akhtar', 'Anwar Ali', 'Sughran Bibi',
            'Shahid Nazir', 'Hajra Bibi', 'Aslam Pervez', 'Azra Parveen', 'Iftikhar Ahmed',
        ];

        $complaints = [];
        for ($i = 0; $i < 100; $i++) {
            $status = $statuses[array_rand($statuses)];
            $isComplete = $status === 'complete';
            $circle = $circles->random();
            $name = $names[$i];

            $complaint = Complaint::create([
                'complainant_name'    => $name,
                'cnic'                => sprintf('%05d-%07d-%d', rand(10000, 99999), rand(1000000, 9999999), rand(1, 9)),
                'contact_no'          => sprintf('03%d-%07d', rand(0, 9), rand(1000000, 9999999)),
                'address'             => rand(1, 999) . ', ' . ['Main Blvd', 'Garden Town', 'Model Town', 'Defence', 'Gulberg'][array_rand([0,1,2,3,4])] . ', ' . ['Lahore', 'Karachi', 'Islamabad', 'Peshawar', 'Quetta'][array_rand([0,1,2,3,4])],
                'profession'          => ['Government Employee', 'Private Sector', 'Business Owner', 'Student', 'Lawyer', 'Bank Employee', 'Teacher', 'Retired'][array_rand([0,1,2,3,4,5,6,7])],
                'report_date'         => now()->subDays(rand(1, 90)),
                'diary_no'            => 'LHR-D-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT) . '/26',
                'received_via'        => ['Email', 'Telephone', 'Postal Service', 'Walk-in', 'Online Portal', 'Tipline'][array_rand([0,1,2,3,4,5])],
                'received_from'       => ['General Public', 'PM Office', 'Court', 'Ministry', 'Bank', 'Organization', 'Anonymous'][array_rand([0,1,2,3,4,5,6])],
                'cmu'                 => ['NCCIA - HQs', 'CCRC - LHR', 'CCRC - KHI', 'CCRC - ISB'][array_rand([0,1,2,3])],
                'priority_type'       => $priorities[array_rand($priorities)],
                'offence_type'        => $offences[array_rand($offences)],
                'amount_involved'     => rand(0, 10) > 7 ? null : rand(10000, 5000000),
                'occurrence_date'     => now()->subDays(rand(1, 180)),
                'laws'                => ['peca_26A', 'peca_24', 'peca_3', 'ppc_420', 'ppc_506', 'ata'],
                'description'         => "Complaint regarding " . ['online fraud', 'cyberstalking', 'identity theft', 'hacking', 'bank fraud', 'extortion', 'defamation', 'harassment'][array_rand([0,1,2,3,4,5,6,7])] . " — detailed description of the incident involving $name. Further investigation required to ascertain the facts.",
                'evidence'            => ['screenshots', 'chat_logs', 'bank_records', 'call_records'],
                'operator_name'       => 'Muhammad Umar Ilyas',
                'operator_designation'=> 'Asst. Sub Inspector',
                'entry_time'          => now()->subDays(rand(1, 30))->setTime(rand(9, 17), rand(0, 59)),
                'scrutiny_result'     => $status,
                'status'              => $status,
                'operator_remarks'    => rand(0, 1) ? 'Initial review completed. Case requires verification.' : null,
                'user_id'             => $users->random()->id,
                'operator_id'         => $users->random()->id,
                'circle_id'           => $circle->id,
                'tracking_no'         => $isComplete ? $circle->code . '-C-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT) . '/26' : null,
                'created_at'          => now()->subDays(rand(1, 90)),
            ]);

            $complaints[] = $complaint;
        }

        $this->command->info('Created ' . count($complaints) . ' complaints.');

        // Create verifications for ~30 of them
        $verificationStatuses = ['pending_assignment', 'assigned', 'in_progress', 'submitted', 'approved', 'sent_back'];
        $officers = User::role('verification_officer')->get();
        $verifiedCount = 0;

        foreach ($complaints as $c) {
            if ($c->status !== 'complete' || rand(0, 1) === 0) continue;
            if (++$verifiedCount > 30) break;

            $vStatus = $verificationStatuses[array_rand($verificationStatuses)];
            $officer = $officers->random();

            Verification::create([
                'complaint_id'           => $c->id,
                'verification_officer_id' => $officer->id,
                'assigned_by'            => $users->random()->id,
                'priority_type'          => $priorities[array_rand($priorities)],
                'status'                 => $vStatus,
                'report_text'            => rand(0, 1) ? 'Verification completed. All details verified.' : null,
                'assigned_at'            => now()->subDays(rand(1, 20)),
                'submitted_at'           => in_array($vStatus, ['submitted', 'approved', 'sent_back']) ? now()->subDays(rand(1, 10)) : null,
                'approved_at'            => $vStatus === 'approved' ? now() : null,
                'transfer_circle_id'     => rand(0, 1) ? $circles->random()->id : null,
                'merge_complaint_id'     => null,
                'created_at'            => now()->subDays(rand(1, 25)),
            ]);
        }

        $this->command->info("Created {$verifiedCount} verifications.");
    }
}
