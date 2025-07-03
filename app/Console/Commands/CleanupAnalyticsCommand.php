<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use Illuminate\Console\Command;

class CleanupAnalyticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:cleanup {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up analytics records older than 3 months';

    /**
     * Execute the console command.
     */
    public function handle(AnalyticsService $analyticsService): int
    {
        $this->info('Starting analytics cleanup...');

        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE - No records will be deleted');
            
            $cutoffDate = now()->subMonths(3);
            $count = \App\Models\RecognitionAnalytics::where('request_date', '<', $cutoffDate)->count();
            
            $this->info("Would delete {$count} records older than {$cutoffDate->format('Y-m-d H:i:s')}");
            
            return 0;
        }

        $deletedCount = $analyticsService->cleanupOldRecords();
        
        $this->info("Successfully deleted {$deletedCount} old analytics records");
        
        return 0;
    }
} 