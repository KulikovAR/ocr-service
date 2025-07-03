<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class RecognitionAnalytics extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id',
        'request_date',
        'status_code',
        'document_type',
        'max_confidence',
        'min_confidence',
        'recognition_success',
        'error_text',
        'processing_time_ms',
        'metadata',
    ];

    protected $casts = [
        'request_date' => 'datetime',
        'max_confidence' => 'decimal:4',
        'min_confidence' => 'decimal:4',
        'recognition_success' => 'boolean',
        'processing_time_ms' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Boot метод для автоматической очистки старых данных
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->request_date)) {
                $model->request_date = now();
            }
        });
    }

    /**
     * Scope для фильтрации по дате
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('request_date', [$startDate, $endDate]);
    }

    /**
     * Scope для фильтрации по типу документа
     */
    public function scopeByDocumentType($query, $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Scope для фильтрации по успешности
     */
    public function scopeBySuccess($query, $success)
    {
        return $query->where('recognition_success', $success);
    }

    /**
     * Scope для фильтрации последних записей
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('request_date', '>=', now()->subDays($days));
    }

    /**
     * Создает запись аналитики из задачи распознавания
     */
    public static function createFromTask($task, $statusCode = 200)
    {
        $resultData = $task->result_data ?? [];

        $maxConfidence = null;
        $minConfidence = null;

        if (isset($resultData['confidences']) && is_array($resultData['confidences'])) {
            $confidences = array_filter($resultData['confidences'], 'is_numeric');
            if (!empty($confidences)) {
                $maxConfidence = max($confidences);
                $minConfidence = min($confidences);
            }
        }

        $recognitionSuccess = $task->status === 'completed';

        $errorText = null;
        if ($task->status === 'failed' && isset($resultData['error'])) {
            $errorText = $resultData['error'];
        }

        $processingTime = null;
        if ($task->created_at && $task->updated_at) {
            $processingTime = $task->created_at->diffInMilliseconds($task->updated_at);
        }

        return self::create([
            'document_id' => $task->metadata['document_id'] ?? $task->id,
            'request_date' => $task->created_at,
            'status_code' => $statusCode,
            'document_type' => $task->document_type,
//            'max_confidence' => $maxConfidence,
//            'min_confidence' => $minConfidence,
//            'recognition_success' => $recognitionSuccess,
//            'error_text' => $errorText,
//            'processing_time_ms' => $processingTime,
//            'metadata' => $task->metadata,
        ]);
    }

    /**
     * Создает запись аналитики ошибки
     */
    public static function createErrorRecord($documentId, $documentType, $errorText, $statusCode = 500)
    {
        return self::create([
            'document_id' => $documentId,
            'request_date' => now(),
            'status_code' => $statusCode,
            'document_type' => $documentType,
            'recognition_success' => false,
            'error_text' => $errorText,
            'processing_time_ms' => null,
            'metadata' => [],
        ]);
    }

    /**
     * Получает статистику по типам документов
     */
    public static function getDocumentTypeStats($startDate = null, $endDate = null)
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->selectRaw('
            document_type,
            COUNT(*) as total_requests,
            SUM(CASE WHEN recognition_success = 1 THEN 1 ELSE 0 END) as successful_requests,
            AVG(processing_time_ms) as avg_processing_time,
            AVG(max_confidence) as avg_max_confidence,
            AVG(min_confidence) as avg_min_confidence
        ')
        ->groupBy('document_type')
        ->get();
    }

    /**
     * Получает статистику производительности
     */
    public static function getPerformanceStats($startDate = null, $endDate = null)
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->selectRaw('
            COUNT(*) as total_requests,
            SUM(CASE WHEN recognition_success = 1 THEN 1 ELSE 0 END) as successful_requests,
            AVG(processing_time_ms) as avg_processing_time,
            MIN(processing_time_ms) as min_processing_time,
            MAX(processing_time_ms) as max_processing_time,
            AVG(max_confidence) as avg_max_confidence,
            AVG(min_confidence) as avg_min_confidence
        ')
        ->first();
    }
}
