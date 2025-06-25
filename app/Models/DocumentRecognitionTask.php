<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentRecognitionTask extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const MAX_ATTEMPTS = 10;

    protected $fillable = [
        'external_task_id',
        'status',
        'callback_url',
        'metadata',
        'result_data',
        'document_type',
        'attempts_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'result_data' => 'array',
        'attempts_count' => 'integer',
    ];

    public function canRetry(): bool
    {
        return $this->attempts_count < self::MAX_ATTEMPTS;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts_count');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
