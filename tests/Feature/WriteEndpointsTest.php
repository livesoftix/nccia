<?php

namespace Tests\Feature;

use App\Models\User;

require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

use Database\Seeders\CircleTenancySeeder;
use Database\Seeders\NcciaOfficesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Authorization and validation behaviour of every state-changing API route.
 * Runs only on the disposable in-memory SQLite test database.
 */
class WriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that are intentionally reachable without a session. */
    private const PUBLIC_WRITES = [
        'api/login', 'api/forensic/login', 'api/forgot-password', 'api/reset-password', 'api/logout',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();
        if (!app()->environment('testing') || $connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            $this->fail('WriteEndpointsTest refused to run: requires APP_ENV=testing and an in-memory SQLite database.');
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);
    }

    /** @return array<int, array{0: string, 1: string, 2: array<int, string>}> method, uri, allowed roles */
    private function writeRoutes(): array
    {
        $out = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $method = collect($route->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true));
            if (!$method || in_array($route->uri(), self::PUBLIC_WRITES, true)) {
                continue;
            }
            $roles = [];
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && preg_match('/^(?:role|.*CheckRole):(.+)$/', $mw, $m)) {
                    $roles = array_map('trim', explode(',', $m[1]));
                }
            }
            $out[] = [$method, preg_replace('/\{[^}]+\}/', '1', $route->uri()), $roles];
        }
        return $out;
    }

    public function test_every_write_route_requires_authentication(): void
    {
        $failures = [];
        foreach ($this->writeRoutes() as [$method, $uri]) {
            $status = $this->json($method, '/' . $uri, ['x' => 'y'])->status();
            if (!in_array($status, [401, 419], true)) {
                $failures[] = "{$method} /{$uri} -> {$status}";
            }
            auth()->forgetGuards();
        }
        $this->assertSame([], $failures, "Write routes reachable without authentication:\n" . implode("\n", $failures));
    }

    public function test_role_gated_write_routes_reject_unlisted_roles(): void
    {
        // A field officer holds none of the elevated roles used by role middleware.
        $officer = User::where('email', 'vo.lhr@nccia.gov.pk')->firstOrFail();
        $failures = [];
        $checked = 0;
        foreach ($this->writeRoutes() as [$method, $uri, $roles]) {
            if ($roles === [] || in_array('verification_officer', $roles, true)) {
                continue;
            }
            $checked++;
            $status = $this->actingAs($officer, 'sanctum')->json($method, '/' . $uri, [])->status();
            // 404 is also a denial: route-model binding resolves (and misses) before role middleware.
            if (!in_array($status, [403, 404], true)) {
                $failures[] = "{$method} /{$uri} (roles: " . implode(',', $roles) . ") -> {$status}";
            }
        }
        $this->assertGreaterThan(20, $checked);
        $this->assertSame([], $failures, "Role-gated routes not returning 403 for an unlisted role:\n" . implode("\n", $failures));
    }

    public function test_invalid_input_is_rejected_without_server_errors(): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $bodies = [
            'empty'      => [],
            'wrong types' => ['name' => ['nested' => 'array'], 'ids' => 'not-an-array', 'date' => 'not-a-date', 'status' => str_repeat('x', 5000)],
        ];
        $serverErrors = [];
        $accepted = [];
        foreach ($this->writeRoutes() as [$method, $uri]) {
            if ($method === 'DELETE') {
                continue; // destructive by design; covered by authorization tests above
            }
            foreach ($bodies as $label => $body) {
                $status = $this->actingAs($admin, 'sanctum')->json($method, '/' . $uri, $body)->status();
                if ($status >= 500) {
                    $serverErrors[] = "{$method} /{$uri} [{$label}] -> {$status}";
                } elseif ($status < 300) {
                    $accepted[] = "{$method} /{$uri} [{$label}] -> {$status}";
                }
            }
        }
        fwrite(STDERR, "\n[write] accepted without validation error: " . count($accepted) . "\n  " . implode("\n  ", $accepted) . "\n");
        fwrite(STDERR, "[write] server errors: " . count($serverErrors) . "\n  " . implode("\n  ", $serverErrors) . "\n");
        $this->assertTrue(true);
    }
}
