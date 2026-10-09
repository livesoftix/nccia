<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OffenceTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $offenceTypes = [
            ['value' => 'financial_fraud', 'name' => 'Financial / Banking Fraud', 'group' => 'Financial Crimes'],
            ['value' => 'online_scam', 'name' => 'Online Scam / Phishing', 'group' => 'Financial Crimes'],
            ['value' => 'crypto_fraud', 'name' => 'Cryptocurrency Fraud', 'group' => 'Financial Crimes'],
            ['value' => 'extortion', 'name' => 'Cyber Extortion / Blackmail', 'group' => 'Financial Crimes'],
            ['value' => 'cyberstalking', 'name' => 'Cyberstalking', 'group' => 'Privacy & Harassment'],
            ['value' => 'impersonation', 'name' => 'Impersonation / Identity Theft', 'group' => 'Privacy & Harassment'],
            ['value' => 'defamation', 'name' => 'Online Defamation / Fake Profile', 'group' => 'Privacy & Harassment'],
            ['value' => 'harassment', 'name' => 'Online Harassment / Threats', 'group' => 'Privacy & Harassment'],
            ['value' => 'non_consensual', 'name' => 'Non-Consensual Content', 'group' => 'Privacy & Harassment'],
            ['value' => 'hacking', 'name' => 'Hacking / Unauthorized Access', 'group' => 'Technical Crimes'],
            ['value' => 'malware', 'name' => 'Malware / Ransomware Attack', 'group' => 'Technical Crimes'],
            ['value' => 'data_breach', 'name' => 'Data Breach / Theft', 'group' => 'Technical Crimes'],
            ['value' => 'ddos', 'name' => 'DDoS / System Disruption', 'group' => 'Technical Crimes'],
            ['value' => 'anti_state', 'name' => 'Anti-State / Terrorism Content', 'group' => 'Other'],
            ['value' => 'hate_speech', 'name' => 'Hate Speech / Extremism', 'group' => 'Other'],
            ['value' => 'other', 'name' => 'Other (Specify in Description)', 'group' => 'Other'],
        ];

        $timestamp = now();

        foreach ($offenceTypes as $item) {
            \DB::table('offence_types')->insertOrIgnore([
                'value'      => $item['value'],
                'name'       => $item['name'],
                'group'      => $item['group'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }
}
