<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;

require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

use Database\Seeders\CircleTenancySeeder;
use Database\Seeders\NcciaOfficesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Log\Events\MessageLogged;
use Tests\TestCase;

/**
 * Non-destructive SQL injection probes against every API input point.
 * Runs only on the in-memory SQLite test database.
 */
class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    /** Classic, boolean, UNION, stacked-query and comment payloads (all read-only). */
    private const PAYLOADS = [
        "' OR '1'='1",
        "' OR 1=1 --",
        "1' AND '1'='2",
        "1) OR (1=1",
        "' UNION SELECT name, password FROM users --",
        "1; SELECT 1 --",
        "\" OR \"\"=\"",
        "admin@admin.com'--",
        "%' OR 'x'='x",
        "1 OR sleep(0)",
    ];

    private const QUERY_PARAMS = [
        'search', 'q', 'query', 'status', 'type', 'from', 'to', 'date_from', 'date_to', 'circle_id',
        'zone_id', 'officer_id', 'user_id', 'batch_id', 'priority', 'sort', 'order', 'direction',
        'per_page', 'page', 'year', 'month', 'module', 'since', 'role', 'category', 'filter', 'code',
    ];

    private array $sqlErrors = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Hard isolation guard: these probes may only ever touch a disposable in-memory database.
        $connection = DB::connection();
        if (!app()->environment('testing')
            || $connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:') {
            $this->fail('SqlInjectionTest refused to run: requires APP_ENV=testing and an in-memory SQLite database.');
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);

        // Record any SQL error raised anywhere in the request, even if a controller swallows it.
        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $ex = $e->context['exception'] ?? null;
            $text = $e->message . ($ex instanceof \Throwable ? ' ' . $ex->getMessage() : '');
            if (preg_match('/syntax error|unrecognized token|unterminated|near "|SQLSTATE\[42000\]/i', $text)) {
                $this->sqlErrors[] = substr($text, 0, 300);
            }
        });
    }

    private function tableCounts(): array
    {
        $counts = [];
        foreach (['users', 'complaints', 'enquiries', 'verifications', 'cases'] as $t) {
            $counts[$t] = DB::table($t)->count();
        }
        return $counts;
    }

    /**
     * Detector self-check. The vulnerable route below exists only in memory for
     * this test run — it is never registered in routes/ or reachable by the app.
     */
    public function test_detector_catches_a_test_only_vulnerable_fixture(): void
    {
        Route::get('/api/__test-only/sqli-fixture', function (\Illuminate\Http\Request $request) {
            $q = (string) $request->query('q');
            return response()->json(DB::select("select id from users where email = '" . $q . "'"));
        });

        $normal = $this->getJson('/api/__test-only/sqli-fixture?q=' . rawurlencode('admin@admin.com'));
        $this->assertCount(1, $normal->json());

        $tautology = $this->getJson('/api/__test-only/sqli-fixture?q=' . rawurlencode("' OR '1'='1"));
        $this->assertGreaterThan(1, count($tautology->json() ?? []), 'Fixture should leak every row — detector baseline');

        $broken = $this->getJson('/api/__test-only/sqli-fixture?q=' . rawurlencode("'unterminated"));
        $this->assertSame(500, $broken->status());
        $this->assertNotEmpty($this->sqlErrors, 'Detector must record the SQL syntax error');
    }

    public function test_login_payloads_are_treated_as_data(): void
    {
        foreach (self::PAYLOADS as $p) {
            foreach ([['email' => $p, 'password' => 'x'], ['email' => 'admin@admin.com', 'password' => $p], ['email' => $p, 'password' => $p]] as $body) {
                $r = $this->postJson('/api/login', $body);
                $this->assertNotContains($r->status(), [200, 500], "login accepted or crashed for payload: {$p}");
                $this->assertGuest('web');
            }
            $r = $this->postJson('/api/forgot-password', ['email' => $p]);
            $this->assertLessThan(500, $r->status(), "forgot-password crashed for payload: {$p}");
        }
        $this->assertSame([], $this->sqlErrors);
    }

    public function test_every_get_endpoint_handles_injected_parameters(): void
    {
        $before = $this->tableCounts();
        $users = [User::where('email', 'admin@admin.com')->first(), User::where('email', 'ci.lhr@nccia.gov.pk')->first(), User::where('email', 'fdo.lhr@nccia.gov.pk')->first()];
        $tested = 0;
        $statuses = [];
        $crashes = [];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'api/'))
            ->reject(fn ($r) => str_contains($r->uri(), 'secure-file'));

        foreach ($routes as $route) {
            $benignUri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $benignQuery = http_build_query(array_fill_keys(self::QUERY_PARAMS, '1'));
            $baseline = [];
            foreach ($users as $user) {
                $baseline[$user->id] = $this->actingAs($user, 'sanctum')->getJson('/' . $benignUri . '?' . $benignQuery)->status();
            }
            foreach ([self::PAYLOADS[0], self::PAYLOADS[4], self::PAYLOADS[3]] as $p) {
                // Fill path parameters with the payload too (route-model binding must reject it).
                $uri = preg_replace('/\{[^}]+\}/', rawurlencode($p), $route->uri());
                $query = http_build_query(array_fill_keys(self::QUERY_PARAMS, $p));
                foreach ($users as $user) {
                    $r = $this->actingAs($user, 'sanctum')->getJson('/' . $uri . '?' . $query);
                    $tested++;
                    $statuses[intdiv($r->status(), 100) . 'xx'] = ($statuses[intdiv($r->status(), 100) . 'xx'] ?? 0) + 1;
                    if ($r->status() >= 500 && $baseline[$user->id] < 500) {
                        $crashes[] = $route->uri() . ' as ' . $user->email . ' -> ' . $r->status() . ' ' . substr((string) $r->getContent(), 0, 160);
                    }
                }
            }
        }

        fwrite(STDERR, "\n[SQLi] GET requests sent: {$tested} across " . $routes->count() . " routes; status classes: " . json_encode($statuses) . "\n");
        $this->assertSame([], $this->sqlErrors, "SQL errors raised:\n" . implode("\n", array_unique($this->sqlErrors)));
        $this->assertSame([], $crashes, "Server errors:\n" . implode("\n", array_unique($crashes)));
        $this->assertSame($before, $this->tableCounts(), 'Row counts changed during read-only probes');
    }

    public function test_search_tautology_does_not_widen_results(): void
    {
        $ci = User::where('email', 'ci.lhr@nccia.gov.pk')->first();
        $visible = Complaint::visibleTo($ci)->count();

        foreach (self::PAYLOADS as $p) {
            $r = $this->actingAs($ci, 'sanctum')->getJson('/api/search?q=' . rawurlencode($p));
            $this->assertLessThan(500, $r->status(), "search crashed for: {$p}");
            $rows = collect($r->json('complaints') ?? $r->json('data') ?? [])->count();
            $this->assertLessThanOrEqual($visible, $rows, "search returned rows outside the user's scope for: {$p}");

            $r = $this->actingAs($ci, 'sanctum')->getJson('/api/complaints?search=' . rawurlencode($p));
            $this->assertLessThan(500, $r->status(), "complaints filter crashed for: {$p}");
            $this->assertLessThanOrEqual($visible, (int) ($r->json('total') ?? $r->json('meta.total') ?? 0));
        }
        $this->assertSame([], $this->sqlErrors);
    }
}
