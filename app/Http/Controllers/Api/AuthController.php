<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

/**
 * Authentication controller for handling user registration, login, and logout.
 */
class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="securepassword"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="securepassword"),
     *             @OA\Property(property="nickname", type="string", nullable=true, example="Johnny"),
     *             @OA\Property(property="invite_token", type="string", nullable=true, example="unique_invite_token_string", description="Team invite token to automatically join a team after registration")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="nickname", type="string", example="Johnny"),
     *                 @OA\Property(property="anonymous", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z")
     *             ),
     *             @OA\Property(property="access_token", type="string", example="1|laravel_sanctum_token_example"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="team", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Development Team"),
     *                 @OA\Property(property="team_code", type="string", example="DEV123")
     *             ),
     *             @OA\Property(property="message", type="string", example="User registered and joined team successfully", description="Message indicating whether the user joined an existing team or a new team was created", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'nickname' => 'nullable|string|max:255',
            'anonymous' => 'boolean',
            'invite_token' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nickname' => $request->nickname,
            'anonymous' => $request->anonymous ?? false,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $teamInfo = null;

        // Check if invite token is provided and valid
        if ($request->has('invite_token') && $request->invite_token) {
            $team = Team::where('invite_token', $request->invite_token)
                ->where(function ($query) {
                    $query->whereNull('invite_expires_at')
                        ->orWhere('invite_expires_at', '>', now());
                })
                ->first();

            if ($team) {
                // Add user to the team
                $team->users()->attach($user->id, ['is_admin' => false]);
                $teamInfo = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_code' => $team->team_code,
                ];
                $message = 'User registered and joined team successfully';
            }
        } else {
            // If no invite token, create a team based on user's nickname
            $nickname = $request->nickname ?: $request->name;
            $teamName = $nickname . "'s Team";

            // Generate a unique team code
            $teamCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nickname), 0, 5) . rand(100, 999));

            // Create the team
            $team = Team::create([
                'name' => $teamName,
                'team_code' => $teamCode,
                'invite_token' => \Illuminate\Support\Str::random(32),
                'invite_expires_at' => now()->addDays(7),
                'founder_user_id' => $user->id,
                'settings' => [
                    'allow_anonymous_entries' => true,
                    'require_approval' => false,
                ],
            ]);

            // Add the user as an admin of the team
            $team->users()->attach($user->id, ['is_admin' => true]);

            $teamInfo = [
                'id' => $team->id,
                'name' => $team->name,
                'team_code' => $team->team_code,
            ];
            $message = 'User registered and team created successfully';
        }

        $response = [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];

        if ($teamInfo) {
            $response['team'] = $teamInfo;
            $response['message'] = $message;
        }

        return response()->json($response, 201);
    }

    /**
     * Login a user and create a token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/login",
     *     summary="Authenticate a user and get a token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="securepassword")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User authenticated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="nickname", type="string", example="Johnny"),
     *                 @OA\Property(property="anonymous", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-06-10T12:00:00.000000Z")
     *             ),
     *             @OA\Property(property="access_token", type="string", example="1|laravel_sanctum_token_example"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout a user and revoke token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/logout",
     *     summary="Logout a user and revoke token",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Get CSRF token for SPA.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/csrf-token",
     *     summary="Get a CSRF token for SPA authentication",
     *     tags={"Authentication"},
     *     @OA\Response(
     *         response=200,
     *         description="CSRF token retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="csrf_token", type="string", example="example_csrf_token_string")
     *         )
     *     )
     * )
     */
    public function getCsrfToken(): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }
}
