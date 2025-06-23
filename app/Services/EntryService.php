<?php

namespace App\Services;

use App\Models\Entry;

class EntryService
{
    /**
     * Calculate the final priority for an entry based on its attributes.
     *
     * @param array $attributes The entry attributes
     * @param Entry|null $entry The existing entry (for updates)
     * @return int The calculated final priority
     */
    public function calculatePriority(array $attributes, ?Entry $entry = null): int
    {
        // Start with manual override priority
        $manualOverride = $attributes['manual_override_prio'] ?? ($entry ? $entry->manual_override_prio : 0);
        $finalPrio = $manualOverride;

        // Get time saved and gross profit values
        $timeSaved = $attributes['time_saved_per_year'] ?? ($entry ? $entry->time_saved_per_year : null);
        $grossProfit = $attributes['gross_profit_per_year'] ?? ($entry ? $entry->gross_profit_per_year : null);
        $effort = $attributes['effort'] ?? ($entry ? $entry->effort : null);

        if ($timeSaved && $grossProfit && $effort) {
            // Normalize values
            $timeFactor = $timeSaved / 100;
            $profitFactor = $grossProfit / 1000;

            // Effort factor (inverse - lower effort means higher priority)
            $effortFactor = [
                'low' => 3,
                'medium' => 2,
                'high' => 1
            ][$effort];

            // Calculate priority based on time saved, profit, and effort
            $calculatedPrio = ($timeFactor + $profitFactor) * $effortFactor;
            $finalPrio += $calculatedPrio;
        }

        return (int) $finalPrio;
    }
}
