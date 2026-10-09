<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_pause_monitor()
    {
        $monitor = Monitor::factory()->create(['is_paused' => false]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/monitors/{$monitor->id}/pause");

        $response->assertStatus(200);
        $this->assertTrue($monitor->fresh()->is_paused);
    }

    public function test_can_resume_monitor()
    {
        $monitor = Monitor::factory()->create(['is_paused' => true]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/monitors/{$monitor->id}/resume");

        $response->assertStatus(200);
        $this->assertFalse($monitor->fresh()->is_paused);
    }

    public function test_can_create_monitor_with_ssl_check_enabled()
    {
        $data = [
            'name' => 'My HTTPS Site',
            'url' => 'https://example.com',
            'check_interval' => 60,
            'certificate_check_enabled' => true
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/monitors', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('monitors', [
            'url' => 'https://example.com',
            'certificate_check_enabled' => 1
        ]);
    }
}
