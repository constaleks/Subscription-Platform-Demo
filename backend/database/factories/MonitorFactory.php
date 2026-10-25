<?php

namespace Database\Factories;

use App\Enums\HttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\PauseReason;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'url' => 'https://93.184.216.34', // public IP literal: passes the SSRF guard without a DNS lookup
            'method' => HttpMethod::Get,
            'interval_seconds' => 900,
            'timeout_seconds' => 30,
            'next_check_at' => now(),
            'is_active' => true,
            'status' => MonitorStatus::Pending,
            'consecutive_failures' => 0,
        ];
    }

    public function paused(PauseReason $reason = PauseReason::User): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'paused_reason' => $reason,
        ]);
    }

    public function down(int $failures = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MonitorStatus::Down,
            'consecutive_failures' => $failures,
        ]);
    }
}
