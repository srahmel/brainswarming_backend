<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Services\EntryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entry>
 */
class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeSaved = fake()->numberBetween(100, 1000);
        $grossProfit = fake()->numberBetween(5000, 50000);
        $effort = fake()->randomElement(['low', 'medium', 'high']);
        $manualOverridePrio = 0;

        // Calculate final priority
        $entryService = new EntryService();
        $finalPrio = $entryService->calculatePriority([
            'time_saved_per_year' => $timeSaved,
            'gross_profit_per_year' => $grossProfit,
            'effort' => $effort,
            'manual_override_prio' => $manualOverridePrio,
        ]);

        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'problem' => fake()->paragraph(),
            'solution' => fake()->paragraph(),
            'area' => fake()->randomElement(['Customer Service', 'Marketing', 'Sales', 'Product Development', 'Operations', 'Finance', 'HR']),
            'time_saved_per_year' => $timeSaved,
            'gross_profit_per_year' => $grossProfit,
            'effort' => $effort,
            'monetary_explanation' => fake()->sentence(),
            'link' => fake()->optional(0.7)->url(),
            'anonymous' => fake()->boolean(20), // 20% chance of being anonymous
            'manual_override_prio' => $manualOverridePrio,
            'final_prio' => $finalPrio,
        ];
    }

    /**
     * Indicate that the entry should have a manual priority override.
     */
    public function withManualPriority(int $priority = 10): static
    {
        return $this->state(function (array $attributes) use ($priority) {
            $entryService = new EntryService();
            $finalPrio = $entryService->calculatePriority([
                'time_saved_per_year' => $attributes['time_saved_per_year'],
                'gross_profit_per_year' => $attributes['gross_profit_per_year'],
                'effort' => $attributes['effort'],
                'manual_override_prio' => $priority,
            ]);

            return [
                'manual_override_prio' => $priority,
                'final_prio' => $finalPrio,
            ];
        });
    }

    /**
     * Indicate that the entry should be anonymous.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'anonymous' => true,
        ]);
    }
}
