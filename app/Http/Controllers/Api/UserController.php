<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller for user-related endpoints.
 */
class UserController extends Controller
{
    /**
     * Get the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/user",
     *     summary="Get authenticated user information",
     *     description="Returns the currently authenticated user's information",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function getCurrentUser(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Get the teams that the authenticated user belongs to.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/me/teams",
     *     summary="Get user's teams",
     *     description="Returns the teams that the authenticated user belongs to",
     *     tags={"User"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Teams retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Team")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function getTeams(Request $request): JsonResponse
    {
        $user = $request->user();
        $teams = $user->teams()->with('founder')->get();

        return response()->json($teams);
    }
}
