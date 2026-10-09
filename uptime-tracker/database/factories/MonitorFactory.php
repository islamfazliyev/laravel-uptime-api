<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->domainName(),
            'url' => 'https://' . fake()->domainName(),
            'check_interval' => 60,
            'status' => 'pending',
            'is_paused' => false,
            'certificate_check_enabled' => true,
        ];
    }
}
