<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Authenticate the user
        Sanctum::actingAs($this->user);
    }

    /**
     * Test creating a new team.
     */
    public function test_can_create_team(): void
    {
        $teamData = [
            'name' => 'Test Team',
            'team_code' => 'TEST123',
        ];

        $response = $this->postJson('/api/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonStructure(['team'])
            ->assertJson([
                'team' => [
                    'name' => $teamData['name'],
                    'team_code' => $teamData['team_code'],
                    'founder_user_id' => $this->user->id,
                ]
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => $teamData['name'],
            'team_code' => $teamData['team_code'],
            'founder_user_id' => $this->user->id,
        ]);

        // Verify the user is attached to the team as an admin
        $team = Team::where('team_code', $teamData['team_code'])->first();
        $this->assertTrue($team->users()->where('user_id', $this->user->id)->exists());
        $this->assertTrue((bool)$team->users()->where('user_id', $this->user->id)->first()->pivot->is_admin);
    }

    /**
     * Test joining a team using a team code.
     */
    public function test_can_join_team_by_code(): void
    {
        // Create a team with another user as founder
        $founder = User::factory()->create();
        $team = Team::factory()->create([
            'founder_user_id' => $founder->id,
        ]);
        $team->users()->attach($founder, ['is_admin' => true]);

        $response = $this->postJson('/api/teams/join', [
            'team_code' => $team->team_code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'team'])
            ->assertJson([
                'message' => 'Successfully joined the team',
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_code' => $team->team_code,
                ]
            ]);

        // Verify the user is attached to the team as a regular member
        $this->assertTrue($team->users()->where('user_id', $this->user->id)->exists());
        $this->assertFalse((bool)$team->users()->where('user_id', $this->user->id)->first()->pivot->is_admin);
    }

    /**
     * Test that a team admin can add another user as an admin.
     */
    public function test_admin_can_add_another_admin(): void
    {
        // Create a team with the user as founder
        $team = Team::factory()->create([
            'founder_user_id' => $this->user->id,
        ]);
        $team->users()->attach($this->user, ['is_admin' => true]);

        // Create another user and add them to the team
        $otherUser = User::factory()->create();
        $team->users()->attach($otherUser, ['is_admin' => false]);

        $response = $this->postJson("/api/teams/{$team->id}/admins/add", [
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User added as admin successfully',
            ]);

        // Verify the other user is now an admin
        $this->assertTrue((bool)$team->users()->where('user_id', $otherUser->id)->first()->pivot->is_admin);
    }

    /**
     * Test that a team admin can remove another admin.
     */
    public function test_admin_can_remove_another_admin(): void
    {
        // Create a team with the user as founder
        $team = Team::factory()->create([
            'founder_user_id' => $this->user->id,
        ]);
        $team->users()->attach($this->user, ['is_admin' => true]);

        // Create another user and add them as an admin
        $otherUser = User::factory()->create();
        $team->users()->attach($otherUser, ['is_admin' => true]);

        $response = $this->postJson("/api/teams/{$team->id}/admins/remove", [
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Admin privileges removed successfully',
            ]);

        // Verify the other user is no longer an admin
        $this->assertFalse((bool)$team->users()->where('user_id', $otherUser->id)->first()->pivot->is_admin);
    }

    /**
     * Test that a team admin can update team settings.
     */
    public function test_admin_can_update_team_settings(): void
    {
        // Create a team with the user as founder
        $team = Team::factory()->create([
            'founder_user_id' => $this->user->id,
            'settings' => json_encode(['day_value' => 500, 'multiplier' => 2]),
        ]);
        $team->users()->attach($this->user, ['is_admin' => true]);

        $newSettings = [
            'settings' => [
                'day_value' => 600,
                'multiplier' => 3,
            ],
        ];

        $response = $this->patchJson("/api/teams/{$team->id}/settings", $newSettings);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'team'])
            ->assertJson([
                'message' => 'Team settings updated successfully',
            ]);

        // Verify the settings were updated
        $team->refresh();
        $settings = is_array($team->settings) ? $team->settings : json_decode($team->settings, true);
        $this->assertEquals(600, $settings['day_value']);
        $this->assertEquals(3, $settings['multiplier']);
    }

    /**
     * Test that a regular team member cannot perform admin actions.
     */
    public function test_regular_member_cannot_perform_admin_actions(): void
    {
        // Create a team with another user as founder
        $founder = User::factory()->create();
        $team = Team::factory()->create([
            'founder_user_id' => $founder->id,
        ]);
        $team->users()->attach($founder, ['is_admin' => true]);
        $team->users()->attach($this->user, ['is_admin' => false]);

        // Try to add an admin
        $otherUser = User::factory()->create();
        $team->users()->attach($otherUser, ['is_admin' => false]);

        $response = $this->postJson("/api/teams/{$team->id}/admins/add", [
            'user_id' => $otherUser->id,
        ]);
        $response->assertStatus(403);

        // Try to update team settings
        $response = $this->patchJson("/api/teams/{$team->id}/settings", [
            'settings' => [
                'day_value' => 600,
                'multiplier' => 3,
            ],
        ]);
        $response->assertStatus(403);
    }
}
