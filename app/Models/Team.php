<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="Team",
 *     title="Team",
 *     description="Team model for brainswarming groups",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="Unique identifier"),
 *     @OA\Property(property="name", type="string", example="Innovation Team", description="Name of the team"),
 *     @OA\Property(property="team_code", type="string", example="INNOV2023", description="Unique code for joining the team"),
 *     @OA\Property(property="invite_token", type="string", example="abc123xyz789", description="Token for inviting users to the team"),
 *     @OA\Property(property="invite_expires_at", type="string", format="date-time", nullable=true, example="2023-07-10T12:00:00.000000Z", description="Expiration date for the invite token"),
 *     @OA\Property(property="founder_user_id", type="integer", format="int64", example=1, description="ID of the user who created the team"),
 *     @OA\Property(property="settings", type="object", nullable=true, description="Team settings as JSON"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z", description="Last update timestamp"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null, description="Soft delete timestamp")
 * )
 */
class Team extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'team_code',
        'invite_token',
        'invite_expires_at',
        'founder_user_id',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'json',
        'invite_expires_at' => 'datetime',
    ];

    /**
     * Get the founder of the team.
     */
    public function founder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'founder_user_id');
    }

    /**
     * Get the users that belong to the team.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    /**
     * Get the admin users of the team.
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('is_admin', true)
            ->withTimestamps();
    }

    /**
     * Get the entries for the team.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
