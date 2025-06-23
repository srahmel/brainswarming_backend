<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;
use App\Models\Team;
use Illuminate\Auth\Access\HandlesAuthorization;

class EntryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any entries.
     *
     * @param  \App\Models\User  $user
     * @param  int  $teamId
     * @return bool
     */
    public function viewAny(User $user, $teamId)
    {
        return $user->teams->contains($teamId);
    }

    /**
     * Determine whether the user can view the entry.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Entry  $entry
     * @return bool
     */
    public function view(User $user, Entry $entry)
    {
        return $user->teams->contains($entry->team_id);
    }

    /**
     * Determine whether the user can create entries.
     *
     * @param  \App\Models\User  $user
     * @param  int  $teamId
     * @return bool
     */
    public function create(User $user, $teamId)
    {
        return $user->teams->contains($teamId);
    }

    /**
     * Determine whether the user can update the entry.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Entry  $entry
     * @return bool
     */
    public function update(User $user, Entry $entry)
    {
        // User can update if they are the owner of the entry or an admin of the team
        if ($entry->user_id === $user->id) {
            return true;
        }

        return $user->teams()
            ->where('team_id', $entry->team_id)
            ->wherePivot('is_admin', true)
            ->exists();
    }

    /**
     * Determine whether the user can delete the entry.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Entry  $entry
     * @return bool
     */
    public function delete(User $user, Entry $entry)
    {
        // User can delete if they are the owner of the entry or an admin of the team
        if ($entry->user_id === $user->id) {
            return true;
        }

        return $user->teams()
            ->where('team_id', $entry->team_id)
            ->wherePivot('is_admin', true)
            ->exists();
    }

    /**
     * Determine whether the user can restore the entry.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Entry  $entry
     * @return bool
     */
    public function restore(User $user, Entry $entry)
    {
        // User can restore if they are the owner of the entry or an admin of the team
        if ($entry->user_id === $user->id) {
            return true;
        }

        return $user->teams()
            ->where('team_id', $entry->team_id)
            ->wherePivot('is_admin', true)
            ->exists();
    }

    /**
     * Determine whether the user can permanently delete the entry.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Entry  $entry
     * @return bool
     */
    public function forceDelete(User $user, Entry $entry)
    {
        // Only team admins can permanently delete entries
        return $user->teams()
            ->where('team_id', $entry->team_id)
            ->wherePivot('is_admin', true)
            ->exists();
    }
}
