<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class PusherNotificationService
{
    protected Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key', '73bcd4a12fe7960bd9da') ?? "73bcd4a12fe7960bd9da",
            config('broadcasting.connections.pusher.secret', '1411e15fd0eb55e7eced') ?? "1411e15fd0eb55e7eced",
            config('broadcasting.connections.pusher.app_id', '1422160') ?? "1422160",
            config('broadcasting.connections.pusher.options', ['cluster' => 'eu'])
        );
    }

    /**
     * Отправляет уведомление о завершении распознавания документа
     */
    public function sendRecognitionCompletedNotification(string $documentId, array $data): void
    {
        try {
            $payload = [
                'document_id' => $documentId,
                'status' => 'completed',
                'data' => $data,
                'timestamp' => now()->toISOString()
            ];

            $this->pusher->trigger(
                'document-recognition',
                'recognition-completed',
                $payload
            );

            Log::info('Pusher notification sent', [
                'document_id' => $documentId,
                'channel' => 'document-recognition',
                'event' => 'recognition-completed'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send Pusher notification', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Отправляет уведомление об ошибке распознавания
     */
    public function sendRecognitionFailedNotification(string $documentId, string $error): void
    {
        try {
            $payload = [
                'document_id' => $documentId,
                'status' => 'failed',
                'error' => $error,
                'timestamp' => now()->toISOString()
            ];

            $this->pusher->trigger(
                'document-recognition',
                'recognition-failed',
                $payload
            );

            Log::info('Pusher notification sent', [
                'document_id' => $documentId,
                'channel' => 'document-recognition',
                'event' => 'recognition-failed'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send Pusher notification', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
