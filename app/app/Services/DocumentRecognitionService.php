<?php

namespace App\Services;

use App\Jobs\CheckRecognitionStatusJob;
use App\Models\DocumentRecognitionTask;
use Illuminate\Support\Facades\Log;

class DocumentRecognitionService
{
    protected ExternalApiClient $apiClient;
    protected DocumentDataProcessor $dataProcessor;
    protected AnalyticsService $analyticsService;
    protected PusherNotificationService $pusherService;

    public function __construct(
        ExternalApiClient     $apiClient,
        DocumentDataProcessor $dataProcessor,
        AnalyticsService      $analyticsService,
        PusherNotificationService $pusherService
    )
    {
        $this->apiClient = $apiClient;
        $this->dataProcessor = $dataProcessor;
        $this->analyticsService = $analyticsService;
        $this->pusherService = $pusherService;
    }



    /**
     * Создает задачу распознавания документа
     */
    public function createRecognitionTask(array $data): array
    {
        try {
            $task = DocumentRecognitionTask::create([
                'status' => DocumentRecognitionTask::STATUS_PENDING,
                'metadata' => [
                    'document_id' => $data['document_id'],
                    'document_type' => $data['document_type'],
                    'files_count' => count($data['images']),
                ],
                'document_type' => $data['document_type'],
            ]);

            $apiResponse = $this->apiClient->addDocument([
                'images' => $data['images'] ?? [],
                'scan' => $data['scan'] ?? null,
                'document_type' => $data['document_type'],
                'additional_data' => $data['metadata'] ?? [],
            ]);

            if ($apiResponse['success']) {
                $task->update([
                    'external_task_id' => $apiResponse['external_task_id'],
                    'status' => DocumentRecognitionTask::STATUS_PROCESSING,
                ]);

                // Планируем первую проверку статуса через 30 секунд для обработки файлов
                $this->scheduleStatusCheck($task);

                Log::info('Document recognition task created successfully', [
                    'task_id' => $task->id,
                    'external_task_id' => $apiResponse['external_task_id'],
                    'document_type' => $data['document_type'],
                    'files_count' => count($data['images'] ?? []),
                    'first_status_check_scheduled_for' => now()->addSeconds(30)
                ]);

                return [
                    'success' => true,
                    'task_id' => $task->id,
                    'external_task_id' => $apiResponse['external_task_id'],
                    'message' => 'Document sent for recognition'
                ];
            } else {
                $task->update([
                    'status' => DocumentRecognitionTask::STATUS_FAILED,
                    'result_data' => ['error' => $apiResponse['error']]
                ]);

                $this->analyticsService->createErrorRecord(
                    $data['document_id'],
                    $data['document_type'],
                    $apiResponse['error'],
                    500
                );

                return [
                    'success' => false,
                    'error' => $apiResponse['error']
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to create recognition task', ['error' => $e->getMessage(), 'data' => $data]);

            if (isset($data['document_id'])) {
                $this->analyticsService->createErrorRecord(
                    $data['document_id'],
                    $data['document_type'] ?? 'UNKNOWN',
                    $e->getMessage(),
                    500
                );
            }

            return [
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Проверяет статус задачи
     */
    public function checkTaskStatus(DocumentRecognitionTask $task): void
    {
        Log::info('checkTaskStatus: attempts_count', ['attempts_count' => $task->attempts_count, 'MAX_ATTEMPTS' => DocumentRecognitionTask::MAX_ATTEMPTS, 'canRetry_before' => $task->canRetry()]);

        if (!$task->canRetry()) {
            $task->update(['status' => DocumentRecognitionTask::STATUS_FAILED]);

            $this->analyticsService->createAnalyticsRecord($task, 408);

            // Отправляем уведомление об ошибке
            $documentId = $task->metadata['document_id'] ?? $task->id;
            $this->pusherService->sendRecognitionFailedNotification(
                $documentId,
                'Max attempts exceeded'
            );

            return;
        }

        $task->incrementAttempts();

        Log::info('checkTaskStatus: after increment', ['attempts_count' => $task->attempts_count, 'canRetry_after' => $task->canRetry()]);

        $apiResponse = $this->apiClient->getRecognitionResult($task->external_task_id);

        if (isset($apiResponse['not_ready']) && $apiResponse['not_ready']) {
            Log::info('checkTaskStatus: not_ready', ['attempts_count' => $task->attempts_count, 'canRetry' => $task->canRetry()]);

            if ($task->canRetry()) {
                // Планируем повторную проверку только если это не прямой запрос статуса
                if ($task->attempts_count > 0) {
                    $this->scheduleStatusCheck($task);
                }
            } else {
                $task->update(['status' => DocumentRecognitionTask::STATUS_FAILED]);

                $this->analyticsService->createAnalyticsRecord($task, 408);

                // Отправляем уведомление об ошибке
                $documentId = $task->metadata['document_id'] ?? $task->id;
                $this->pusherService->sendRecognitionFailedNotification(
                    $documentId,
                    'Recognition timeout after max attempts'
                );
            }
            return;
        }

        if ($apiResponse['success']) {
            $processedData = $this->dataProcessor->processRecognitionData(
                $apiResponse['data'],
                $task->document_type
            );

            $task->update([
                'status' => DocumentRecognitionTask::STATUS_COMPLETED,
                'result_data' => $processedData
            ]);

            $this->analyticsService->createAnalyticsRecord($task, 200);

            // Отправляем уведомление о завершении распознавания
            $documentId = $task->metadata['document_id'] ?? $task->id;
            $this->pusherService->sendRecognitionCompletedNotification(
                $documentId,
                $processedData['extracted_data'] ?? []
            );
        } else {
            $task->update([
                'status' => DocumentRecognitionTask::STATUS_FAILED,
                'result_data' => ['error' => $apiResponse['error']]
            ]);

            $this->analyticsService->createAnalyticsRecord($task, 500);

            // Отправляем уведомление об ошибке
            $documentId = $task->metadata['document_id'] ?? $task->id;
            $this->pusherService->sendRecognitionFailedNotification(
                $documentId,
                $apiResponse['error']
            );
        }
    }

    /**
     * Планирует проверку статуса через 30 секунд
     * Первая проверка дает время на обработку файлов в beorg.ru
     */
    protected function scheduleStatusCheck(DocumentRecognitionTask $task): void
    {
        $scheduledTime = now()->addSeconds(30);
        
        CheckRecognitionStatusJob::dispatch($task)
            ->delay($scheduledTime);

        Log::info('Status check scheduled', [
            'task_id' => $task->id, 
            'external_task_id' => $task->external_task_id,
            'scheduled_for' => $scheduledTime,
            'attempts_count' => $task->attempts_count
        ]);
    }


}
