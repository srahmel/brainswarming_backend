<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        // Send email verification notification
        $user->sendEmailVerificationNotification();

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

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Create a new token for the user
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
     * Send a password reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/forgot-password",
     *     summary="Send a password reset link",
     *     description="Sends a password reset link to the user's email address",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset link sent to your email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Password reset link sent to your email'])
            : response()->json(['message' => __($status)], 400);
    }

    /**
     * Reset the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/reset-password",
     *     summary="Reset password",
     *     description="Resets the user's password using the token received in email",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token", "email", "password", "password_confirmation"},
     *             @OA\Property(property="token", type="string", example="1234567890abcdef1234567890abcdef"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your password has been reset")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    /**
     * Verify the user's email address.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/email/verify/{id}/{hash}",
     *     summary="Verify email address",
     *     description="Verifies the user's email address using the link sent to their email",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         description="Email verification hash",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="expires",
     *         in="query",
     *         description="Expiration timestamp",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         description="URL signature",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid verification link"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return response()->json(['message' => 'Email verified successfully']);
    }

    /**
     * Resend the email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/email/verification-notification",
     *     summary="Resend verification email",
     *     description="Resends the email verification notification",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Verification link sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification link sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent']);
    }

}
