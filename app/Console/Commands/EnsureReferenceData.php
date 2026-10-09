<?php

namespace App\Console\Commands;

use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;

class EnsureReferenceData extends Command
{
    protected $signature = 'reference:ensure';

    protected $description = 'Ensure default Laws, Rules, SOPs, and User Manuals exist (safe to re-run)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]);
        $this->info('Reference library ensured.');

        return self::SUCCESS;
    }
}
