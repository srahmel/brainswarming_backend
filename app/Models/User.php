<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="User",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="Unique identifier"),
 *     @OA\Property(property="name", type="string", example="John Doe", description="Full name of the user"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com", description="Email address"),
 *     @OA\Property(property="nickname", type="string", nullable=true, example="Johnny", description="Optional nickname"),
 *     @OA\Property(property="anonymous", type="boolean", example=false, description="Whether the user prefers to be anonymous by default"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2023-06-10T12:00:00.000000Z", description="Email verification timestamp"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z", description="Last update timestamp")
 * )
 */
class User extends Authenticatable implements CanResetPasswordContract, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, CanResetPassword, MustVerifyEmailTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nickname',
        'anonymous',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'anonymous' => 'boolean',
        ];
    }

    /**
     * The teams that the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('is_admin')
            ->withTimestamps();
    }

    /**
     * The entries that the user has created.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * The teams that the user is an admin of.
     */
    public function adminTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->wherePivot('is_admin', true)
            ->withTimestamps();
    }
}
