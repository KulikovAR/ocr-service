<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessDocumentRequest;
use App\Models\DocumentRecognitionTask;
use App\Services\DocumentRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Document Recognition API",
 *     description="API для распознавания документов через внешний сервис bescan.ru",
 *     @OA\Contact(
 *         email="support@example.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://127.0.0.1:8080/api/v1",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Tag(
 *     name="Document Recognition",
 *     description="Операции с распознаванием документов"
 * )
 */
class DocumentRecognitionController extends Controller
{
    protected DocumentRecognitionService $recognitionService;

    public function __construct(DocumentRecognitionService $recognitionService)
    {
        $this->recognitionService = $recognitionService;
    }

    /**
     * @OA\Post(
     *     path="/recognize",
     *     operationId="recognizeDocument",
     *     tags={"Document Recognition"},
     *     summary="Отправить документ на распознавание",
     *     description="Отправляет документ на распознавание во внешний сервис bescan.ru",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"document_id", "document_type", "images"},
     *             @OA\Property(property="document_id", type="string", example="doc_123", description="Уникальный идентификатор документа"),
     *             @OA\Property(property="document_type", type="string", enum={"PASSPORT", "PASSPORT_REG", "DLIC", "SNILS", "STS"}, example="PASSPORT", description="Тип документа"),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string", format="base64"), example={"/9j/4AAQSkZJRgABAQEASABIAAD/4gIYSUNDX1BST0ZJTEUAAQEAAAIIAAAAAAQwAABtbnRyUkdCIFhZWiAH"}, description="Массив изображений в формате base64"),
     *             @OA\Property(property="callback_url", type="string", format="url", example="https://example.com/webhook", description="URL для отправки результата распознавания"),
     *             @OA\Property(property="metadata", type="object", example={"user_id": 123, "session_id": "abc123"}, description="Дополнительные метаданные")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Документ успешно отправлен на распознавание",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="task_id", type="integer", example=1, description="ID задачи в системе"),
     *             @OA\Property(property="document_id", type="integer", example=1, description="ID документа (дублирует task_id)"),
     *             @OA\Property(property="external_task_id", type="string", example="s-12345", description="ID задачи во внешнем сервисе"),
     *             @OA\Property(property="message", type="string", example="Document sent for recognition")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка при отправке документа",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="External API error: Connection timeout")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"document_type": {"The document type field is required."}})
     *         )
     *     )
     * )
     */
    public function recognize(ProcessDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->recognitionService->createRecognitionTask($data);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'task_id' => $result['task_id'],
                'document_id' => $result['task_id'],
                'external_task_id' => $result['external_task_id'],
                'message' => $result['message']
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/status/{task_id}",
     *     operationId="getTaskStatus",
     *     tags={"Document Recognition"},
     *     summary="Получить статус задачи распознавания",
     *     description="Возвращает текущий статус задачи распознавания и результат, если готов",
     *     @OA\Parameter(
     *         name="task_id",
     *         in="path",
     *         required=true,
     *         description="ID задачи, внешний ID задачи или ID документа",
     *         @OA\Schema(type="string", example="1")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статус задачи получен",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="task_id", type="integer", example=1),
     *             @OA\Property(property="document_id", type="integer", example=1),
     *             @OA\Property(property="external_task_id", type="string", example="s-12345"),
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="completed"),
     *             @OA\Property(property="document_type", type="string", example="PASSPORT"),
     *             @OA\Property(property="metadata", type="object", example={"user_id": 123}),
     *             @OA\Property(property="data", type="object", example={"Series": "1234", "Number": "567890"}, description="Распознанные данные (только для completed)"),
     *             @OA\Property(property="confidences", type="object", example={"Series": 0.95}, description="Уверенность в распознавании (только для completed)"),
     *             @OA\Property(property="verifications", type="object", example={"Series": {"valid": true}}, description="Результаты верификации (только для completed)"),
     *             @OA\Property(property="error", type="string", example="Document processing failed", description="Описание ошибки (только для failed)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Задача не найдена",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Task not found"),
     *             @OA\Property(property="code", type="integer", example=404)
     *         )
     *     )
     * )
     */
    public function status(string $taskId): JsonResponse
    {
        $task = DocumentRecognitionTask::where('metadata->document_id', $taskId)
            ->orWhere('id', $taskId)
            ->orWhere('external_task_id', $taskId)
            ->first();

        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => 'Task not found',
                'code' => 404
            ], 404);
        }

        $response = [
            'success' => true,
            'task_id' => $task->id,
            'document_id' => $task->id,
            'external_task_id' => $task->external_task_id,
            'status' => $task->status,
            'document_type' => $task->document_type,
            'metadata' => $task->metadata,
        ];

        if ($task->status === DocumentRecognitionTask::STATUS_COMPLETED) {
            $response['data'] = $task->result_data['data'] ?? [];
            $response['confidences'] = $task->result_data['confidences'] ?? [];
            $response['verifications'] = $task->result_data['verifications'] ?? [];
        } elseif ($task->status === DocumentRecognitionTask::STATUS_FAILED) {
            $response['error'] = $task->result_data['error'] ?? 'Recognition failed';
        }

        return response()->json($response);
    }
}
