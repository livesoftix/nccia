<?php

namespace App\Console\Commands;

use App\Models\Verification;
use App\Notifications\VerificationDeadlineNotification;
use Illuminate\Console\Command;

class CheckVerificationDeadlines extends Command
{
    protected $signature = 'app:check-verification-deadlines
        {--days=7 : Number of days before a verification is considered overdue}';

    protected $description = 'Check for overdue verifications and notify the assigned officers';

    public function handle(): void
    {
        $threshold = now()->subDays((int) $this->option('days'));

        $overdue = Verification::whereIn('status', ['assigned', 'in_progress'])
            ->where('created_at', '<=', $threshold)
            ->with('officer', 'complaint')
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue verifications found.');
            return;
        }

        $this->info("Found {$overdue->count()} overdue verification(s):");
        foreach ($overdue as $v) {
            $daysOverdue = now()->diffInDays($v->created_at) - (int) $this->option('days');

            if ($v->officer && $this->hasActiveDeadlineNotification($v)) {
                $this->line("  - #{$v->id} ({$v->complaint?->tracking_no}) already notified — skipping");
                continue;
            }

            $v->officer?->notify(new VerificationDeadlineNotification($v, max($daysOverdue, 0)));

            $this->line("  - #{$v->id} ({$v->complaint?->tracking_no}) assigned to {$v->officer?->name} — {$daysOverdue} day(s) overdue (notification sent)");
        }
    }

    private function hasActiveDeadlineNotification(Verification $v): bool
    {
        if (!$v->officer) {
            return true;
        }

        return $v->officer->notifications()
            ->where('type', VerificationDeadlineNotification::class)
            ->whereNull('read_at')
            ->get()
            ->contains(fn ($n) => (int) ($n->data['verification_id'] ?? 0) === $v->id);
    }
}
