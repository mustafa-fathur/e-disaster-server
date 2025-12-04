<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    /**
    <?php

    namespace App\Http\Controllers\Api\V1;

    use App\Http\Controllers\Controller;
    use Illuminate\Http\Request;

    class SystemController extends Controller
    {
        /**
         * @OA\Get(
         *     path="/health",
         *     summary="Health check",
         *     description="Check API health status",
         *     tags={"System"},
         *     @OA\Response(
         *         response=200,
         *         description="API is healthy",
         *         @OA\JsonContent(
         *             @OA\Property(property="status", type="string", example="aman"),
         *             @OA\Property(property="message", type="string", example="bismillah wisuda februari 2026"),
         *         )
         *     )
         * )
         */
        public function health()
        {
            return response()->json(['status' => 'aman', 'message' => 'bismillah wisuda februari 2026']);
        }
    }
