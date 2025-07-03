<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    |
    | Здесь настройки для подключения к Kafka
    |
    */

    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
    
    'client_id' => env('KAFKA_CLIENT_ID', 'ocr-service'),
    
    'topic' => env('KAFKA_TOPIC', 'document-recognition-results'),
    
    'timeout' => env('KAFKA_TIMEOUT', 10000), // 10 секунд
    
    'enabled' => env('KAFKA_ENABLED', true),
]; 