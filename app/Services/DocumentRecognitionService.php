<?php

namespace App\Services;

use App\Jobs\CheckRecognitionStatusJob;
use App\Models\DocumentRecognitionTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentRecognitionService
{
    protected ExternalApiClient $apiClient;
    protected DocumentDataProcessor $dataProcessor;
    protected AnalyticsService $analyticsService;
    protected KafkaService $kafkaService;

    public function __construct(
        ExternalApiClient     $apiClient,
        DocumentDataProcessor $dataProcessor,
        AnalyticsService      $analyticsService,
        KafkaService          $kafkaService
    )
    {
        $this->apiClient = $apiClient;
        $this->dataProcessor = $dataProcessor;
        $this->analyticsService = $analyticsService;
        $this->kafkaService = $kafkaService;
    }

    /**
     * Создает задачу распознавания документа
     */
    public function createRecognitionTask(array $data): array
    {
        try {
            $task = DocumentRecognitionTask::create([
                'status' => DocumentRecognitionTask::STATUS_PENDING,
                'callback_url' => $data['callback_url'] ?? null,
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

                $this->scheduleStatusCheck($task);

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
     * Проверяет статус задачи и отправляет webhook при завершении
     */
    public function checkTaskStatus(DocumentRecognitionTask $task): void
    {
        Log::info('checkTaskStatus: attempts_count', ['attempts_count' => $task->attempts_count, 'MAX_ATTEMPTS' => DocumentRecognitionTask::MAX_ATTEMPTS, 'canRetry_before' => $task->canRetry()]);

        if (!$task->canRetry()) {
            $task->update(['status' => DocumentRecognitionTask::STATUS_FAILED]);

            $this->analyticsService->createAnalyticsRecord($task, 408);

            $this->sendNotification($task, ['error' => 'Max attempts exceeded']);

            return;
        }

        $task->incrementAttempts();

        Log::info('checkTaskStatus: after increment', ['attempts_count' => $task->attempts_count, 'canRetry_after' => $task->canRetry()]);

        $apiResponse = $this->apiClient->getRecognitionResult($task->external_task_id);

        if (isset($apiResponse['not_ready']) && $apiResponse['not_ready']) {
            Log::info('checkTaskStatus: not_ready', ['attempts_count' => $task->attempts_count, 'canRetry' => $task->canRetry()]);

            if ($task->canRetry()) {
                $this->scheduleStatusCheck($task);
            } else {
                $task->update(['status' => DocumentRecognitionTask::STATUS_FAILED]);

                $this->analyticsService->createAnalyticsRecord($task, 408);

                $this->sendNotification($task, ['error' => 'Recognition timeout after max attempts']);
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

            $this->sendNotification($task, $processedData);
        } else {
            $task->update([
                'status' => DocumentRecognitionTask::STATUS_FAILED,
                'result_data' => ['error' => $apiResponse['error']]
            ]);

            $this->analyticsService->createAnalyticsRecord($task, 500);

            $this->sendNotification($task, ['error' => $apiResponse['error']]);
        }
    }

    /**
     * Отправляет уведомление: webhook на callback_url или сообщение в Kafka
     */
    private function sendNotification(DocumentRecognitionTask $task, array $data): void
    {
        $payload = [
            'task_id' => $task->id,
            'external_task_id' => $task->external_task_id,
            'status' => $task->status,
            'metadata' => $task->metadata,
            'result' => $data,
            'timestamp' => now()->toISOString()
        ];

        if ($task->callback_url !== null) {
            try {
                $response = Http::timeout(10)
                    ->post($task->callback_url, $payload);

                Log::info('Webhook sent successfully', ['task_id' => $task->id, 'callback_url' => $task->callback_url, 'response_status' => $response->status()]);

            } catch (\Exception $e) {
                Log::error('Failed to send webhook', ['task_id' => $task->id, 'callback_url' => $task->callback_url, 'error' => $e->getMessage()]);
            }
        } else {
            $this->kafkaService->sendRecognitionResult($task->toArray(), $data);
        }
    }

    /**
     * Планирует проверку статуса через 30 секунд
     */
    protected function scheduleStatusCheck(DocumentRecognitionTask $task): void
    {
        CheckRecognitionStatusJob::dispatch($task)
            ->delay(now()->addSeconds(30));

        Log::info('Status check scheduled', ['task_id' => $task->id, 'scheduled_for' => now()->addSeconds(30)]);
    }
}
