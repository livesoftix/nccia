<?php

namespace Database\Seeders;

use App\Models\Law;
use App\Models\Rule;
use App\Models\Sop;
use App\Models\UserManual;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ReferenceLibraryContent::laws() as $row) {
            Law::updateOrCreate(['title' => $row['title']], $row);
        }

        foreach (ReferenceLibraryContent::rules() as $row) {
            Rule::updateOrCreate(['title' => $row['title']], $row);
        }

        foreach (ReferenceLibraryContent::sops() as $row) {
            Sop::updateOrCreate(['title' => $row['title']], $row);
        }

        foreach (ReferenceLibraryContent::manuals() as $row) {
            UserManual::updateOrCreate(['title' => $row['title']], $row);
        }
    }
}
