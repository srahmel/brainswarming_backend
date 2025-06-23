<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'team_code' => fake()->unique()->regexify('[A-Z]{5}[0-9]{3}'),
            'invite_token' => Str::random(20),
            'invite_expires_at' => now()->addDays(7),
            'founder_user_id' => User::factory(),
            'settings' => [
                'allow_anonymous_entries' => true,
                'default_view' => 'list',
            ],
        ];
    }

    /**
     * Indicate that the team's invite has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'invite_expires_at' => now()->subDays(1),
        ]);
    }
}
