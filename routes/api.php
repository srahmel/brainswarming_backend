<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public endpoints
Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/forgot-password', [\App\Http\Controllers\Api\AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [\App\Http\Controllers\Api\AuthController::class, 'resetPassword']);

// Email verification routes
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Api\AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

// Public email verification notification route
Route::post('/email/verification-notification/public', [\App\Http\Controllers\Api\AuthController::class, 'resendVerificationEmailPublic']);

Route::post('/teams/invite/accept', [\App\Http\Controllers\Api\TeamController::class, 'acceptInvite']);

// Swagger documentation route
Route::get('/documentation', function () {
    return view('swagger');
});

// Authenticated endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [\App\Http\Controllers\Api\UserController::class, 'getCurrentUser']);

    // Logout route
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);

    // Email verification resend route
    Route::post('/email/verification-notification', [\App\Http\Controllers\Api\AuthController::class, 'resendVerificationEmail'])
        ->name('verification.send');

    // Routes that require email verification
    //Route::middleware('verified')->group(function () {
        // Teams routes
        Route::get('/teams', [\App\Http\Controllers\Api\TeamController::class, 'index']);
        Route::get('/teams/{teamId}', [\App\Http\Controllers\Api\TeamController::class, 'show']);
        Route::post('/teams', [\App\Http\Controllers\Api\TeamController::class, 'store']);
        Route::post('/teams/join', [\App\Http\Controllers\Api\TeamController::class, 'joinByCode']);
        Route::get('/teams/join/{token}', [\App\Http\Controllers\Api\TeamController::class, 'joinByLink']);
        Route::delete('/teams/{teamId}/leave', [\App\Http\Controllers\Api\TeamController::class, 'leave']);

        Route::delete('/teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'destroy']);

        Route::patch('/teams/{team}/settings', [\App\Http\Controllers\Api\TeamController::class, 'updateSettings']);

        Route::post('/teams/{teamId}/invite/generate', [\App\Http\Controllers\Api\TeamController::class, 'generateInviteLink']);
        Route::patch('/teams/{teamId}/name', [\App\Http\Controllers\Api\TeamController::class, 'updateName']);

        // Entries routes
        // Routes for deleted entries, restore, and export (must be defined before the {id} routes)
        Route::get('/teams/{teamId}/entries/deleted', [\App\Http\Controllers\Api\EntryController::class, 'deleted']);
        Route::get('/teams/{teamId}/entries/export', [\App\Http\Controllers\Api\EntryController::class, 'export']);

        // Standard CRUD routes
        Route::get('/teams/{teamId}/entries', [\App\Http\Controllers\Api\EntryController::class, 'index']);
        Route::post('/teams/{teamId}/entries', [\App\Http\Controllers\Api\EntryController::class, 'store']);
        Route::get('/teams/{teamId}/entries/{id}', [\App\Http\Controllers\Api\EntryController::class, 'show']);
        Route::patch('/teams/{teamId}/entries/{id}', [\App\Http\Controllers\Api\EntryController::class, 'update']);
        Route::delete('/teams/{teamId}/entries/{id}', [\App\Http\Controllers\Api\EntryController::class, 'destroy']);
        Route::post('/teams/{teamId}/entries/{id}/restore', [\App\Http\Controllers\Api\EntryController::class, 'restore']);
    //});

    // Admin routes
    Route::post('/teams/{team}/admins/add', [\App\Http\Controllers\Api\TeamController::class, 'addAdmin']);

    Route::post('/teams/{team}/admins/remove', [\App\Http\Controllers\Api\TeamController::class, 'removeAdmin']);

    // User routes
    Route::get('/me/teams', [\App\Http\Controllers\Api\UserController::class, 'getTeams']);
});

Route::get('/debug-auth', function (Request $request) {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user(),
    ]);
});
