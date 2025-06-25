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

    public $tries = 1;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected DocumentRecognitionTask $task
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentRecognitionService $recognitionService): void
    {
        $recognitionService->checkTaskStatus($this->task);
    }
}
