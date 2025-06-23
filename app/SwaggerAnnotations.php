<?php

namespace App;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Brainswarming API",
 *     description="API for the Brainswarming platform - a SaaS platform for teams to anonymously or openly submit, evaluate, and prioritize improvement ideas.",
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints for user authentication"
 * )
 *
 * @OA\Tag(
 *     name="Teams",
 *     description="API Endpoints for managing teams"
 * )
 *
 * @OA\Schema(
 *     schema="Error",
 *     title="Error",
 *     description="Error response",
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\Property(property="field", type="array", @OA\Items(type="string", example="The field is required."))
 *     )
 * )
 */
class SwaggerAnnotations
{
    // This class is used only for Swagger annotations
}
