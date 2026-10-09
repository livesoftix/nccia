<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Verification;
require_once __DIR__ . '/../../database/seeders/CircleTenancySeeder.php';

use Database\Seeders\CircleTenancySeeder;
use Database\Seeders\NcciaOfficesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CircleIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(NcciaOfficesSeeder::class);
        $this->seed(CircleTenancySeeder::class);
    }

    public function test_gujranwala_ci_only_sees_gujranwala_complaints(): void
    {
        $ciGrw = User::where('email', 'ci.grw@nccia.gov.pk')->first();
        $circleGrw = Circle::where('code', 'GRW')->first();
        $circleLhr = Circle::where('code', 'LHR')->first();

        $this->assertNotNull($ciGrw, 'Gujranwala CI must exist');
        $this->assertNotNull($circleGrw);
        $this->assertNotNull($circleLhr);

        $visibleComplaints = Complaint::visibleTo($ciGrw)->get();

        $this->assertNotEmpty($visibleComplaints, 'CI Gujranwala should have complaints in scope');
        foreach ($visibleComplaints as $c) {
            $this->assertEquals($circleGrw->id, $c->circle_id, "Complaint {$c->tracking_no} must belong to Gujranwala");
            $this->assertNotEquals($circleLhr->id, $c->circle_id, "Complaint {$c->tracking_no} must not belong to Lahore");
        }
    }

    public function test_lahore_ci_only_sees_lahore_complaints(): void
    {
        $ciLhr = User::where('email', 'ci.lhr@nccia.gov.pk')->first();
        $circleGrw = Circle::where('code', 'GRW')->first();
        $circleLhr = Circle::where('code', 'LHR')->first();

        $this->assertNotNull($ciLhr, 'Lahore CI must exist');

        $visibleComplaints = Complaint::visibleTo($ciLhr)->get();

        $this->assertNotEmpty($visibleComplaints, 'CI Lahore should have complaints in scope');
        foreach ($visibleComplaints as $c) {
            $this->assertEquals($circleLhr->id, $c->circle_id, "Complaint {$c->tracking_no} must belong to Lahore");
            $this->assertNotEquals($circleGrw->id, $c->circle_id, "Complaint {$c->tracking_no} must not belong to Gujranwala");
        }
    }

    public function test_punjab_zonal_head_sees_all_punjab_circles(): void
    {
        $directorPunjab = User::where('email', 'director.punjab@nccia.gov.pk')->first();
        $this->assertNotNull($directorPunjab, 'Director Punjab must exist');

        $visibleComplaints = Complaint::visibleTo($directorPunjab)->get();
        $circleIds = $visibleComplaints->pluck('circle_id')->unique()->toArray();

        $circleGrw = Circle::where('code', 'GRW')->first();
        $circleLhr = Circle::where('code', 'LHR')->first();

        $this->assertContains($circleGrw->id, $circleIds, 'Director Punjab must see Gujranwala complaints');
        $this->assertContains($circleLhr->id, $circleIds, 'Director Punjab must see Lahore complaints');
    }

    public function test_cross_circle_vo_assignment_is_rejected(): void
    {
        $ciGrw = User::where('email', 'ci.grw@nccia.gov.pk')->first();
        $voLhr = User::where('email', 'vo.lhr@nccia.gov.pk')->first();
        $circleGrw = Circle::where('code', 'GRW')->first();

        // Use complaint without pre-existing verification (e.g. complaint #3)
        $grwComplaint = Complaint::where('circle_id', $circleGrw->id)->doesntHave('verification')->first()
            ?? Complaint::where('circle_id', $circleGrw->id)->first();

        $this->assertNotNull($grwComplaint);
        $this->assertNotNull($voLhr);

        // Gujranwala CI attempts to assign Lahore VO to Gujranwala complaint
        $response = $this->actingAs($ciGrw, 'sanctum')->postJson("/api/complaints/{$grwComplaint->id}/direct-assign", [
            'verification_officer_id' => $voLhr->id,
            'priority_type'           => 'normal',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('same circle', $response->json('message'));
    }

    public function test_lookup_officers_returns_only_user_circle_officers(): void
    {
        $ciGrw = User::where('email', 'ci.grw@nccia.gov.pk')->first();
        $circleGrw = Circle::where('code', 'GRW')->first();

        $response = $this->actingAs($ciGrw, 'sanctum')->getJson('/api/lookup/verification-officers');
        $response->assertStatus(200);

        $officers = $response->json();
        $this->assertNotEmpty($officers);

        foreach ($officers as $officer) {
            $this->assertEquals($circleGrw->id, $officer['circle_id'], "Officer {$officer['name']} must belong to Gujranwala circle");
        }
    }

    public function test_ci_cannot_view_or_manage_users_of_another_circle(): void
    {
        $ciGrw = User::where('email', 'ci.grw@nccia.gov.pk')->first();
        $voLhr = User::where('email', 'vo.lhr@nccia.gov.pk')->first();

        // CI Gujranwala tries to view Lahore VO directly
        $response = $this->actingAs($ciGrw, 'sanctum')->getJson("/api/users/{$voLhr->id}");
        $response->assertStatus(403);

        // In user list, only Gujranwala users must appear
        $listResponse = $this->actingAs($ciGrw, 'sanctum')->getJson('/api/users');
        $listResponse->assertStatus(200);

        $users = $listResponse->json('data') ?? $listResponse->json();
        foreach ($users as $u) {
            $this->assertEquals($ciGrw->circle_id, $u['circle_id'], "User {$u['name']} must belong to Gujranwala");
        }
    }

    public function test_ci_department_progress_is_strictly_locked_to_their_station(): void
    {
        $ciGrw = User::where('email', 'ci.grw@nccia.gov.pk')->first();
        $circleGrw = Circle::where('code', 'GRW')->first();
        $circleLhr = Circle::where('code', 'LHR')->first();

        // 1. CI accesses progress without query params -> locked to their circle
        $response = $this->actingAs($ciGrw, 'sanctum')->getJson('/api/department-progress');
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['is_station_locked']);
        $this->assertEquals($circleGrw->id, $data['selected_circle']['id']);
        $this->assertCount(1, $data['circles']);
        $this->assertEquals($circleGrw->id, $data['circles'][0]['id']);

        // 2. CI maliciously requests another circle's progress (e.g. Lahore) -> still forced to Gujranwala
        $spoofedResponse = $this->actingAs($ciGrw, 'sanctum')->getJson("/api/department-progress?circle_id={$circleLhr->id}");
        $spoofedResponse->assertStatus(200);
        $spoofedData = $spoofedResponse->json();

        $this->assertTrue($spoofedData['is_station_locked']);
        $this->assertEquals($circleGrw->id, $spoofedData['selected_circle']['id']);
        $this->assertNotEquals($circleLhr->id, $spoofedData['selected_circle']['id']);
    }
}
