<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="Entry",
 *     title="Entry",
 *     description="Entry model representing a brainstorming idea",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="Unique identifier"),
 *     @OA\Property(property="team_id", type="integer", format="int64", example=1, description="ID of the team this entry belongs to"),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1, description="ID of the user who created this entry"),
 *     @OA\Property(property="problem", type="string", example="Slow customer response time", description="Description of the problem"),
 *     @OA\Property(property="solution", type="string", example="Implement automated response system", description="Proposed solution to the problem"),
 *     @OA\Property(property="area", type="string", example="Customer Service", description="Business area affected by the problem/solution"),
 *     @OA\Property(property="time_saved_per_year", type="integer", nullable=true, example=500, description="Estimated time saved per year in hours"),
 *     @OA\Property(property="gross_profit_per_year", type="integer", nullable=true, example=10000, description="Estimated gross profit increase per year"),
 *     @OA\Property(property="effort", type="string", enum={"low", "medium", "high"}, example="medium", description="Estimated effort to implement the solution"),
 *     @OA\Property(property="monetary_explanation", type="string", example="Saves 500 hours per year at $20/hour", description="Explanation of the monetary benefits"),
 *     @OA\Property(property="link", type="string", nullable=true, example="https://example.com/docs", description="Optional link to additional information"),
 *     @OA\Property(property="anonymous", type="boolean", example=false, description="Whether the entry should be displayed anonymously"),
 *     @OA\Property(property="manual_override_prio", type="integer", example=0, description="Manual priority override value"),
 *     @OA\Property(property="final_prio", type="integer", example=15, description="Final calculated priority of the entry"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z", description="Last update timestamp"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null, description="Soft delete timestamp"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         nullable=true,
 *         description="User who created the entry (null if anonymous)",
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="nickname", type="string", nullable=true, example="Johnny")
 *     )
 * )
 */
class Entry extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'user_id',
        'problem',
        'solution',
        'area',
        'time_saved_per_year',
        'gross_profit_per_year',
        'effort',
        'monetary_explanation',
        'link',
        'anonymous',
        'manual_override_prio',
        'final_prio',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'anonymous' => 'boolean',
        'time_saved_per_year' => 'integer',
        'gross_profit_per_year' => 'integer',
        'manual_override_prio' => 'integer',
        'final_prio' => 'integer',
    ];

    /**
     * Get the team that owns the entry.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that owns the entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
