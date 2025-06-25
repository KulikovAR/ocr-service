<?php

namespace App\Services;

use App\Models\DocumentRecognitionTask;
use Illuminate\Support\Facades\Log;

class DocumentRecognitionService
{
    protected ExternalApiClient $apiClient;
    protected DocumentDataProcessor $dataProcessor;
    protected AnalyticsService $analyticsService;

    public function __construct(
        ExternalApiClient $apiClient, 
        DocumentDataProcessor $dataProcessor,
        AnalyticsService $analyticsService
    ) {
        $this->apiClient = $apiClient;
        $this->dataProcessor = $dataProcessor;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Создает задачу распознавания документа
     */
    public function createRecognitionTask(array $data): array
    {
        try {
            // Создаем запись в БД
            $task = DocumentRecognitionTask::create([
                'status' => DocumentRecognitionTask::STATUS_PENDING,
                'callback_url' => $data['callback_url'],
                'metadata' => [
                    'document_id' => $data['document_id'],
                    'document_type' => $data['document_type'],
                    'files_count' => count($data['images']),
                ],
                'document_type' => $data['document_type'],
            ]);

            // Отправляем документ во внешний API
            $apiResponse = $this->apiClient->addDocument([
                'images' => $data['images'] ?? [],
                'scan' => $data['scan'] ?? null,
                'document_type' => $data['document_type'],
                'additional_data' => $data['metadata'] ?? [],
            ]);

            if ($apiResponse['success']) {
                // Обновляем задачу с external_task_id
                $task->update([
                    'external_task_id' => $apiResponse['external_task_id'],
                    'status' => DocumentRecognitionTask::STATUS_PROCESSING,
                ]);

                // Запускаем задачу для проверки статуса
                $this->scheduleStatusCheck($task);

                return [
                    'success' => true,
                    'task_id' => $task->id,
                    'external_task_id' => $apiResponse['external_task_id'],
                    'message' => 'Document sent for recognition'
                ];
            } else {
                // Если API вернул ошибку, помечаем задачу как неудачную
                $task->update([
                    'status' => DocumentRecognitionTask::STATUS_FAILED,
                    'result_data' => ['error' => $apiResponse['error']]
                ]);

                // Создаем запись аналитики для ошибки
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
            Log::error('Failed to create recognition task', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            // Создаем запись аналитики для ошибки
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
     * Проверяет статус задачи и отправляет webhook при завершении
     */
    public function checkTaskStatus(DocumentRecognitionTask $task): void
    {
        Log::info('checkTaskStatus: attempts_count', [
            'attempts_count' => $task->attempts_count,
            'MAX_ATTEMPTS' => DocumentRecognitionTask::MAX_ATTEMPTS,
            'canRetry_before' => $task->canRetry()
        ]);

        if (!$task->canRetry()) {
            $task->update(['status' => DocumentRecognitionTask::STATUS_FAILED]);
            
            // Создаем запись аналитики для ошибки
            $this->analyticsService->createAnalyticsRecord($task, 408);
            
            $this->sendWebhook($task, ['error' => 'Max attempts exceeded']);
            return;
        }

        $task->incrementAttempts();

        Log::info('checkTaskStatus: after increment', [
            'attempts_count' => $task->attempts_count,
            'canRetry_after' => $task->canRetry()
        ]);

        $apiResponse = $this->apiClient->getRecognitionResult($task->external_task_id);

        if ($apiResponse['success']) {
            // Обрабатываем данные через DocumentDataProcessor
            $processedData = $this->dataProcessor->processRecognitionData(
                $apiResponse['data'], 
                $task->document_type
            );

            $task->update([
                'status' => DocumentRecognitionTask::STATUS_COMPLETED,
                'result_data' => $processedData
            ]);

            // Создаем запись аналитики для успешного завершения
            $this->analyticsService->createAnalyticsRecord($task, 200);

            $this->sendWebhook($task, $processedData);
        } else {
            // Проверяем, не готов ли результат еще
            if (isset($apiResponse['not_ready']) && $apiResponse['not_ready']) {
                Log::info('checkTaskStatus: not_ready', [
                    'attempts_count' => $task->attempts_count,
                    'canRetry' => $task->canRetry()
                ]);
                // Если результат еще не готов, планируем следующую проверку
                if ($task->canRetry()) {
                    $this->scheduleStatusCheck($task);
                } else {
                    $task->update(['status' => DocumentRecognitionTask::STATUS_FAILED]);
                    
                    // Создаем запись аналитики для ошибки
                    $this->analyticsService->createAnalyticsRecord($task, 408);
                    
                    $this->sendWebhook($task, ['error' => 'Recognition timeout after max attempts']);
                }
            } else {
                // Если произошла ошибка, помечаем задачу как неудачную
                $task->update([
                    'status' => DocumentRecognitionTask::STATUS_FAILED,
                    'result_data' => ['error' => $apiResponse['error']]
                ]);
                
                // Создаем запись аналитики для ошибки
                $this->analyticsService->createAnalyticsRecord($task, 500);
                
                $this->sendWebhook($task, ['error' => $apiResponse['error']]);
            }
        }
    }

    /**
     * Отправляет webhook на callback_url
     */
    private function sendWebhook(DocumentRecognitionTask $task, array $data): void
    {
        try {
            $payload = [
                'task_id' => $task->id,
                'external_task_id' => $task->external_task_id,
                'status' => $task->status,
                'metadata' => $task->metadata,
                'result' => $data,
                'timestamp' => now()->toISOString()
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->post($task->callback_url, $payload);

            Log::info('Webhook sent successfully', [
                'task_id' => $task->id,
                'callback_url' => $task->callback_url,
                'response_status' => $response->status()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send webhook', [
                'task_id' => $task->id,
                'callback_url' => $task->callback_url,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Планирует проверку статуса через 30 секунд
     */
    protected function scheduleStatusCheck(DocumentRecognitionTask $task): void
    {
        \App\Jobs\CheckRecognitionStatusJob::dispatch($task)
            ->delay(now()->addSeconds(30));
        
        Log::info('Status check scheduled', [
            'task_id' => $task->id,
            'scheduled_for' => now()->addSeconds(30)
        ]);
    }
} 