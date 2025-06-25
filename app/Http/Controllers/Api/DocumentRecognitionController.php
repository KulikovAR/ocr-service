<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessDocumentRequest;
use App\Models\DocumentRecognitionTask;
use App\Services\DocumentRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRecognitionController extends Controller
{
    protected DocumentRecognitionService $recognitionService;

    public function __construct(DocumentRecognitionService $recognitionService)
    {
        $this->recognitionService = $recognitionService;
    }

    public function processDocument(ProcessDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->recognitionService->createRecognitionTask($data);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'task_id' => $result['task_id'],
                'external_task_id' => $result['external_task_id'],
                'message' => $result['message']
            ], 202);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }
    }

    public function getStatus(string $documentId): JsonResponse
    {
        $task = DocumentRecognitionTask::where('metadata->document_id', $documentId)
            ->orWhere('id', $documentId)
            ->first();

        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found'
            ], 404);
        }

        $response = [
            'success' => true,
            'task_id' => $task->id,
            'external_task_id' => $task->external_task_id,
            'status' => $task->status,
            'metadata' => $task->metadata,
        ];

        if ($task->status === DocumentRecognitionTask::STATUS_COMPLETED) {
            $response['result'] = $task->result_data;
        } elseif ($task->status === DocumentRecognitionTask::STATUS_FAILED) {
            $response['error'] = $task->result_data['error'] ?? 'Recognition failed';
        }

        return response()->json($response);
    }
}
