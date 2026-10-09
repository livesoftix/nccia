<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable approval requirements. For each action (warrants, 87/88
 * proclamation & attachment) the admin chooses whether Circle Incharge approval
 * is "mandatory" (must be approved before it can be issued/printed) or "open"
 * (officer may issue directly). Defaults to "open" so existing behaviour is
 * unchanged until the admin turns a control on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->id();
            $table->string('action_key')->unique();   // e.g. arrest_warrant
            $table->string('label');                   // human label for the admin UI
            $table->enum('requirement', ['mandatory', 'open'])->default('open');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['action_key' => 'arrest_warrant',  'label' => 'Arrest Warrant'],
            ['action_key' => 'search_warrant',  'label' => 'Search Warrant'],
            ['action_key' => 'raid_permission', 'label' => 'Raid Permission'],
            ['action_key' => 'proclamation',    'label' => 'Proclamation (u/s 87 CrPC)'],
            ['action_key' => 'attachment',      'label' => 'Property Attachment (u/s 88 CrPC)'],
        ];
        foreach ($defaults as $d) {
            DB::table('approval_settings')->insert(array_merge($d, [
                'requirement' => 'open',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_settings');
    }
};
