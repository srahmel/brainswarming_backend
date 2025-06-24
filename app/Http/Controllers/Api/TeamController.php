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
}
