<?php

namespace App\Jobs;

use App\Models\DocumentRecognitionTask;
use App\Services\DocumentRecognitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckRecognitionStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected DocumentRecognitionTask $task;

    public function __construct(DocumentRecognitionTask $task)
    {
        $this->task = $task;
    }

    public function handle(DocumentRecognitionService $recognitionService): void
    {
        $recognitionService->checkTaskStatus($this->task);
    }
} 