<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Teams",
 *     description="API Endpoints for managing teams"
 * )
 */
class TeamController extends Controller
{
    /**
     * Display a listing of the teams the authenticated user belongs to.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams",
     *     summary="Get all teams for the authenticated user",
     *     description="Returns a list of all teams the authenticated user belongs to",
     *     operationId="getUserTeams",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="teams",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Team")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $teams = $user->teams()->with('founder')->get()->map(function ($team) use ($user) {
            $team->is_admin = $team->admins()->where('user_id', $user->id)->exists();
            return $team;
        });

        return response()->json(['teams' => $teams]);
    }

    /**
     * Join a team using a team code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/join",
     *     summary="Join a team using a team code",
     *     description="Allows a user to join a team using a team code",
     *     operationId="joinTeamByCode",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"team_code"},
     *             @OA\Property(property="team_code", type="string", example="DEV123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully joined the team",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully joined the team"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function joinByCode(Request $request): JsonResponse
    {
        $request->validate([
            'team_code' => 'required|string',
        ]);

        $team = Team::where('team_code', $request->team_code)->first();

        if (!$team) {
            return response()->json(['message' => 'Team not found'], 404);
        }

        $user = $request->user();

        // Check if user is already a member of the team
        if ($team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already a member of this team', 'team' => $team]);
        }

        // Add user to the team
        $team->users()->attach($user->id, ['is_admin' => false]);

        return response()->json([
            'message' => 'Successfully joined the team',
            'team' => $team
        ]);
    }

    /**
     * Join a team using an invite link token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $token
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams/join/{token}",
     *     summary="Join a team using an invite link token",
     *     description="Allows a user to join a team using an invite link token",
     *     operationId="joinTeamByLink",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Invite token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully joined the team",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully joined the team"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found or invite token expired"
     *     )
     * )
     */
    public function joinByLink(Request $request, string $token): JsonResponse
    {
        $team = Team::where('invite_token', $token)
            ->where(function ($query) {
                $query->whereNull('invite_expires_at')
                    ->orWhere('invite_expires_at', '>', now());
            })
            ->first();

        if (!$team) {
            return response()->json(['message' => 'Team not found or invite token expired'], 404);
        }

        $user = $request->user();

        // Check if user is already a member of the team
        if ($team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already a member of this team', 'team' => $team]);
        }

        // Add user to the team
        $team->users()->attach($user->id, ['is_admin' => false]);

        return response()->json([
            'message' => 'Successfully joined the team',
            'team' => $team
        ]);
    }

    /**
     * Accept a team invitation (for non-authenticated users).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/invite/accept",
     *     summary="Accept a team invitation",
     *     description="Allows a user to accept a team invitation using an invite token",
     *     operationId="acceptTeamInvite",
     *     tags={"Teams"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"invite_token"},
     *             @OA\Property(property="invite_token", type="string", example="unique_invite_token_string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invitation accepted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invitation accepted successfully"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found or invite token expired"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function acceptInvite(Request $request): JsonResponse
    {
        $request->validate([
            'invite_token' => 'required|string',
        ]);

        $team = Team::where('invite_token', $request->invite_token)
            ->where(function ($query) {
                $query->whereNull('invite_expires_at')
                    ->orWhere('invite_expires_at', '>', now());
            })
            ->first();

        if (!$team) {
            return response()->json(['message' => 'Team not found or invite token expired'], 404);
        }

        // If user is authenticated, add them to the team
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user is already a member of the team
            if ($team->users()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'You are already a member of this team', 'team' => $team]);
            }

            // Add user to the team
            $team->users()->attach($user->id, ['is_admin' => false]);

            return response()->json([
                'message' => 'Invitation accepted successfully',
                'team' => $team
            ]);
        }

        // If user is not authenticated, return team info so they can register/login
        return response()->json([
            'message' => 'Please register or login to join this team',
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'invite_token' => $team->invite_token
            ]
        ]);
    }

    /**
     * Leave a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Delete(
     *     path="/teams/{teamId}/leave",
     *     summary="Leave a team",
     *     description="Allows a user to leave a team they are a member of",
     *     operationId="leaveTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully left the team",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully left the team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Cannot leave a team you founded"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found or user is not a member"
     *     )
     * )
     */
    public function leave(Request $request, $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $user = $request->user();

        // Check if user is a member of the team
        if (!$team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are not a member of this team'], 404);
        }

        // Prevent the founder from leaving the team
        if ($team->founder_user_id === $user->id) {
            return response()->json(['message' => 'You cannot leave a team you founded. Transfer ownership or delete the team instead.'], 403);
        }

        // Remove user from the team
        $team->users()->detach($user->id);

        return response()->json(['message' => 'Successfully left the team']);
    }

    /**
     * Create a new team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams",
     *     summary="Create a new team",
     *     description="Creates a new team with the authenticated user as the founder",
     *     operationId="createTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "team_code"},
     *             @OA\Property(property="name", type="string", example="New Project Team"),
     *             @OA\Property(property="team_code", type="string", example="NPT789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Team created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'team_code' => 'required|string|max:50|unique:teams,team_code',
        ]);

        $user = $request->user();

        $team = Team::create([
            'name' => $request->name,
            'team_code' => $request->team_code,
            'invite_token' => \Illuminate\Support\Str::random(32),
            'invite_expires_at' => now()->addDays(7),
            'founder_user_id' => $user->id,
            'settings' => [
                'allow_anonymous_entries' => true,
                'require_approval' => false,
            ],
        ]);

        // Add the founder as an admin
        $team->users()->attach($user->id, ['is_admin' => true]);

        return response()->json(['team' => $team], 201);
    }

    /**
     * Generate a new invite link for a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/{teamId}/invite/generate",
     *     summary="Generate a new invite link",
     *     description="Generates a new invite link for a team (requires admin privileges)",
     *     operationId="generateTeamInvite",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="expires_in_days", type="integer", example=7, description="Number of days until the invite expires (default: 7)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invite link generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invite link generated successfully"),
     *             @OA\Property(property="invite_token", type="string", example="unique_invite_token_string"),
     *             @OA\Property(property="invite_link", type="string", example="https://example.com/teams/join/unique_invite_token_string"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2023-06-17T12:00:00.000000Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function generateInviteLink(Request $request, $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $user = $request->user();

        // Check if user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to generate invite links for this team'], 403);
        }

        $expiresInDays = $request->input('expires_in_days', 7);
        $inviteToken = \Illuminate\Support\Str::random(32);
        $expiresAt = now()->addDays($expiresInDays);

        $team->update([
            'invite_token' => $inviteToken,
            'invite_expires_at' => $expiresAt,
        ]);

        $inviteLink = url("/api/teams/join/{$inviteToken}");

        return response()->json([
            'message' => 'Invite link generated successfully',
            'invite_token' => $inviteToken,
            'invite_link' => $inviteLink,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Update the team name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Patch(
     *     path="/teams/{teamId}/name",
     *     summary="Update team name",
     *     description="Updates the name of a team (requires admin privileges)",
     *     operationId="updateTeamName",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Updated Team Name")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team name updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Team name updated successfully"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateName(Request $request, $teamId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $team = Team::findOrFail($teamId);
        $user = $request->user();

        // Check if user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to update this team\'s name'], 403);
        }

        $team->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Team name updated successfully',
            'team' => $team
        ]);
    }

    /**
     * Get or generate an invite link for a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams/{teamId}/invite-link",
     *     summary="Get team invite link",
     *     description="Returns an existing invite link or generates a new one if none exists or if expired",
     *     operationId="getTeamInviteLink",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invite link retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invite link retrieved successfully"),
     *             @OA\Property(property="invite_token", type="string", example="abc123xyz789"),
     *             @OA\Property(property="invite_link", type="string", example="https://example.com/api/teams/join/abc123xyz789"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2023-07-10T12:00:00.000000Z")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function getInviteLink(Request $request, $teamId): JsonResponse
    {
        $team = Team::findOrFail($teamId);
        $user = $request->user();

        // Check if user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to access invite links for this team'], 403);
        }

        // Check if a valid invite token already exists
        if ($team->invite_token && $team->invite_expires_at && $team->invite_expires_at->isFuture()) {
            $inviteLink = url("/api/teams/join/{$team->invite_token}");

            return response()->json([
                'message' => 'Invite link retrieved successfully',
                'invite_token' => $team->invite_token,
                'invite_link' => $inviteLink,
                'expires_at' => $team->invite_expires_at,
            ]);
        }

        // Generate a new invite token if none exists or if expired
        $inviteToken = \Illuminate\Support\Str::random(32);
        $expiresAt = now()->addDays(7); // Default expiration is 7 days

        $team->update([
            'invite_token' => $inviteToken,
            'invite_expires_at' => $expiresAt,
        ]);

        $inviteLink = url("/api/teams/join/{$inviteToken}");

        return response()->json([
            'message' => 'New invite link generated successfully',
            'invite_token' => $inviteToken,
            'invite_link' => $inviteLink,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Import JSON data to a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @param  string|null  $mode
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/{teamId}/import/{mode}",
     *     summary="Import JSON data to a team",
     *     description="Imports settings and entries from JSON data. Mode can be 'merge' (default) or 'overwrite'.",
     *     operationId="importTeamData",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="mode",
     *         in="path",
     *         description="Import mode: 'merge' or 'overwrite'",
     *         required=false,
     *         @OA\Schema(type="string", enum={"merge", "overwrite"}, default="merge")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="settings",
     *                 type="object",
     *                 @OA\Property(property="time_value", type="integer", example=500),
     *                 @OA\Property(property="multiplier", type="integer", example=2)
     *             ),
     *             @OA\Property(
     *                 property="mode",
     *                 type="object",
     *                 description="Additional mode information (optional)"
     *             ),
     *             @OA\Property(
     *                 property="ideas",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="problem", type="string", example="Manual reporting takes too long"),
     *                     @OA\Property(property="solution", type="string", example="Automate monthly reports with a simple script and templates."),
     *                     @OA\Property(property="area", type="string", example="Finance"),
     *                     @OA\Property(property="time_saved_per_year", type="integer", example=30),
     *                     @OA\Property(property="gross_profit_per_year", type="integer", example=0),
     *                     @OA\Property(property="effort", type="string", enum={"Low", "Medium", "High"}, example="Low"),
     *                     @OA\Property(property="monetary_explanation", type="string", example="Repetitive reports; scripting will save 2.5 days/month."),
     *                     @OA\Property(property="link", type="string", example="https://example.com/automation"),
     *                     @OA\Property(property="anonymous", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Data imported successfully"),
     *             @OA\Property(property="settings_updated", type="boolean", example=true),
     *             @OA\Property(property="entries_imported", type="integer", example=3),
     *             @OA\Property(property="entries_skipped", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function import(Request $request, $teamId, $mode = 'merge'): JsonResponse
    {
        // Validate the mode parameter
        if (!in_array($mode, ['merge', 'overwrite'])) {
            return response()->json(['message' => 'Invalid import mode. Mode must be either "merge" or "overwrite".'], 422);
        }

        // Validate the request data
        $request->validate([
            'settings' => 'sometimes|array',
            'settings.time_value' => 'sometimes|integer',
            'settings.multiplier' => 'sometimes|integer',
            'mode' => 'sometimes|array',
            'ideas' => 'required|array',
            'ideas.*.problem' => 'required|string',
            'ideas.*.solution' => 'required|string',
            'ideas.*.area' => 'required|string',
            'ideas.*.time_saved_per_year' => 'sometimes|nullable|integer',
            'ideas.*.gross_profit_per_year' => 'sometimes|nullable|integer',
            'ideas.*.effort' => 'required|string|in:Low,Medium,High,low,medium,high',
            'ideas.*.monetary_explanation' => 'required|string',
            'ideas.*.link' => 'sometimes|nullable|string',
            'ideas.*.anonymous' => 'sometimes|boolean',
        ]);

        $team = Team::findOrFail($teamId);
        $user = $request->user();

        // Check if user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to import data to this team'], 403);
        }

        // Initialize counters
        $entriesImported = 0;
        $entriesSkipped = 0;
        $settingsUpdated = false;

        // Update team settings if provided
        if ($request->has('settings')) {
            $team->update([
                'settings' => $request->settings,
            ]);
            $settingsUpdated = true;
        }

        // Get the entry service for calculating priorities
        $entryService = app(\App\Services\EntryService::class);

        // If mode is overwrite, delete all existing entries
        if ($mode === 'overwrite') {
            $team->entries()->delete();
        }

        // Import entries
        foreach ($request->ideas as $idea) {
            // Normalize effort value (ensure lowercase)
            $effort = strtolower($idea['effort']);

            // For merge mode, check if entry already exists based on problem text
            if ($mode === 'merge') {
                $existingEntry = $team->entries()->where('problem', $idea['problem'])->first();
                if ($existingEntry) {
                    $entriesSkipped++;
                    continue;
                }
            }

            // Prepare entry data
            $entryData = [
                'problem' => $idea['problem'],
                'solution' => $idea['solution'],
                'area' => $idea['area'],
                'time_saved_per_year' => $idea['time_saved_per_year'] ?? null,
                'gross_profit_per_year' => $idea['gross_profit_per_year'] ?? null,
                'effort' => $effort,
                'monetary_explanation' => $idea['monetary_explanation'],
                'link' => $idea['link'] ?? null,
                'anonymous' => $idea['anonymous'] ?? false,
                'manual_override_prio' => 0, // Default value
            ];

            // Calculate final priority
            $finalPrio = $entryService->calculatePriority($entryData);
            $entryData['final_prio'] = $finalPrio;

            // Add team_id and user_id
            $entryData['team_id'] = $team->id;
            $entryData['user_id'] = $user->id;

            // Create the entry
            $team->entries()->create($entryData);
            $entriesImported++;
        }

        return response()->json([
            'message' => 'Data imported successfully',
            'settings_updated' => $settingsUpdated,
            'entries_imported' => $entriesImported,
            'entries_skipped' => $entriesSkipped,
        ]);
    }

    /**
     * Display the specified team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $teamId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/teams/{teamId}",
     *     summary="Get a specific team by ID",
     *     description="Returns details of a specific team. Only accessible to team members.",
     *     operationId="getTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="teamId",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not a member of this team"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function show(Request $request, $teamId): JsonResponse
    {
        $team = Team::with('founder')->findOrFail($teamId);
        $user = $request->user();

        // Check if user is a member of the team
        if (!$team->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to view this team'], 403);
        }

        // Add is_admin flag to the response
        $team->is_admin = $team->admins()->where('user_id', $user->id)->exists();

        return response()->json(['team' => $team]);
    }

    /**
     * Delete a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $team
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Delete(
     *     path="/teams/{team}",
     *     summary="Delete a team",
     *     description="Deletes a team. Only accessible to team admins.",
     *     operationId="deleteTeam",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Team deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin of this team"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     )
     * )
     */
    public function destroy(Request $request, $team): JsonResponse
    {
        $team = Team::findOrFail($team);
        $user = $request->user();

        // Check if user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to delete this team'], 403);
        }

        // Delete the team
        $team->delete();

        return response()->json(['message' => 'Team deleted successfully']);
    }

    /**
     * Update team settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $team
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Patch(
     *     path="/teams/{team}/settings",
     *     summary="Update team settings",
     *     description="Updates the settings for a team. Only accessible to team admins.",
     *     operationId="updateTeamSettings",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="settings", type="object", example={"allow_anonymous_entries": true, "require_approval": false})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Team settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Team settings updated successfully"),
     *             @OA\Property(property="team", ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin of this team"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateSettings(Request $request, $team): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        $team = Team::findOrFail($team);
        $user = $request->user();

        // Check if user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to update this team\'s settings'], 403);
        }

        // Update the team settings
        $team->update([
            'settings' => $request->settings,
        ]);

        return response()->json([
            'message' => 'Team settings updated successfully',
            'team' => $team
        ]);
    }

    /**
     * Add an admin to a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $team
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/{team}/admins/add",
     *     summary="Add an admin to a team",
     *     description="Adds a user as an admin to a team. Only accessible to existing team admins.",
     *     operationId="addTeamAdmin",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User added as admin successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User added as admin successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin of this team"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team or user not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function addAdmin(Request $request, $team): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $team = Team::findOrFail($team);
        $user = $request->user();
        $targetUser = User::findOrFail($request->user_id);

        // Check if the authenticated user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to add admins to this team'], 403);
        }

        // Check if the target user is a member of the team
        if (!$team->users()->where('user_id', $targetUser->id)->exists()) {
            return response()->json(['message' => 'The user is not a member of this team'], 422);
        }

        // Make the user an admin
        $team->users()->updateExistingPivot($targetUser->id, ['is_admin' => true]);

        return response()->json(['message' => 'User added as admin successfully']);
    }

    /**
     * Remove an admin from a team.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $team
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/teams/{team}/admins/remove",
     *     summary="Remove an admin from a team",
     *     description="Removes admin privileges from a user in a team. Only accessible to existing team admins.",
     *     operationId="removeTeamAdmin",
     *     tags={"Teams"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="team",
     *         in="path",
     *         description="ID of the team",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Admin privileges removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Admin privileges removed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User is not an admin of this team"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Team or user not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or cannot remove the founder as admin"
     *     )
     * )
     */
    public function removeAdmin(Request $request, $team): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $team = Team::findOrFail($team);
        $user = $request->user();
        $targetUser = User::findOrFail($request->user_id);

        // Check if the authenticated user is an admin of the team
        if (!$team->admins()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You do not have permission to remove admins from this team'], 403);
        }

        // Check if the target user is the founder (cannot remove founder as admin)
        if ($team->founder_user_id === $targetUser->id) {
            return response()->json(['message' => 'Cannot remove the team founder as admin'], 422);
        }

        // Check if the target user is an admin of the team
        if (!$team->admins()->where('user_id', $targetUser->id)->exists()) {
            return response()->json(['message' => 'The user is not an admin of this team'], 422);
        }

        // Remove admin privileges
        $team->users()->updateExistingPivot($targetUser->id, ['is_admin' => false]);

        return response()->json(['message' => 'Admin privileges removed successfully']);
    }
}
