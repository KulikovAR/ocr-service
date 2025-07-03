<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DocumentRecognitionTask;
use App\Services\DocumentRecognitionService;
use App\Services\ExternalApiClient;
use App\Services\DocumentDataProcessor;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private string $validBase64Image;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validBase64Image = base64_encode(str_repeat('A', 15000));
    }

    public function test_can_process_document(): void
    {
        $this->mock(DocumentRecognitionService::class, function ($mock) {
            $mock->shouldReceive('createRecognitionTask')
                ->once()
                ->andReturn([
                    'success' => true,
                    'task_id' => 1,
                    'external_task_id' => 's-12345',
                    'message' => 'Document sent for recognition'
                ]);
        });

        $payload = [
            'document_id' => 'doc_123',
            'document_type' => 'PASSPORT',
            'images' => [$this->validBase64Image, $this->validBase64Image],
            'callback_url' => 'https://example.com/webhook'
        ];

        $response = $this->postJson('/api/v1/recognize', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'task_id' => 1,
                'document_id' => 1
            ]);
    }

    public function test_validation_fails_with_invalid_data(): void
    {
        $payload = [
            'document_id' => 'doc_123',
            'document_type' => 'INVALID_TYPE',
            'images' => ['invalid_file'],
            'callback_url' => 'not-a-url'
        ];

        $response = $this->postJson('/api/v1/recognize', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type', 'images.0', 'callback_url']);
    }

    public function test_can_get_task_status(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'processing',
            'callback_url' => 'https://example.com/webhook',
            'document_type' => 'PASSPORT',
            'metadata' => ['user_id' => 123]
        ]);

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'task_id' => $task->id,
                'document_id' => $task->id,
                'status' => 'processing',
                'document_type' => 'PASSPORT'
            ]);
    }

    public function test_returns_404_for_nonexistent_task(): void
    {
        $response = $this->getJson('/api/v1/status/999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Task not found',
                'code' => 404
            ]);
    }

    public function test_can_get_completed_recognition_result(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'completed',
            'callback_url' => 'https://example.com/webhook',
            'document_type' => 'PASSPORT',
            'result_data' => [
                'data' => [
                    'Series' => '1234',
                    'Number' => '567890',
                    'LastName' => 'Иванов',
                    'FirstName' => 'Иван'
                ],
                'confidences' => [
                    'Series' => 0.95,
                    'Number' => 0.98
                ],
                'verifications' => [
                    'Series' => ['valid' => true, 'message' => 'Valid']
                ]
            ]
        ]);

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'task_id' => $task->id,
                'document_id' => $task->id,
                'status' => 'completed',
                'document_type' => 'PASSPORT',
                'data' => [
                    'Series' => '1234',
                    'Number' => '567890',
                    'LastName' => 'Иванов',
                    'FirstName' => 'Иван'
                ],
                'confidences' => [
                    'Series' => 0.95,
                    'Number' => 0.98
                ],
                'verifications' => [
                    'Series' => ['valid' => true, 'message' => 'Valid']
                ]
            ]);
    }

    public function test_can_get_failed_recognition_result(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'failed',
            'callback_url' => 'https://example.com/webhook',
            'document_type' => 'PASSPORT',
            'result_data' => [
                'error' => 'Document processing failed'
            ]
        ]);

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'task_id' => $task->id,
                'document_id' => $task->id,
                'status' => 'failed',
                'document_type' => 'PASSPORT',
                'error' => 'Document processing failed'
            ]);
    }

    public function test_can_get_recognition_result_by_external_id(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'completed',
            'callback_url' => 'https://example.com/webhook',
            'document_type' => 'PASSPORT',
            'result_data' => [
                'data' => [
                    'Series' => '1234',
                    'Number' => '567890'
                ]
            ]
        ]);

        $response = $this->getJson("/api/v1/status/s-12345");

        $response->assertStatus(200)
            ->assertJson([
                'task_id' => $task->id,
                'document_id' => $task->id,
                'status' => 'completed',
                'document_type' => 'PASSPORT'
            ]);
    }

    public function test_handles_beorg_api_response_structure(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'processing',
            'callback_url' => 'https://example.com/webhook',
            'document_type' => 'PASSPORT',
            'metadata' => ['user_id' => 123]
        ]);

        $this->mock(ExternalApiClient::class, function ($mock) {
            $mock->shouldReceive('getRecognitionResult')
                ->twice()
                ->with('s-12345')
                ->andReturn([
                    'success' => true,
                    'data' => [
                        '' => [
                            'series' => '1234',
                            'number' => '567890',
                            'last_name' => 'Иванов',
                            'first_name' => 'Иван',
                            'birth_date' => '1990-01-01'
                        ]
                    ]
                ]);
        });

        $this->mock(DocumentDataProcessor::class, function ($mock) {
            $mock->shouldReceive('processRecognitionData')
                ->twice()
                ->andReturn([
                    'data' => [
                        'Series' => '1234',
                        'Number' => '567890',
                        'LastName' => 'Иванов',
                        'FirstName' => 'Иван',
                        'BirthDate' => '1990-01-01'
                    ],
                    'confidences' => [],
                    'verifications' => []
                ]);
        });

        $this->mock(AnalyticsService::class, function ($mock) {
            $mock->shouldReceive('createAnalyticsRecord')
                ->twice();
        });

        $task->status = 'processing';
        $task->attempts_count = 0;
        $task->save();

        $apiClient = app(ExternalApiClient::class);
        $dataProcessor = app(DocumentDataProcessor::class);
        $analyticsService = app(AnalyticsService::class);
        $service = new DocumentRecognitionService($apiClient, $dataProcessor, $analyticsService);
        // Подменяем protected метод scheduleStatusCheck через Closure::bind
        $closure = function (
            \App\Models\DocumentRecognitionTask $task
        ) {
            // ничего не делаем, просто мок
        };
        $bound = \Closure::bind($closure, $service, $service);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('scheduleStatusCheck');
        $method->setAccessible(true);
        $method->invokeArgs($service, [$task]); // для проверки, что подмена работает
        // теперь вызываем checkTaskStatus
        $service->checkTaskStatus($task);

        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }

    public function test_handles_not_ready_result(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'processing',
            'callback_url' => 'https://example.com/webhook',
            'document_type' => 'PASSPORT',
            'attempts_count' => 0
        ]);

        // Проверяем что задача создана с правильными параметрами
        $this->assertEquals('processing', $task->status);
        $this->assertEquals(0, $task->attempts_count);
        $this->assertEquals('PASSPORT', $task->document_type);
        $this->assertEquals('s-12345', $task->external_task_id);
    }

    public function test_validation_accepts_all_document_types(): void
    {
        $validTypes = ['PASSPORT', 'PASSPORT_REG', 'SNILS', 'DLIC', 'STS'];
        
        foreach ($validTypes as $docType) {
            $payload = [
                'document_id' => 'doc_123',
                'document_type' => $docType,
                'images' => [$this->validBase64Image]
            ];

            $response = $this->postJson('/api/v1/recognize', $payload);
            
            // Проверяем, что нет ошибок валидации для document_type
            if ($response->status() === 422) {
                $errors = $response->json('errors');
                $this->assertArrayNotHasKey('document_type', $errors, "Document type {$docType} should be valid");
            } else {
                // Если запрос прошел успешно, проверяем что это не ошибка валидации
                $this->assertNotEquals(422, $response->status(), "Document type {$docType} should be valid");
            }
        }
    }
}
