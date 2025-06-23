<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Services\EntryService;
use PHPUnit\Framework\TestCase;

class EntryServiceTest extends TestCase
{
    protected EntryService $entryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entryService = new EntryService();
    }

    /**
     * Test calculating priority with only manual override.
     */
    public function test_calculate_priority_with_only_manual_override(): void
    {
        $attributes = [
            'manual_override_prio' => 10,
        ];

        $priority = $this->entryService->calculatePriority($attributes);

        $this->assertEquals(10, $priority);
    }

    /**
     * Test calculating priority with all factors.
     */
    public function test_calculate_priority_with_all_factors(): void
    {
        $attributes = [
            'time_saved_per_year' => 500,
            'gross_profit_per_year' => 10000,
            'effort' => 'medium',
            'manual_override_prio' => 5,
        ];

        // Expected calculation:
        // timeFactor = 500 / 100 = 5
        // profitFactor = 10000 / 1000 = 10
        // effortFactor for 'medium' = 2
        // calculatedPrio = (5 + 10) * 2 = 30
        // finalPrio = 5 (manual override) + 30 = 35

        $priority = $this->entryService->calculatePriority($attributes);

        $this->assertEquals(35, $priority);
    }

    /**
     * Test calculating priority with different effort levels.
     */
    public function test_calculate_priority_with_different_effort_levels(): void
    {
        // Low effort (factor 3)
        $lowEffortAttributes = [
            'time_saved_per_year' => 300,
            'gross_profit_per_year' => 6000,
            'effort' => 'low',
            'manual_override_prio' => 0,
        ];
        // Expected: (3 + 6) * 3 = 27

        // Medium effort (factor 2)
        $mediumEffortAttributes = [
            'time_saved_per_year' => 300,
            'gross_profit_per_year' => 6000,
            'effort' => 'medium',
            'manual_override_prio' => 0,
        ];
        // Expected: (3 + 6) * 2 = 18

        // High effort (factor 1)
        $highEffortAttributes = [
            'time_saved_per_year' => 300,
            'gross_profit_per_year' => 6000,
            'effort' => 'high',
            'manual_override_prio' => 0,
        ];
        // Expected: (3 + 6) * 1 = 9

        $lowPriority = $this->entryService->calculatePriority($lowEffortAttributes);
        $mediumPriority = $this->entryService->calculatePriority($mediumEffortAttributes);
        $highPriority = $this->entryService->calculatePriority($highEffortAttributes);

        $this->assertEquals(27, $lowPriority);
        $this->assertEquals(18, $mediumPriority);
        $this->assertEquals(9, $highPriority);

        // Verify that lower effort results in higher priority
        $this->assertGreaterThan($mediumPriority, $lowPriority);
        $this->assertGreaterThan($highPriority, $mediumPriority);
    }

    /**
     * Test calculating priority with missing factors.
     */
    public function test_calculate_priority_with_missing_factors(): void
    {
        // Missing time_saved_per_year
        $attributes1 = [
            'gross_profit_per_year' => 10000,
            'effort' => 'medium',
            'manual_override_prio' => 7,
        ];

        // Missing gross_profit_per_year
        $attributes2 = [
            'time_saved_per_year' => 500,
            'effort' => 'medium',
            'manual_override_prio' => 7,
        ];

        // Missing effort
        $attributes3 = [
            'time_saved_per_year' => 500,
            'gross_profit_per_year' => 10000,
            'manual_override_prio' => 7,
        ];

        $priority1 = $this->entryService->calculatePriority($attributes1);
        $priority2 = $this->entryService->calculatePriority($attributes2);
        $priority3 = $this->entryService->calculatePriority($attributes3);

        // When any factor is missing, only the manual override should be used
        $this->assertEquals(7, $priority1);
        $this->assertEquals(7, $priority2);
        $this->assertEquals(7, $priority3);
    }

    /**
     * Test calculating priority with an existing entry.
     */
    public function test_calculate_priority_with_existing_entry(): void
    {
        // Create a mock Entry object
        $entry = $this->createMock(Entry::class);
        $entry->method('__get')->willReturnMap([
            ['time_saved_per_year', 400],
            ['gross_profit_per_year', 8000],
            ['effort', 'low'],
            ['manual_override_prio', 3],
        ]);

        // Only update the manual_override_prio
        $attributes = [
            'manual_override_prio' => 10,
        ];

        // Expected calculation:
        // timeFactor = 400 / 100 = 4
        // profitFactor = 8000 / 1000 = 8
        // effortFactor for 'low' = 3
        // calculatedPrio = (4 + 8) * 3 = 36
        // finalPrio = 10 (new manual override) + 36 = 46

        $priority = $this->entryService->calculatePriority($attributes, $entry);

        $this->assertEquals(46, $priority);
    }
}
