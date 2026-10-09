<?php

namespace App\Providers;

use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\InvestigationOfficer;
use App\Models\Verification;
use App\Policies\ComplaintPolicy;
use App\Policies\EnquiryPolicy;
use App\Policies\InvestigationOfficerPolicy;
use App\Policies\VerificationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Immutable audit ledger: auto-record create/update/delete of key records.
        $observed = [
            \App\Models\Complaint::class,
            \App\Models\Verification::class,
            \App\Models\VerificationReport::class,
            \App\Models\Enquiry::class,
            \App\Models\CaseFile::class,
            \App\Models\CourtCase::class,
            \App\Models\CourtVerdict::class,
            \App\Models\Arrest::class,
            \App\Models\ForensicRequest::class,
            \App\Models\WarrantRequest::class,
            \App\Models\Proclamation::class,
            \App\Models\PropertyAttachment::class,
            \App\Models\User::class,
            \App\Models\ApprovalSetting::class,
        ];
        foreach ($observed as $model) {
            if (class_exists($model)) {
                $model::observe(\App\Observers\AuditObserver::class);
            }
        }

        Gate::policy(Complaint::class, ComplaintPolicy::class);
        Gate::policy(Verification::class, VerificationPolicy::class);
        Gate::policy(Enquiry::class, EnquiryPolicy::class);
        Gate::policy(InvestigationOfficer::class, InvestigationOfficerPolicy::class);

        // Standard API Rate Limiter (SPA + mobile)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });

        // Polling endpoints (dashboard, sidebar badges, analytics)
        RateLimiter::for('dashboard', function (Request $request) {
            return Limit::perMinute(360)->by($request->user()?->id ?: $request->ip());
        });

        // Anti-Brute-Force Login Rate Limiter (Max 5 attempts / min per IP)
        RateLimiter::for('auth_login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts detected. For security reasons, please wait 1 minute before trying again.',
                ], 429);
            });
        });

        // Sensitive Action Rate Limiter (Password Reset, Permission Edit)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        $this->registerSqliteCompatibilityFunctions();
    }

    /**
     * Register MySQL-compatible SQL functions for SQLite so the same
     * queries (DATEDIFF, SUBSTRING_INDEX, YEAR) work on both databases.
     */
    protected function registerSqliteCompatibilityFunctions(): void
    {
        try {
            $connection = DB::connection();
        } catch (\Throwable $e) {
            return;
        }

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();

        $pdo->sqliteCreateFunction('DATEDIFF', function ($date1, $date2) {
            if ($date1 === null || $date2 === null) {
                return null;
            }
            $ts1 = strtotime((string) $date1);
            $ts2 = strtotime((string) $date2);
            if ($ts1 === false || $ts2 === false) {
                return null;
            }
            return (int) floor(($ts1 - $ts2) / 86400);
        }, 2);

        $pdo->sqliteCreateFunction('SUBSTRING_INDEX', function ($string, $delimiter, $count) {
            if ($string === null || $delimiter === '') {
                return null;
            }
            $string = (string) $string;
            $count = (int) $count;
            $parts = explode((string) $delimiter, $string);

            if ($count > 0) {
                if (count($parts) <= $count) {
                    return $string;
                }
                return implode((string) $delimiter, array_slice($parts, 0, $count));
            }

            if ($count < 0) {
                $abs = abs($count);
                if (count($parts) <= $abs) {
                    return $string;
                }
                return implode((string) $delimiter, array_slice($parts, -$abs));
            }

            return '';
        }, 3);

        $pdo->sqliteCreateFunction('YEAR', function ($date) {
            if ($date === null) {
                return null;
            }
            return (int) date('Y', strtotime((string) $date));
        }, 1);
    }
}
