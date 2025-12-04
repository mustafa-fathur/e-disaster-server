<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="e-Disaster API",
 *     version="1.0.0",
 *     description="Disaster Management System API for Mobile Applications",
 *     @OA\Contact(
 *         email="admin@edisaster.test"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="https://e-disaster.fathur.tech/api/v1",
 *     description="Production Server"
 * )
 * @OA\Server(
 *     url="http://localhost:8000/api/v1",
 *     description="Development Server"
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
 *     name="System",
 *     description="System health and status endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and profile management"
 * )
 * 
 * @OA\Tag(
 *     name="Disasters",
 *     description="Disaster management operations"
 * )
 * 
 * @OA\Tag(
 *     name="Reports",
 *     description="Disaster reporting operations"
 * )
 * 
 * @OA\Tag(
 *     name="Victims",
 *     description="Disaster victim management"
 * )
 * 
 * @OA\Tag(
 *     name="Aids",
 *     description="Disaster aid management"
 * )
 * 
 * @OA\Tag(
 *     name="Notifications",
 *     description="User notification management"
 * )
 * 
 * @OA\Tag(
 *     name="Pictures",
 *     description="Image upload and management"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}