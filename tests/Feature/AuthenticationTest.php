<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test user registration.
     */
    public function test_user_can_register(): void
    {
        Notification::fake();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nickname' => 'Tester',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'nickname',
                    'anonymous',
                    'created_at',
                    'updated_at',
                ],
                'access_token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'name' => $userData['name'],
            'email' => $userData['email'],
            'nickname' => $userData['nickname'],
        ]);

        // Verify that a verification email was sent
        Notification::assertSentTo(
            User::where('email', $userData['email'])->first(),
            VerifyEmail::class
        );
    }

    /**
     * Test user login.
     */
    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'nickname',
                    'anonymous',
                    'created_at',
                    'updated_at',
                ],
                'access_token',
                'token_type',
            ]);
    }

    /**
     * Test login with invalid credentials.
     */
    public function test_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test user logout.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out',
            ]);

        // Verify the token was deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }

    /**
     * Test password reset request.
     */
    public function test_can_request_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset link sent to your email',
            ]);

        // Verify that a password reset notification was sent
        Notification::assertSentTo(
            $user,
            \Illuminate\Auth\Notifications\ResetPassword::class
        );
    }

    /**
     * Test accessing protected routes without authentication.
     */
    public function test_cannot_access_protected_routes_without_auth(): void
    {
        // Try to access user profile
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);

        // Try to access teams
        $response = $this->getJson('/api/teams');
        $response->assertStatus(401);
    }

    /**
     * Test email verification.
     */
    public function test_email_verification(): void
    {
        Notification::fake();

        // Create a user
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Generate a verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Extract the URL parameters
        $urlParts = parse_url($verificationUrl);
        parse_str($urlParts['query'] ?? '', $query);

        // Get the hash from the route parameters
        $hash = sha1($user->email);

        // Make sure expires and signature are set
        $expires = isset($query['expires']) ? $query['expires'] : '';
        $signature = isset($query['signature']) ? $query['signature'] : '';

        // Make the request to the verification endpoint
        $response = $this->get("/api/email/verify/{$user->id}/{$hash}?expires={$expires}&signature={$signature}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully',
            ]);

        // Verify the user's email is now verified
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
