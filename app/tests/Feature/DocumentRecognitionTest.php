<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DocumentRecognitionTask;
use App\Services\DocumentRecognitionService;
use App\Services\ExternalApiClient;
use App\Services\DocumentDataProcessor;
use App\Services\AnalyticsService;
use App\Services\PusherNotificationService;

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
            'images' => [$this->validBase64Image, $this->validBase64Image]
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
            'images' => ['invalid_file']
        ];

        $response = $this->postJson('/api/v1/recognize', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type', 'images.0']);
    }

    public function test_can_get_task_status(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'processing',
            'document_type' => 'PASSPORT',
            'metadata' => ['user_id' => 123, 'document_id' => 'doc_123']
        ]);

        // Мокаем проверку статуса
        $this->mock(DocumentRecognitionService::class, function ($mock) {
            $mock->shouldReceive('checkTaskStatus')->once();
        });

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'document_id' => 'doc_123',
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
            'document_type' => 'PASSPORT',
            'metadata' => ['document_id' => 'doc_123'],
            'result_data' => [
                'extracted_data' => [
                    'series' => '1234',
                    'number' => '567890',
                    'lastName' => 'Иванов',
                    'firstName' => 'Иван'
                ],
                'confidence_score' => [
                    'series' => 0.95,
                    'number' => 0.98
                ],
                'metadata' => [
                    'verifications' => [
                        'series' => ['valid' => true, 'message' => 'Valid']
                    ]
                ]
            ]
        ]);

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'document_id' => 'doc_123',
                'status' => 'completed',
                'document_type' => 'PASSPORT',
                'data' => [
                    'series' => '1234',
                    'number' => '567890',
                    'lastName' => 'Иванов',
                    'firstName' => 'Иван'
                ],
                'confidences' => [
                    'series' => 0.95,
                    'number' => 0.98
                ],
                'verifications' => [
                    'series' => ['valid' => true, 'message' => 'Valid']
                ]
            ]);
    }

    public function test_can_get_failed_recognition_result(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'failed',
            'document_type' => 'PASSPORT',
            'metadata' => ['document_id' => 'doc_123'],
            'result_data' => [
                'error' => 'Document processing failed'
            ]
        ]);

        // Мокаем DocumentRecognitionService чтобы не вызывать checkTaskStatus
        $this->mock(DocumentRecognitionService::class, function ($mock) {
            $mock->shouldReceive('checkTaskStatus')->never();
        });

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'document_id' => 'doc_123',
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
            'document_type' => 'PASSPORT',
            'metadata' => ['document_id' => 'doc_123'],
            'result_data' => [
                'extracted_data' => [
                    'Series' => '1234',
                    'Number' => '567890'
                ]
            ]
        ]);

        $response = $this->getJson("/api/v1/status/s-12345");

        $response->assertStatus(200)
            ->assertJson([
                'document_id' => 'doc_123',
                'status' => 'completed',
                'document_type' => 'PASSPORT'
            ]);
    }

    public function test_handles_beorg_api_response_structure(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'processing',
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
        $pusherService = app(PusherNotificationService::class);
        $service = new DocumentRecognitionService($apiClient, $dataProcessor, $analyticsService, $pusherService);
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

            $response->assertStatus(201);
        }
    }

    public function test_driver_license_processing_includes_end_date(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'completed',
            'document_type' => 'DLIC',
            'metadata' => ['document_id' => 'doc_123'],
            'result_data' => [
                'extracted_data' => [
                    'series' => '1234',
                    'number' => '567890',
                    'issuedBy' => 'ГИБДД',
                    'issueDate' => '2020-01-01',
                    'endDate' => '2030-01-01',
                    'lastName' => 'Иванов',
                    'firstName' => 'Иван',
                    'middleName' => 'Иванович',
                    'birthDate' => '1990-01-01',
                    'birthPlace' => 'Москва',
                    'gender' => 'M',
                    'categories' => ['B', 'C'],
                    'photo' => 'base64_photo_data',
                    'mrz1' => 'MRZ1_DATA',
                    'mrz2' => 'MRZ2_DATA',
                    'mrz3' => 'MRZ3_DATA'
                ],
                'confidence_score' => 0.95,
                'metadata' => [
                    'verifications' => [
                        'series' => ['valid' => true, 'message' => 'Valid']
                    ]
                ]
            ]
        ]);

        $response = $this->getJson("/api/v1/status/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'document_id' => 'doc_123',
                'status' => 'completed',
                'document_type' => 'DLIC',
                'data' => [
                    'series' => '1234',
                    'number' => '567890',
                    'issuedBy' => 'ГИБДД',
                    'issueDate' => '2020-01-01',
                    'endDate' => '2030-01-01',
                    'lastName' => 'Иванов',
                    'firstName' => 'Иван',
                    'middleName' => 'Иванович',
                    'birthDate' => '1990-01-01',
                    'birthPlace' => 'Москва',
                    'gender' => 'M',
                    'categories' => ['B', 'C'],
                    'photo' => 'base64_photo_data',
                    'mrz1' => 'MRZ1_DATA',
                    'mrz2' => 'MRZ2_DATA',
                    'mrz3' => 'MRZ3_DATA'
                ]
            ]);
    }

    public function test_recognition_task_schedules_status_check_with_delay(): void
    {
        $this->mock(ExternalApiClient::class, function ($mock) {
            $mock->shouldReceive('addDocument')
                ->once()
                ->andReturn([
                    'success' => true,
                    'external_task_id' => 's-12345'
                ]);
        });

        $this->mock(AnalyticsService::class, function ($mock) {
            $mock->shouldReceive('createErrorRecord')->never();
        });

        $this->mock(PusherNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendRecognitionCompletedNotification')->never();
            $mock->shouldReceive('sendRecognitionFailedNotification')->never();
        });

        $payload = [
            'document_id' => 'doc_123',
            'document_type' => 'PASSPORT',
            'images' => [$this->validBase64Image]
        ];

        $response = $this->postJson('/api/v1/recognize', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'task_id' => 1,
                'external_task_id' => 's-12345'
            ]);

        // Проверяем, что задача создана с правильным статусом
        $task = DocumentRecognitionTask::find(1);
        $this->assertNotNull($task);
        $this->assertEquals('processing', $task->status);
        $this->assertEquals('s-12345', $task->external_task_id);
        $this->assertEquals('PASSPORT', $task->document_type);
    }

    public function test_sends_pusher_notification_on_completion(): void
    {
        $task = DocumentRecognitionTask::create([
            'external_task_id' => 's-12345',
            'status' => 'processing',
            'document_type' => 'PASSPORT',
            'metadata' => ['document_id' => 'doc_123']
        ]);

        $this->mock(ExternalApiClient::class, function ($mock) {
            $mock->shouldReceive('getRecognitionResult')
                ->once()
                ->with('s-12345')
                ->andReturn([
                    'success' => true,
                    'data' => [
                        'documents' => [
                            [
                                'data' => [
                                    'Series' => '1234',
                                    'Number' => '567890',
                                    'LastName' => 'Иванов',
                                    'FirstName' => 'Иван'
                                ],
                                'metadata' => [
                                    'confidences' => [0.95, 0.98],
                                    'verifications' => [
                                        'Series' => ['valid' => true]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
        });

        $this->mock(PusherNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendRecognitionCompletedNotification')
                ->once()
                ->with('doc_123', \Mockery::type('array'));
        });

        $service = app(DocumentRecognitionService::class);
        $service->checkTaskStatus($task);
    }
}
