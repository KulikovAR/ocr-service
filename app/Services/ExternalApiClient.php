<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $token;
    protected string $machineUid;
    protected string $projectId;

    public function __construct()
    {
        $this->baseUrl = config('services.bescan.base_url');
        $this->apiKey = config('services.bescan.api_key');
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
                'set' => [
                    'id' => $data['set_id'] ?? 1,
                    'key' => $data['set_key'] ?? 'default-set-key',
                    'count' => $data['set_count'] ?? 1,
                    'number' => $data['set_number'] ?? 1,
                ],
                'additional_data' => $data['additional_data'] ?? [],
                'recognized_data' => $data['recognized_data'] ?? [],
                'upload_place' => $data['upload_place'] ?? 0,
                'acc_pack_id' => $data['acc_pack_id'] ?? 0,
                'process_info' => [
                    [
                        'type' => $data['document_type'],
                        'options' => [
                            'stages' => $data['stages'] ?? ['verification'],
                            'relation' => $data['relation'] ?? [],
                        ]
                    ]
                ]
            ];

            Log::info('Sending document to external API', [
                'document_type' => $data['document_type'],
                'images_count' => count($data['images'] ?? []),
                'has_scan' => !empty($data['scan'])
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/api/bescan/add_document', $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['external_task_id'])) {
                    Log::info('Document successfully sent to external API', [
                        'external_task_id' => $responseData['external_task_id']
                    ]);

                    return [
                        'success' => true,
                        'external_task_id' => $responseData['external_task_id']
                    ];
                } else {
                    Log::error('External API response missing external_task_id', [
                        'response' => $responseData
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Invalid response from external API'
                    ];
                }
            } else {
                Log::error('External API request failed', [
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'External API request failed: ' . $response->status()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exception during external API call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'External API error: ' . $e->getMessage()
            ];
        }
    }

    public function getRecognitionResult(string $externalTaskId): array
    {
        try {
            Log::info('Checking recognition status', [
                'external_task_id' => $externalTaskId
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->timeout(30)->get($this->baseUrl . '/api/document/resutl/' . $externalTaskId);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['status'])) {
                    if ($responseData['status'] === 'completed') {
                        Log::info('Recognition completed', [
                            'external_task_id' => $externalTaskId,
                            'data' => $responseData
                        ]);

                        return [
                            'success' => true,
                            'data' => $responseData
                        ];
                    } elseif ($responseData['status'] === 'processing') {
                        Log::info('Recognition still processing', [
                            'external_task_id' => $externalTaskId
                        ]);

                        return [
                            'success' => false,
                            'not_ready' => true,
                            'error' => 'Recognition still in progress'
                        ];
                    } else {
                        Log::error('Unknown recognition status', [
                            'external_task_id' => $externalTaskId,
                            'status' => $responseData['status']
                        ]);

                        return [
                            'success' => false,
                            'error' => 'Unknown recognition status: ' . $responseData['status']
                        ];
                    }
                } else {
                    Log::error('External API response missing status', [
                        'external_task_id' => $externalTaskId,
                        'response' => $responseData
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Invalid response from external API'
                    ];
                }
            } else {
                Log::error('External API status check failed', [
                    'external_task_id' => $externalTaskId,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'External API status check failed: ' . $response->status()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exception during status check', [
                'external_task_id' => $externalTaskId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Status check error: ' . $e->getMessage()
            ];
        }
    }
}
