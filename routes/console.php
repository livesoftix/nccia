<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:check-verification-deadlines --days=7')
    ->daily()
    ->withoutOverlapping();

Schedule::command('security:audit-verify')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('security.audit.require_writes'));
