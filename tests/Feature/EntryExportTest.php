<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntryExportTest extends TestCase
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

        // Create entries for the team by the user
        Entry::factory()->count(5)->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
        ]);

        // Authenticate the user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test exporting entries as CSV.
     */
    public function test_can_export_entries_as_csv(): void
    {
        $response = $this->get("/api/teams/{$this->team->id}/entries/export");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename=entries.csv');

        // Verify the CSV content contains the expected headers
        $content = $response->getContent();
        $this->assertStringContainsString('ID,Problem,Solution,Area', $content);

        // Verify all entries are included
        $entries = Entry::where('team_id', $this->team->id)->get();
        foreach ($entries as $entry) {
            $this->assertStringContainsString($entry->problem, $content);
            $this->assertStringContainsString($entry->solution, $content);
        }
    }

    /**
     * Test that a non-team member cannot export entries.
     */
    public function test_non_team_member_cannot_export_entries(): void
    {
        // Create another user
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->get("/api/teams/{$this->team->id}/entries/export");
        $response->assertStatus(403);
    }
}
