<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Team $team;
    protected Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create a team with the user as founder
        $this->team = Team::factory()->create([
            'founder_user_id' => $this->user->id,
        ]);

        // Attach the user to the team as an admin
        $this->team->users()->attach($this->user, ['is_admin' => true]);

        // Create an entry for the team by the user
        $this->entry = Entry::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
        ]);

        // Authenticate the user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test listing entries for a team.
     */
    public function test_can_list_entries(): void
    {
        // Create additional entries
        Entry::factory()->count(3)->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/teams/{$this->team->id}/entries");

        $response->assertStatus(200)
            ->assertJsonStructure(['entries'])
            ->assertJsonCount(4, 'entries');
    }

    /**
     * Test filtering entries by area.
     */
    public function test_can_filter_entries_by_area(): void
    {
        // Create entries with specific areas
        $area = 'Marketing';
        Entry::factory()->count(2)->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'area' => $area,
        ]);

        Entry::factory()->count(2)->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'area' => 'Sales',
        ]);

        $response = $this->getJson("/api/teams/{$this->team->id}/entries?area={$area}");

        $response->assertStatus(200)
            ->assertJsonStructure(['entries'])
            ->assertJsonCount(2, 'entries');

        $response->assertJson([
            'entries' => [
                ['area' => $area],
                ['area' => $area],
            ]
        ]);
    }

    /**
     * Test creating a new entry.
     */
    public function test_can_create_entry(): void
    {
        $entryData = [
            'problem' => 'Test problem',
            'solution' => 'Test solution',
            'area' => 'Customer Service',
            'time_saved_per_year' => 500,
            'gross_profit_per_year' => 10000,
            'effort' => 'medium',
            'monetary_explanation' => 'Test explanation',
            'link' => 'https://example.com',
            'anonymous' => false,
            'manual_override_prio' => 0,
        ];

        $response = $this->postJson("/api/teams/{$this->team->id}/entries", $entryData);

        $response->assertStatus(201)
            ->assertJsonStructure(['entry'])
            ->assertJson([
                'entry' => [
                    'problem' => $entryData['problem'],
                    'solution' => $entryData['solution'],
                    'area' => $entryData['area'],
                    'time_saved_per_year' => $entryData['time_saved_per_year'],
                    'gross_profit_per_year' => $entryData['gross_profit_per_year'],
                    'effort' => $entryData['effort'],
                    'monetary_explanation' => $entryData['monetary_explanation'],
                    'link' => $entryData['link'],
                    'anonymous' => $entryData['anonymous'],
                    'manual_override_prio' => $entryData['manual_override_prio'],
                ]
            ]);

        $this->assertDatabaseHas('entries', [
            'problem' => $entryData['problem'],
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test viewing a single entry.
     */
    public function test_can_view_entry(): void
    {
        $response = $this->getJson("/api/teams/{$this->team->id}/entries/{$this->entry->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['entry'])
            ->assertJson([
                'entry' => [
                    'id' => $this->entry->id,
                    'problem' => $this->entry->problem,
                    'solution' => $this->entry->solution,
                ]
            ]);
    }

    /**
     * Test updating an entry.
     */
    public function test_can_update_entry(): void
    {
        $updateData = [
            'problem' => 'Updated problem',
            'solution' => 'Updated solution',
            'area' => 'Updated area',
            'manual_override_prio' => 5,
        ];

        $response = $this->patchJson("/api/teams/{$this->team->id}/entries/{$this->entry->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure(['entry'])
            ->assertJson([
                'entry' => [
                    'id' => $this->entry->id,
                    'problem' => $updateData['problem'],
                    'solution' => $updateData['solution'],
                    'area' => $updateData['area'],
                    'manual_override_prio' => $updateData['manual_override_prio'],
                ]
            ]);

        $this->assertDatabaseHas('entries', [
            'id' => $this->entry->id,
            'problem' => $updateData['problem'],
            'solution' => $updateData['solution'],
            'area' => $updateData['area'],
            'manual_override_prio' => $updateData['manual_override_prio'],
        ]);
    }

    /**
     * Test deleting an entry.
     */
    public function test_can_delete_entry(): void
    {
        $response = $this->deleteJson("/api/teams/{$this->team->id}/entries/{$this->entry->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Entry deleted successfully'
            ]);

        // Since we're using soft deletes, the entry should still exist but have a deleted_at timestamp
        $this->assertSoftDeleted('entries', [
            'id' => $this->entry->id
        ]);
    }

    /**
     * Test that a user cannot access entries from a team they don't belong to.
     */
    public function test_cannot_access_entries_from_other_team(): void
    {
        // Create another team and entry
        $otherTeam = Team::factory()->create();
        $otherEntry = Entry::factory()->create([
            'team_id' => $otherTeam->id,
        ]);

        // Try to list entries
        $response = $this->getJson("/api/teams/{$otherTeam->id}/entries");
        $response->assertStatus(403);

        // Try to view a specific entry
        $response = $this->getJson("/api/teams/{$otherTeam->id}/entries/{$otherEntry->id}");
        $response->assertStatus(403);

        // Try to create an entry
        $response = $this->postJson("/api/teams/{$otherTeam->id}/entries", [
            'problem' => 'Test problem',
            'solution' => 'Test solution',
            'area' => 'Customer Service',
            'effort' => 'medium',
            'monetary_explanation' => 'Test explanation',
        ]);
        $response->assertStatus(403);

        // Try to update an entry
        $response = $this->patchJson("/api/teams/{$otherTeam->id}/entries/{$otherEntry->id}", [
            'problem' => 'Updated problem',
        ]);
        $response->assertStatus(403);

        // Try to delete an entry
        $response = $this->deleteJson("/api/teams/{$otherTeam->id}/entries/{$otherEntry->id}");
        $response->assertStatus(403);
    }
}
