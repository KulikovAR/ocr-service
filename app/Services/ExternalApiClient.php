<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiClient
{
    protected string $baseUrl;
    protected string $token;
    protected string $machineUid;
    protected string $projectId;

    public function __construct()
    {
        $this->baseUrl = config('services.bescan.base_url');
        $this->token = config('services.bescan.token');
        $this->machineUid = config('services.bescan.machine_uid');
        $this->projectId = config('services.bescan.project_id');
    }

    public function addDocument(array $data): array
    {
        try {
            $payload = [
                'token' => $this->token,
                'machine_uid' => $this->machineUid,
                'project_id' => $this->projectId,
                'images' => $data['images'] ?? [],
                'scan' => $data['scan'] ?? null,
                'additional_data' => $data['additional_data'] ?? [],
                'recognized_data' => $data['recognized_data'] ?? [],
                'upload_place' => $data['upload_place'] ?? 0,
                'acc_pack_id' => $data['acc_pack_id'] ?? 0,
                'process_info' => [
                    [
                        'type' => $data['document_type'],
                    ]
                ]
            ];

            Log::info('Sending document to external API', ['document_type' => $data['document_type'], 'images_count' => count($data['images'] ?? []), 'has_scan' => !empty($data['scan'])]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/api/bescan/add_document', $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['document_id'])) {
                    Log::info('Document successfully sent to external API', ['external_task_id' => $responseData['document_id']]);

                    return [
                        'success' => true,
                        'external_task_id' => $responseData['document_id']
                    ];
                } else {
                    Log::error('External API response missing external_task_id', ['response' => $responseData]);

                    return [
                        'success' => false,
                        'error' => 'Invalid response from external API'
                    ];
                }
            } else {
                Log::error('External API request failed', ['status_code' => $response->status(), 'response' => $response->body()]);

                return [
                    'success' => false,
                    'error' => ' ' . $response->status()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exception during external API call', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'error' => 'External API error: ' . $e->getMessage()
            ];
        }
    }

    public function getRecognitionResult(string $externalTaskId): array
    {
        try {
            Log::info('Checking recognition status', ['external_task_id' => $externalTaskId]);

            $response = Http::timeout(30)->get($this->baseUrl . '/api/document/result/' . $externalTaskId,
                [
                    'token' => $this->token
                ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Проверяем, есть ли данные документа в ответе
                if (isset($responseData['document_id']) && isset($responseData['documents']) && !empty($responseData['documents'])) {
                    Log::info('Recognition completed', ['external_task_id' => $externalTaskId, 'data' => $responseData]);

                    return [
                        'success' => true,
                        'data' => $responseData
                    ];
                } elseif (isset($responseData['document_id']) && (!isset($responseData['documents']) || empty($responseData['documents']))) {
                    // Документ существует, но данные еще не готовы
                    Log::info('Recognition still processing', ['external_task_id' => $externalTaskId]);

                    return [
                        'success' => false,
                        'not_ready' => true,
                        'error' => 'Recognition still in progress'
                    ];
                } else {
                    Log::error('External API response missing document data', ['external_task_id' => $externalTaskId, 'response' => $responseData]);

                    return [
                        'success' => false,
                        'error' => 'Invalid response from external API'
                    ];
                }
            } else {
                Log::error('External API status check failed', ['external_task_id' => $externalTaskId, 'status_code' => $response->status(), 'response' => $response->body()]);

                return [
                    'success' => false,
                    'error' => 'External API status check failed: ' . $response->status()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exception during status check', ['external_task_id' => $externalTaskId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'error' => 'Status check error: ' . $e->getMessage()
            ];
        }
    }
}
