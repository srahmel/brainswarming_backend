<?php

namespace Tests\Unit;

use App\Models\Entry;
use App\Models\Team;
use App\Models\User;
use App\Policies\EntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected EntryPolicy $policy;
    protected User $teamAdmin;
    protected User $teamMember;
    protected User $nonTeamMember;
    protected Team $team;
    protected Entry $adminEntry;
    protected Entry $memberEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EntryPolicy();

        // Create users
        $this->teamAdmin = User::factory()->create();
        $this->teamMember = User::factory()->create();
        $this->nonTeamMember = User::factory()->create();

        // Create a team
        $this->team = Team::factory()->create([
            'founder_user_id' => $this->teamAdmin->id,
        ]);

        // Attach users to the team
        $this->team->users()->attach($this->teamAdmin, ['is_admin' => true]);
        $this->team->users()->attach($this->teamMember, ['is_admin' => false]);

        // Create entries
        $this->adminEntry = Entry::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->teamAdmin->id,
        ]);

        $this->memberEntry = Entry::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->teamMember->id,
        ]);
    }

    /**
     * Test viewAny policy method.
     */
    public function test_view_any_policy(): void
    {
        // Team members can view entries
        $this->assertTrue($this->policy->viewAny($this->teamAdmin, $this->team->id));
        $this->assertTrue($this->policy->viewAny($this->teamMember, $this->team->id));

        // Non-team members cannot view entries
        $this->assertFalse($this->policy->viewAny($this->nonTeamMember, $this->team->id));
    }

    /**
     * Test view policy method.
     */
    public function test_view_policy(): void
    {
        // Team members can view entries
        $this->assertTrue($this->policy->view($this->teamAdmin, $this->adminEntry));
        $this->assertTrue($this->policy->view($this->teamAdmin, $this->memberEntry));
        $this->assertTrue($this->policy->view($this->teamMember, $this->adminEntry));
        $this->assertTrue($this->policy->view($this->teamMember, $this->memberEntry));

        // Non-team members cannot view entries
        $this->assertFalse($this->policy->view($this->nonTeamMember, $this->adminEntry));
        $this->assertFalse($this->policy->view($this->nonTeamMember, $this->memberEntry));
    }

    /**
     * Test create policy method.
     */
    public function test_create_policy(): void
    {
        // Team members can create entries
        $this->assertTrue($this->policy->create($this->teamAdmin, $this->team->id));
        $this->assertTrue($this->policy->create($this->teamMember, $this->team->id));

        // Non-team members cannot create entries
        $this->assertFalse($this->policy->create($this->nonTeamMember, $this->team->id));
    }

    /**
     * Test update policy method.
     */
    public function test_update_policy(): void
    {
        // Entry owners can update their entries
        $this->assertTrue($this->policy->update($this->teamAdmin, $this->adminEntry));
        $this->assertTrue($this->policy->update($this->teamMember, $this->memberEntry));

        // Team admins can update any entry in their team
        $this->assertTrue($this->policy->update($this->teamAdmin, $this->memberEntry));

        // Regular team members cannot update entries they don't own
        $this->assertFalse($this->policy->update($this->teamMember, $this->adminEntry));

        // Non-team members cannot update any entries
        $this->assertFalse($this->policy->update($this->nonTeamMember, $this->adminEntry));
        $this->assertFalse($this->policy->update($this->nonTeamMember, $this->memberEntry));
    }

    /**
     * Test delete policy method.
     */
    public function test_delete_policy(): void
    {
        // Entry owners can delete their entries
        $this->assertTrue($this->policy->delete($this->teamAdmin, $this->adminEntry));
        $this->assertTrue($this->policy->delete($this->teamMember, $this->memberEntry));

        // Team admins can delete any entry in their team
        $this->assertTrue($this->policy->delete($this->teamAdmin, $this->memberEntry));

        // Regular team members cannot delete entries they don't own
        $this->assertFalse($this->policy->delete($this->teamMember, $this->adminEntry));

        // Non-team members cannot delete any entries
        $this->assertFalse($this->policy->delete($this->nonTeamMember, $this->adminEntry));
        $this->assertFalse($this->policy->delete($this->nonTeamMember, $this->memberEntry));
    }

    /**
     * Test restore policy method.
     */
    public function test_restore_policy(): void
    {
        // Entry owners can restore their entries
        $this->assertTrue($this->policy->restore($this->teamAdmin, $this->adminEntry));
        $this->assertTrue($this->policy->restore($this->teamMember, $this->memberEntry));

        // Team admins can restore any entry in their team
        $this->assertTrue($this->policy->restore($this->teamAdmin, $this->memberEntry));

        // Regular team members cannot restore entries they don't own
        $this->assertFalse($this->policy->restore($this->teamMember, $this->adminEntry));

        // Non-team members cannot restore any entries
        $this->assertFalse($this->policy->restore($this->nonTeamMember, $this->adminEntry));
        $this->assertFalse($this->policy->restore($this->nonTeamMember, $this->memberEntry));
    }

    /**
     * Test forceDelete policy method.
     */
    public function test_force_delete_policy(): void
    {
        // Only team admins can force delete entries
        $this->assertTrue($this->policy->forceDelete($this->teamAdmin, $this->adminEntry));
        $this->assertTrue($this->policy->forceDelete($this->teamAdmin, $this->memberEntry));

        // Regular team members cannot force delete any entries, even their own
        $this->assertFalse($this->policy->forceDelete($this->teamMember, $this->memberEntry));
        $this->assertFalse($this->policy->forceDelete($this->teamMember, $this->adminEntry));

        // Non-team members cannot force delete any entries
        $this->assertFalse($this->policy->forceDelete($this->nonTeamMember, $this->adminEntry));
        $this->assertFalse($this->policy->forceDelete($this->nonTeamMember, $this->memberEntry));
    }
}
