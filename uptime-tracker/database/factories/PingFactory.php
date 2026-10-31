<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\Ping;
use Illuminate\Database\Eloquent\Factories\Factory;

class PingFactory extends Factory
{
    protected $model = Ping::class;

    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'status_code' => 200,
            'response_time_ms' => fake()->numberBetween(50, 300),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
