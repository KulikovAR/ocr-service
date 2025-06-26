<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecognitionAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\AnalyticsService;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="Операции с аналитикой распознавания документов"
 * )
 */
class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Главная страница аналитики
     */
    public function index(): JsonResponse
    {
        $stats = RecognitionAnalytics::getPerformanceStats();

        $documentTypeStats = RecognitionAnalytics::getDocumentTypeStats();

        return response()->json([
            'overall_stats' => $stats,
            'document_type_stats' => $documentTypeStats,
            'last_updated' => now()->toISOString(),
        ]);
    }

    /**
     * Статистика по запросам
     */
    public function requests(Request $request): JsonResponse
    {
        $query = RecognitionAnalytics::query();

        if ($request->has('date_from')) {
            $query->where('request_date', '>=', Carbon::parse($request->date_from));
        }
        if ($request->has('date_to')) {
            $query->where('request_date', '<=', Carbon::parse($request->date_to));
        }

        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->has('success')) {
            $success = filter_var($request->success, FILTER_VALIDATE_BOOLEAN);
            $query->where('recognition_success', $success);
        }

        $requests = $query->select([
            'document_id',
            'request_date',
            'status_code',
            'document_type',
            'max_confidence',
            'min_confidence',
            'recognition_success',
            'error_text',
            'processing_time_ms',
            'metadata'
        ])
        ->orderBy('request_date', 'desc')
        ->paginate($request->get('per_page', 50));

        return response()->json($requests);
    }

    /**
     * Статистика производительности
     */
    public function performance(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30));
        $dateTo = $request->get('date_to', now());

        $performance = RecognitionAnalytics::getPerformanceStats($dateFrom, $dateTo);

        $documentTypeStats = RecognitionAnalytics::getDocumentTypeStats($dateFrom, $dateTo);

        $recentRequests = RecognitionAnalytics::dateRange($dateFrom, $dateTo)
            ->select([
                'document_id',
                'document_type',
                'processing_time_ms',
                'max_confidence',
                'min_confidence',
                'recognition_success',
                'error_text',
            ])
            ->orderBy('request_date', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'period' => [
                'from' => $dateFrom->toISOString(),
                'to' => $dateTo->toISOString(),
            ],
            'overall_performance' => $performance,
            'document_type_breakdown' => $documentTypeStats,
            'recent_requests' => $recentRequests,
        ]);
    }

    public function getSummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $query = RecognitionAnalytics::query();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        $documentType = $request->get('document_type');
        if ($documentType) {
            $query->byDocumentType($documentType);
        }

        $success = $request->get('success');
        if ($success !== null) {
            $query->bySuccess($success === 'true');
        }

        $summary = $query->selectRaw('
            COUNT(*) as total_requests,
            SUM(CASE WHEN recognition_success = 1 THEN 1 ELSE 0 END) as successful_requests,
            AVG(processing_time_ms) as avg_processing_time,
            AVG(max_confidence) as avg_max_confidence,
            AVG(min_confidence) as avg_min_confidence
        ')->first();

        $documentTypeStats = $query->selectRaw('
            document_type,
            COUNT(*) as total,
            SUM(CASE WHEN recognition_success = 1 THEN 1 ELSE 0 END) as successful,
            AVG(processing_time_ms) as avg_time
        ')
        ->groupBy('document_type')
        ->get();

        $recentRequests = $query->orderBy('request_date', 'desc')
            ->limit(10)
            ->get(['document_id', 'document_type', 'recognition_success', 'processing_time_ms', 'request_date']);

        return response()->json([
            'summary' => $summary,
            'document_types' => $documentTypeStats,
            'recent_requests' => $recentRequests,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    public function getDetailed(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $query = RecognitionAnalytics::query();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        $documentType = $request->get('document_type');
        if ($documentType) {
            $query->byDocumentType($documentType);
        }

        $success = $request->get('success');
        if ($success !== null) {
            $query->bySuccess($success === 'true');
        }

        $perPage = min($request->get('per_page', 50), 100);

        $requests = $query->orderBy('request_date', 'desc')
            ->paginate($perPage);

        $errorStats = $query->where('recognition_success', false)
            ->selectRaw('
                error_text,
                COUNT(*) as count
            ')
            ->groupBy('error_text')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        $performanceStats = $query->selectRaw('
            AVG(processing_time_ms) as avg_time,
            MIN(processing_time_ms) as min_time,
            MAX(processing_time_ms) as max_time,
            STDDEV(processing_time_ms) as std_time
        ')->first();

        return response()->json([
            'requests' => $requests,
            'error_statistics' => $errorStats,
            'performance_statistics' => $performanceStats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'document_type' => $documentType,
                'success' => $success
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/analytics",
     *     operationId="getAnalytics",
     *     tags={"Analytics"},
     *     summary="Получить аналитику распознавания документов",
     *     description="Возвращает статистику по распознаванию документов за указанный период",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=false,
     *         description="Начальная дата периода (формат: Y-m-d)",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=false,
     *         description="Конечная дата периода (формат: Y-m-d)",
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="document_type",
     *         in="query",
     *         required=false,
     *         description="Фильтр по типу документа",
     *         @OA\Schema(type="string", enum={"PASSPORT", "PASSPORT_REG", "DLIC", "SNILS", "STS"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Аналитика получена успешно",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_requests", type="integer", example=1000, description="Общее количество запросов"),
     *                 @OA\Property(property="successful_requests", type="integer", example=950, description="Успешных запросов"),
     *                 @OA\Property(property="failed_requests", type="integer", example=50, description="Неудачных запросов"),
     *                 @OA\Property(property="success_rate", type="number", format="float", example=95.0, description="Процент успешных запросов"),
     *                 @OA\Property(property="average_processing_time", type="number", format="float", example=2.5, description="Среднее время обработки в секундах"),
     *                 @OA\Property(property="requests_by_type", type="object", example={"PASSPORT": 500, "DLIC": 300}, description="Запросы по типам документов"),
     *                 @OA\Property(property="requests_by_status", type="object", example={"completed": 950, "failed": 50}, description="Запросы по статусам"),
     *                 @OA\Property(property="daily_stats", type="array", @OA\Items(type="object"), description="Ежедневная статистика")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Доступ запрещен",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied.")
     *         )
     *     )
     * )
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $documentType = $request->get('document_type');

        $analytics = $this->analyticsService->getAnalytics($startDate, $endDate, $documentType);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }
}
