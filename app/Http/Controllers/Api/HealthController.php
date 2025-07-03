<?php

namespace App\Http\Controllers\Api;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    private HealthCheckService $healthCheckService;

    public function __construct(HealthCheckService $healthCheckService)
    {
        $this->healthCheckService = $healthCheckService;
    }

    /**
     * @OA\Get(
     *     path="/health",
     *     operationId="checkHealth",
     *     summary="Check service health",
     *     description="Returns the health status of the service including database, queue, redis, external API and storage checks",
     *     tags={"Health"},
     *     @OA\Response(
     *         response=200,
     *         description="Health check successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="healthy", description="Overall health status: healthy, degraded, or unhealthy"),
     *             @OA\Property(property="timestamp", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *             @OA\Property(
     *                 property="checks",
     *                 type="object",
     *                 @OA\Property(
     *                     property="database",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="healthy"),
     *                     @OA\Property(property="message", type="string", example="Database connection successful"),
     *                     @OA\Property(
     *                         property="details",
     *                         type="object",
     *                         @OA\Property(property="tasks_count", type="integer", example=42),
     *                         @OA\Property(property="analytics_count", type="integer", example=15)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="queue",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="healthy"),
     *                     @OA\Property(property="message", type="string", example="Queue is working properly"),
     *                     @OA\Property(
     *                         property="details",
     *                         type="object",
     *                         @OA\Property(property="failed_jobs", type="integer", example=0)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="redis",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="healthy"),
     *                     @OA\Property(property="message", type="string", example="Redis connection successful")
     *                 ),
     *                 @OA\Property(
     *                     property="external_api",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="healthy"),
     *                     @OA\Property(property="message", type="string", example="External API is working properly"),
     *                     @OA\Property(
     *                         property="details",
     *                         type="object",
     *                         @OA\Property(property="recent_errors", type="integer", example=0)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="storage",
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="healthy"),
     *                     @OA\Property(property="message", type="string", example="Storage is adequate"),
     *                     @OA\Property(
     *                         property="details",
     *                         type="object",
     *                         @OA\Property(property="usage_percent", type="number", format="float", example=45.2),
     *                         @OA\Property(property="free_space_mb", type="number", format="float", example=1024.5)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Service unhealthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="unhealthy"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="checks", type="object")
     *         )
     *     )
     * )
     */
    public function checkHealth(): JsonResponse
    {
        $healthData = $this->healthCheckService->checkHealth();

        $statusCode = $healthData['status'] === 'healthy' ? 200 : 503;

        return response()->json($healthData, $statusCode);
    }
}
