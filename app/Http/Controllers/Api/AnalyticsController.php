<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecognitionAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
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

        // Фильтры по дате
        if ($request->has('date_from')) {
            $query->where('request_date', '>=', Carbon::parse($request->date_from));
        }
        if ($request->has('date_to')) {
            $query->where('request_date', '<=', Carbon::parse($request->date_to));
        }

        // Фильтр по типу документа
        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        // Фильтр по успешности
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

        // Получаем последние запросы для детального анализа
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

    public function getAnalytics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $documentType = $request->get('document_type');

        $query = RecognitionAnalytics::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($documentType) {
            $query->where('document_type', $documentType);
        }

        $analytics = $query->get();

        $totalRequests = $analytics->count();
        $successfulRequests = $analytics->where('status_code', 200)->count();
        $failedRequests = $analytics->where('status_code', '!=', 200)->count();
        $averageProcessingTime = $analytics->where('status_code', 200)->avg('processing_time') ?? 0;

        $documentTypeStats = $analytics->groupBy('document_type')
            ->map(function ($group) {
                return [
                    'total' => $group->count(),
                    'successful' => $group->where('status_code', 200)->count(),
                    'failed' => $group->where('status_code', '!=', 200)->count(),
                    'average_processing_time' => $group->where('status_code', 200)->avg('processing_time') ?? 0,
                ];
            });

        $dailyStats = $analytics->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($group) {
            return [
                'total' => $group->count(),
                'successful' => $group->where('status_code', 200)->count(),
                'failed' => $group->where('status_code', '!=', 200)->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_requests' => $totalRequests,
                    'successful_requests' => $successfulRequests,
                    'failed_requests' => $failedRequests,
                    'success_rate' => $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 2) : 0,
                    'average_processing_time' => round($averageProcessingTime, 2),
                ],
                'by_document_type' => $documentTypeStats,
                'daily_stats' => $dailyStats,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }
} 