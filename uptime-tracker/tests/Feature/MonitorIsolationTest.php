<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitorIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeMonitor(User $user, string $url = 'https://example.com'): Monitor
    {
        return $user->monitors()->create([
            'name' => parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'check_interval' => 1,
        ]);
    }

    public function test_guest_cannot_list_monitors(): void
    {
        $this->getJson('/api/monitors')->assertUnauthorized();
    }

    public function test_index_returns_only_own_monitors(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->makeMonitor($alice, 'https://alice.test');
        $this->makeMonitor($bob, 'https://bob.test');

        Sanctum::actingAs($alice);

        $this->getJson('/api/monitors')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['url' => 'https://alice.test'])
            ->assertJsonMissing(['url' => 'https://bob.test']);
    }

    public function test_user_cannot_delete_another_users_monitor(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $monitor = $this->makeMonitor($owner);

        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/monitors/{$monitor->id}")->assertNotFound();
        $this->assertDatabaseHas('monitors', ['id' => $monitor->id]);
    }

    public function test_user_can_delete_own_monitor(): void
    {
        $user = User::factory()->create();
        $monitor = $this->makeMonitor($user);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/monitors/{$monitor->id}")->assertOk();
        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }

    public function test_user_cannot_see_stats_of_another_users_monitor(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $monitor = $this->makeMonitor($owner);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/monitors/{$monitor->id}/stats")->assertNotFound();
    }

    public function test_guest_cannot_access_logs(): void
    {
        $this->getJson('/api/logs')->assertUnauthorized();
    }

    public function test_user_only_sees_logs_of_own_monitors(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceMonitor = $this->makeMonitor($alice, 'https://alice.test');
        $bobMonitor = $this->makeMonitor($bob, 'https://bob.test');

        \App\Models\Ping::create([
            'monitor_id' => $aliceMonitor->id,
            'status_code' => 200,
            'response_time_ms' => 120,
        ]);
        \App\Models\Ping::create([
            'monitor_id' => $bobMonitor->id,
            'status_code' => 500,
            'response_time_ms' => 450,
        ]);

        Sanctum::actingAs($alice);

        $response = $this->getJson('/api/logs')->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['monitor_id' => $aliceMonitor->id]);
        $response->assertJsonMissing(['monitor_id' => $bobMonitor->id]);
    }

    public function test_user_cannot_pause_another_users_monitor(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $monitor = $this->makeMonitor($owner);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/monitors/{$monitor->id}/pause")->assertForbidden();
        $this->assertFalse($monitor->fresh()->is_paused);
    }

    public function test_user_cannot_resume_another_users_monitor(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $monitor = $this->makeMonitor($owner);
        $monitor->update(['is_paused' => true]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/monitors/{$monitor->id}/resume")->assertForbidden();
        $this->assertTrue($monitor->fresh()->is_paused);
    }

    public function test_user_cannot_trigger_check_on_another_users_monitor(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $monitor = $this->makeMonitor($owner);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/monitors/{$monitor->id}/check")->assertNotFound();
    }

    public function test_cors_allows_authorized_origin_and_blocks_unauthorized(): void
    {
        // Authorized origin
        $response = $this->json('OPTIONS', '/api/monitors', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ]);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');

        // Unauthorized origin
        $badResponse = $this->json('OPTIONS', '/api/monitors', [], [
            'Origin' => 'http://malicious-site.example',
            'Access-Control-Request-Method' => 'GET',
        ]);
        $badResponse->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}