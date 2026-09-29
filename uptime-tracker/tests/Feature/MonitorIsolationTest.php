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
}