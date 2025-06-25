<?php

namespace App\Services;

use App\Models\DocumentRecognitionTask;
use App\Models\RecognitionAnalytics;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    /**
     * Создает запись аналитики для завершенной задачи
     */
    public function createAnalyticsRecord(DocumentRecognitionTask $task, int $statusCode = 200): void
    {
        try {
            RecognitionAnalytics::createFromTask($task, $statusCode);
            
            Log::info('Analytics record created', [
                'task_id' => $task->id,
                'document_type' => $task->document_type,
                'status' => $task->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create analytics record', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Создает запись аналитики для ошибки
     */
    public function createErrorRecord(string $documentId, string $documentType, string $error, int $statusCode = 500): void
    {
        try {
            RecognitionAnalytics::create([
                'document_id' => $documentId,
                'request_date' => now(),
                'status_code' => $statusCode,
                'document_type' => $documentType,
                'max_confidence' => null,
                'min_confidence' => null,
                'recognition_success' => false,
                'error_text' => $error,
                'processing_time_ms' => null,
                'metadata' => [
                    'error_type' => 'processing_error',
                ],
            ]);
            
            Log::info('Error analytics record created', [
                'document_id' => $documentId,
                'document_type' => $documentType,
                'error' => $error,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create error analytics record', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получает статистику для дашборда
     */
    public function getDashboardStats(): array
    {
        $now = now();
        $last24Hours = $now->subDay();
        $last7Days = $now->subDays(7);
        $last30Days = $now->subDays(30);

        return [
            'last_24_hours' => [
                'total_requests' => RecognitionAnalytics::where('request_date', '>=', $last24Hours)->count(),
                'successful_requests' => RecognitionAnalytics::where('request_date', '>=', $last24Hours)
                    ->where('recognition_success', true)->count(),
                'avg_processing_time' => RecognitionAnalytics::where('request_date', '>=', $last24Hours)
                    ->whereNotNull('processing_time_ms')->avg('processing_time_ms'),
            ],
            'last_7_days' => [
                'total_requests' => RecognitionAnalytics::where('request_date', '>=', $last7Days)->count(),
                'successful_requests' => RecognitionAnalytics::where('request_date', '>=', $last7Days)
                    ->where('recognition_success', true)->count(),
                'avg_processing_time' => RecognitionAnalytics::where('request_date', '>=', $last7Days)
                    ->whereNotNull('processing_time_ms')->avg('processing_time_ms'),
            ],
            'last_30_days' => [
                'total_requests' => RecognitionAnalytics::where('request_date', '>=', $last30Days)->count(),
                'successful_requests' => RecognitionAnalytics::where('request_date', '>=', $last30Days)
                    ->where('recognition_success', true)->count(),
                'avg_processing_time' => RecognitionAnalytics::where('request_date', '>=', $last30Days)
                    ->whereNotNull('processing_time_ms')->avg('processing_time_ms'),
            ],
            'document_type_distribution' => RecognitionAnalytics::selectRaw('
                document_type,
                COUNT(*) as count,
                SUM(CASE WHEN recognition_success = 1 THEN 1 ELSE 0 END) as successful_count
            ')
            ->where('request_date', '>=', $last30Days)
            ->groupBy('document_type')
            ->get(),
            'error_summary' => RecognitionAnalytics::selectRaw('
                error_text,
                COUNT(*) as count
            ')
            ->where('request_date', '>=', $last30Days)
            ->where('recognition_success', false)
            ->whereNotNull('error_text')
            ->groupBy('error_text')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get(),
        ];
    }

    /**
     * Очищает старые записи аналитики (старше 3 месяцев)
     */
    public function cleanupOldRecords(): int
    {
        $cutoffDate = now()->subMonths(3);
        $deletedCount = RecognitionAnalytics::where('request_date', '<', $cutoffDate)->delete();
        
        Log::info('Cleaned up old analytics records', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);
        
        return $deletedCount;
    }
} 