<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntryRestoreTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $regularUser;
    protected User $nonTeamUser;
    protected Team $team;
    protected Entry $adminEntry;
    protected Entry $regularEntry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->adminUser = User::factory()->create();
        $this->regularUser = User::factory()->create();
        $this->nonTeamUser = User::factory()->create();

        // Create a team with the admin user as founder
        $this->team = Team::factory()->create([
            'founder_user_id' => $this->adminUser->id,
        ]);

        // Attach users to the team
        $this->team->users()->attach($this->adminUser, ['is_admin' => true]);
        $this->team->users()->attach($this->regularUser, ['is_admin' => false]);

        // Create entries for the team
        $this->adminEntry = Entry::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->adminUser->id,
        ]);

        $this->regularEntry = Entry::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->regularUser->id,
        ]);

        // Soft delete the entries
        $this->adminEntry->delete();
        $this->regularEntry->delete();
    }

    /**
     * Test that an admin can restore any deleted entry.
     */
    public function test_admin_can_restore_any_entry(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Restore admin's own entry
        $response = $this->postJson("/api/teams/{$this->team->id}/entries/{$this->adminEntry->id}/restore");
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Entry restored successfully',
            ]);

        $this->assertDatabaseHas('entries', [
            'id' => $this->adminEntry->id,
            'deleted_at' => null,
        ]);

        // Restore regular user's entry
        $response = $this->postJson("/api/teams/{$this->team->id}/entries/{$this->regularEntry->id}/restore");
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Entry restored successfully',
            ]);

        $this->assertDatabaseHas('entries', [
            'id' => $this->regularEntry->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that a regular user can restore their own deleted entry.
     */
    public function test_regular_user_can_restore_own_entry(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Restore their own entry
        $response = $this->postJson("/api/teams/{$this->team->id}/entries/{$this->regularEntry->id}/restore");
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Entry restored successfully',
            ]);

        $this->assertDatabaseHas('entries', [
            'id' => $this->regularEntry->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that a regular user cannot restore another user's deleted entry.
     */
    public function test_regular_user_cannot_restore_others_entry(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Try to restore admin's entry
        $response = $this->postJson("/api/teams/{$this->team->id}/entries/{$this->adminEntry->id}/restore");
        $response->assertStatus(403);

        $this->assertSoftDeleted('entries', [
            'id' => $this->adminEntry->id,
        ]);
    }

    /**
     * Test that a non-team user cannot restore any deleted entry.
     */
    public function test_non_team_user_cannot_restore_any_entry(): void
    {
        Sanctum::actingAs($this->nonTeamUser);

        // Try to restore admin's entry
        $response = $this->postJson("/api/teams/{$this->team->id}/entries/{$this->adminEntry->id}/restore");
        $response->assertStatus(403);

        // Try to restore regular user's entry
        $response = $this->postJson("/api/teams/{$this->team->id}/entries/{$this->regularEntry->id}/restore");
        $response->assertStatus(403);

        $this->assertSoftDeleted('entries', [
            'id' => $this->adminEntry->id,
        ]);
        $this->assertSoftDeleted('entries', [
            'id' => $this->regularEntry->id,
        ]);
    }
}
