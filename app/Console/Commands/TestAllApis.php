<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\TransientToken;

class TestAllApis extends Command
{
    protected $signature = 'nccia:test-apis
        {--user= : Email of user to test as (defaults to admin)}
        {--url= : Optional live base URL to test over HTTP (e.g. https://nccia.real-erp.net)}
        {--password=password : Password when using --url live login}';

    protected $description = 'Test all NCCIA API endpoints and verify status codes, authorization, and response health';

    protected array $endpoints = [
        // Category 1: Overview & Alerts
        ['method' => 'GET', 'uri' => '/api/security-alerts', 'desc' => 'Security Alerts', 'category' => 'Security'],
        ['method' => 'GET', 'uri' => '/api/dashboard', 'desc' => 'Main Dashboard API', 'category' => 'Dashboard'],
        ['method' => 'GET', 'uri' => '/api/analytics', 'desc' => 'Analytics Metrics', 'category' => 'Dashboard'],
        ['method' => 'GET', 'uri' => '/api/sidebar-counts', 'desc' => 'Sidebar Badge Counts', 'category' => 'Dashboard'],
        ['method' => 'GET', 'uri' => '/api/department-progress', 'desc' => 'Department Progress', 'category' => 'Dashboard'],
        ['method' => 'GET', 'uri' => '/api/search?q=test', 'desc' => 'Global Search API', 'category' => 'Search'],

        // Category 2: System Lookups
        ['method' => 'GET', 'uri' => '/api/lookup/professions', 'desc' => 'Lookup: Professions', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/received-via', 'desc' => 'Lookup: Received Via', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/received-from', 'desc' => 'Lookup: Received From', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/cmu-options', 'desc' => 'Lookup: CMU Options', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/offence-types', 'desc' => 'Lookup: Offence Types', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/roles', 'desc' => 'Lookup: System Roles', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/circles', 'desc' => 'Lookup: Circles', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/zones', 'desc' => 'Lookup: Zones', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/enquiry-officers', 'desc' => 'Lookup: Enquiry Officers', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/legal-officers', 'desc' => 'Lookup: Legal Officers', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/verification-officers', 'desc' => 'Lookup: Verification Officers', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/investigation-officers', 'desc' => 'Lookup: Investigation Officers', 'category' => 'Lookups'],
        ['method' => 'GET', 'uri' => '/api/lookup/circle-incharges', 'desc' => 'Lookup: Circle Incharges', 'category' => 'Lookups'],

        // Category 3: Complaints
        ['method' => 'GET', 'uri' => '/api/complaints', 'desc' => 'Complaints List', 'category' => 'Complaints'],
        ['method' => 'GET', 'uri' => '/api/complaints/search?query=test', 'desc' => 'Complaints Search', 'category' => 'Complaints'],
        ['method' => 'GET', 'uri' => '/api/complaint-pdf-imports', 'desc' => 'PDF Imports List', 'category' => 'Complaints'],
        ['method' => 'GET', 'uri' => '/api/complaint-pdf-imports/stats', 'desc' => 'PDF Imports Stats', 'category' => 'Complaints'],
        ['method' => 'GET', 'uri' => '/api/complaint-pdf-imports/capabilities', 'desc' => 'PDF Engine Capabilities', 'category' => 'Complaints'],

        // Category 4: Verifications
        ['method' => 'GET', 'uri' => '/api/verifications', 'desc' => 'Verifications List', 'category' => 'Verifications'],
        ['method' => 'GET', 'uri' => '/api/verifications/stats', 'desc' => 'Verifications Stats', 'category' => 'Verifications'],
        ['method' => 'GET', 'uri' => '/api/verifications/reports-list', 'desc' => 'Verification Reports List', 'category' => 'Verifications'],

        // Category 5: Enquiries
        ['method' => 'GET', 'uri' => '/api/enquiries', 'desc' => 'Enquiries List', 'category' => 'Enquiries'],
        ['method' => 'GET', 'uri' => '/api/enquiries/stats', 'desc' => 'Enquiries Stats', 'category' => 'Enquiries'],

        // Category 6: Cases & Court
        ['method' => 'GET', 'uri' => '/api/cases', 'desc' => 'DAC Cases List', 'category' => 'Cases'],
        ['method' => 'GET', 'uri' => '/api/court-cases', 'desc' => 'Court Cases List', 'category' => 'Court Cases'],

        // Category 7: Reports & DO Letters
        ['method' => 'GET', 'uri' => '/api/dsr-reports', 'desc' => 'DSR Reports List', 'category' => 'Reports'],
        ['method' => 'GET', 'uri' => '/api/do-letters', 'desc' => 'DO Letters List', 'category' => 'Reports'],

        // Category 8: Administration & Masters
        ['method' => 'GET', 'uri' => '/api/users', 'desc' => 'Users Management', 'category' => 'Admin'],
        ['method' => 'GET', 'uri' => '/api/circles', 'desc' => 'Circles Management', 'category' => 'Admin'],
        ['method' => 'GET', 'uri' => '/api/offence-types', 'desc' => 'Offence Types List', 'category' => 'Admin'],
        ['method' => 'GET', 'uri' => '/api/investigation-officers', 'desc' => 'Investigation Officers List', 'category' => 'Admin'],
        ['method' => 'GET', 'uri' => '/api/login-history', 'desc' => 'Login History Logs', 'category' => 'Admin'],
        ['method' => 'GET', 'uri' => '/api/login-history/stats', 'desc' => 'Login History Stats', 'category' => 'Admin'],

        // Category 9: Reference Data
        ['method' => 'GET', 'uri' => '/api/laws', 'desc' => 'Laws Reference Library', 'category' => 'Reference'],
        ['method' => 'GET', 'uri' => '/api/rules', 'desc' => 'Rules Reference Library', 'category' => 'Reference'],
        ['method' => 'GET', 'uri' => '/api/sops', 'desc' => 'SOPs Reference Library', 'category' => 'Reference'],
        ['method' => 'GET', 'uri' => '/api/user-manuals', 'desc' => 'User Manuals Library', 'category' => 'Reference'],

        // Category 10: Forensic Portal
        ['method' => 'GET', 'uri' => '/api/forensic/stats', 'desc' => 'Forensic Portal Stats', 'category' => 'Forensic'],
        ['method' => 'GET', 'uri' => '/api/forensic/request-stats', 'desc' => 'Forensic Requests Stats', 'category' => 'Forensic'],
        ['method' => 'GET', 'uri' => '/api/forensic/users', 'desc' => 'Forensic Users List', 'category' => 'Forensic'],

        // Category 11: Security Probing (Expected Behavior)
        ['method' => 'GET', 'uri' => '/api/v1/users', 'desc' => 'Probe non-existent v1 route (Expect 404)', 'category' => 'Security Probe', 'expect' => 404],
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->info("===============================================================");
        $this->info("           NCCIA ENTERPRISE API TEST SUITE                    ");
        $this->info("===============================================================");

        $liveUrl = $this->option('url');

        if ($liveUrl) {
            return $this->testLiveHttp($liveUrl);
        }

        return $this->testInternal();
    }

    protected function testInternal(): int
    {
        $userEmail = $this->option('user');
        $user = null;

        if ($userEmail) {
            $user = User::where('email', $userEmail)->first();
            if (!$user) {
                $this->error("User with email [{$userEmail}] not found in database.");
                return 1;
            }
        } else {
            $user = User::role('admin')->first()
                ?? User::role('director_general')->first()
                ?? User::where('email', 'admin@admin.com')->first()
                ?? User::where('email', 'like', '%admin%')->first()
                ?? User::first();
        }

        if (!$user) {
            $this->error("No user found in database. Please run seeds or specify a valid --user.");
            return 1;
        }

        $rolesStr = implode(', ', $user->getRoleNames()->toArray() ?: ['User']);
        $this->info("Authenticating as: <comment>{$user->name}</comment> (<comment>{$user->email}</comment>)");
        $this->info("Roles: <comment>{$rolesStr}</comment>");
        $this->newLine();

        Auth::login($user);
        Auth::guard('web')->setUser($user);
        Auth::setUser($user);
        if (method_exists($user, 'withAccessToken') && class_exists(\Laravel\Sanctum\TransientToken::class)) {
            $user->withAccessToken(new \Laravel\Sanctum\TransientToken);
        }
        app('auth')->guard('sanctum')->setUser($user);
        app('auth')->shouldUse('sanctum');

        $session = app('session.store');
        if (!$session->isStarted()) {
            $session->start();
        }
        $session->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());

        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($this->endpoints as $ep) {
            $expected = $ep['expect'] ?? 200;
            $start = microtime(true);

            Auth::guard('web')->setUser($user);
            Auth::setUser($user);
            app('auth')->guard('sanctum')->setUser($user);

            $req = Request::create($ep['uri'], $ep['method'], [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]);
            $req->setLaravelSession($session);
            $req->setUserResolver(fn () => $user);

            try {
                $response = $kernel->handle($req);
                $status = $response->getStatusCode();
                $kernel->terminate($req, $response);
            } catch (\Throwable $e) {
                $status = 500;
            }

            $duration = round((microtime(true) - $start) * 1000, 1);
            $isPass = ($status === $expected);

            if ($isPass) {
                $passed++;
                $statusStr = "<info>{$status}</info>";
                $resultStr = '<info>PASS</info>';
            } else {
                $failed++;
                $statusStr = "<fg=red>{$status}</fg=red> (exp: {$expected})";
                $resultStr = '<fg=red>FAIL</fg=red>';
            }

            $results[] = [
                $ep['category'],
                $ep['method'] . ' ' . $ep['uri'],
                $statusStr,
                "{$duration}ms",
                $resultStr,
            ];
        }

        $this->table(
            ['Category', 'Endpoint', 'HTTP Status', 'Latency', 'Result'],
            $results
        );

        $this->newLine();
        $this->info("---------------------------------------------------------------");
        $failText = $failed > 0 ? "<fg=red>Failed: {$failed}</fg=red>" : "Failed: 0";
        $this->info("Summary: Total Tested: " . count($this->endpoints) . " | <info>Passed: {$passed}</info> | {$failText}");
        $this->info("---------------------------------------------------------------");
        $this->newLine();

        return $failed === 0 ? 0 : 1;
    }

    protected function testLiveHttp(string $baseUrl): int
    {
        $baseUrl = rtrim($baseUrl, '/');
        $email = $this->option('user') ?: 'admin@admin.com';
        $password = $this->option('password');

        $this->info("Live Server Target: <comment>{$baseUrl}</comment>");
        $this->info("Attempting login with: <comment>{$email}</comment>");

        $loginRes = Http::acceptJson()->post("{$baseUrl}/api/login", [
            'email' => $email,
            'password' => $password,
        ]);

        $cookies = $loginRes->cookies();
        $client = Http::acceptJson()
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->withCookies($cookies->toArray(), parse_url($baseUrl, PHP_URL_HOST));

        if ($loginRes->failed()) {
            $this->warn("Live login returned {$loginRes->status()}. Continuing tests unauthenticated to verify endpoint protections...");
        } else {
            $this->info("<info>Logged in successfully to live server!</info>");
        }

        $this->newLine();

        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($this->endpoints as $ep) {
            $expected = $ep['expect'] ?? 200;
            $start = microtime(true);
            $url = "{$baseUrl}{$ep['uri']}";

            try {
                $res = $client->send($ep['method'], $url);
                $status = $res->status();
            } catch (\Throwable $e) {
                $status = 0;
            }

            $duration = round((microtime(true) - $start) * 1000, 1);
            $isPass = ($status === $expected);

            if ($isPass) {
                $passed++;
                $statusStr = "<info>{$status}</info>";
                $resultStr = '<info>PASS</info>';
            } else {
                $failed++;
                $statusStr = "<fg=red>{$status}</fg=red> (exp: {$expected})";
                $resultStr = '<fg=red>FAIL</fg=red>';
            }

            $results[] = [
                $ep['category'],
                $ep['method'] . ' ' . $ep['uri'],
                $statusStr,
                "{$duration}ms",
                $resultStr,
            ];
        }

        $this->table(
            ['Category', 'Endpoint', 'HTTP Status', 'Latency', 'Result'],
            $results
        );

        $this->newLine();
        $this->info("Summary: Total: " . count($this->endpoints) . " | Passed: {$passed} | Failed: {$failed}");
        return $failed === 0 ? 0 : 1;
    }
}
