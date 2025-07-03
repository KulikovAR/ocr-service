<?php

namespace Tests\Feature;

use App\Models\DocumentRecognitionTask;
use App\Services\DocumentRecognitionService;
use App\Services\KafkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KafkaNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_to_kafka_when_no_callback_url()
    {
        // Создаем мок для KafkaService
        $kafkaService = $this->createMock(KafkaService::class);
        $kafkaService->expects($this->once())
            ->method('sendRecognitionResult')
            ->willReturn(true);

        // Создаем задачу без callback_url
        $task = DocumentRecognitionTask::create([
            'status' => DocumentRecognitionTask::STATUS_COMPLETED,
            'callback_url' => null,
            'external_task_id' => 'test-external-id',
            'document_type' => 'passport',
            'metadata' => ['document_id' => 'test-123'],
            'result_data' => ['success' => true]
        ]);

        // Создаем сервис с моком
        $service = new DocumentRecognitionService(
            app(\App\Services\ExternalApiClient::class),
            app(\App\Services\DocumentDataProcessor::class),
            app(\App\Services\AnalyticsService::class),
            $kafkaService
        );

        // Вызываем приватный метод через рефлексию
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sendNotification');
        $method->setAccessible(true);

        $result = ['success' => true, 'data' => 'test data'];
        $method->invoke($service, $task, $result);

        // Если мы дошли сюда без ошибок, значит тест прошел
        $this->assertTrue(true);
    }

    public function test_sends_webhook_when_callback_url_exists()
    {
        // Создаем мок для KafkaService
        $kafkaService = $this->createMock(KafkaService::class);
        $kafkaService->expects($this->never())
            ->method('sendRecognitionResult');

        // Создаем задачу с callback_url
        $task = DocumentRecognitionTask::create([
            'status' => DocumentRecognitionTask::STATUS_COMPLETED,
            'callback_url' => 'http://example.com/callback',
            'external_task_id' => 'test-external-id',
            'document_type' => 'passport',
            'metadata' => ['document_id' => 'test-123'],
            'result_data' => ['success' => true]
        ]);

        // Создаем сервис с моком
        $service = new DocumentRecognitionService(
            app(\App\Services\ExternalApiClient::class),
            app(\App\Services\DocumentDataProcessor::class),
            app(\App\Services\AnalyticsService::class),
            $kafkaService
        );

        // Вызываем приватный метод через рефлексию
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sendNotification');
        $method->setAccessible(true);

        $result = ['success' => true, 'data' => 'test data'];
        $method->invoke($service, $task, $result);

        // Если мы дошли сюда без ошибок, значит тест прошел
        $this->assertTrue(true);
    }
} 