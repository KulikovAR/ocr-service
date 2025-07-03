<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\TopicConf;

class KafkaService
{
    private Producer $producer;
    private string $topic;
    private bool $enabled;

    public function __construct()
    {
        $this->topic = config('kafka.topic', 'document-recognition-results');
        $this->enabled = config('kafka.enabled', true);
        
        if ($this->enabled) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.brokers', 'localhost:9092'));
            $conf->set('client.id', config('kafka.client_id', 'osr-service'));
            
            $this->producer = new Producer($conf);
        }
    }

    /**
     * Отправляет сообщение в Kafka
     */
    public function sendMessage(array $data): bool
    {
        if (!$this->enabled) {
            Log::info('Kafka is disabled, skipping message', ['data' => $data]);
            return true;
        }

        try {
            $topic = $this->producer->newTopic($this->topic);
            
            $message = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
            $this->producer->flush(10000); // 10 секунд таймаут
            
            Log::info('Message sent to Kafka', [
                'topic' => $this->topic,
                'data' => $data
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send message to Kafka', [
                'topic' => $this->topic,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return false;
        }
    }

    /**
     * Отправляет результат распознавания документа в Kafka
     */
    public function sendRecognitionResult(array $taskData, array $resultData): bool
    {
        $message = [
            'event_type' => 'document_recognition_completed',
            'task_id' => $taskData['id'],
            'external_task_id' => $taskData['external_task_id'],
            'status' => $taskData['status'],
            'document_type' => $taskData['document_type'],
            'metadata' => $taskData['metadata'],
            'result' => $resultData,
            'timestamp' => now()->toISOString(),
            'source' => 'osr-service'
        ];

        return $this->sendMessage($message);
    }
} 