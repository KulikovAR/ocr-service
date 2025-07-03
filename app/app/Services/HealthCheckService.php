<?php

namespace App\Services;

use App\Models\DocumentRecognitionTask;
use App\Models\RecognitionAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthCheckService
{
    private const ERROR_FLAG_KEY = 'health_check_error';
    private const ERROR_THRESHOLD = 5;
    private const ERROR_WINDOW_MINUTES = 10;

    public function checkHealth(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'redis' => $this->checkRedis(),
            'external_api' => $this->checkExternalApi(),
            'storage' => $this->checkStorage(),
        ];

        $overallStatus = $this->determineOverallStatus($checks);
        
        $this->updateErrorFlag($overallStatus === 'healthy');

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            
            $taskCount = DocumentRecognitionTask::count();
            $analyticsCount = RecognitionAnalytics::count();
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => [
                    'tasks_count' => $taskCount,
                    'analytics_count' => $analyticsCount,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            
            if ($failedJobs > 0) {
                return [
                    'status' => 'degraded',
                    'message' => 'Queue has failed jobs',
                    'details' => ['failed_jobs' => $failedJobs]
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Queue is working properly',
                'details' => ['failed_jobs' => 0]
            ];
        } catch (\Exception $e) {
            Log::error('Queue health check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkExternalApi(): array
    {
        $recentErrors = $this->getRecentApiErrors();
        
        if ($recentErrors >= self::ERROR_THRESHOLD) {
            return [
                'status' => 'unhealthy',
                'message' => 'External API has too many recent errors',
                'details' => ['recent_errors' => $recentErrors]
            ];
        }
        
        if ($recentErrors > 0) {
            return [
                'status' => 'degraded',
                'message' => 'External API has some recent errors',
                'details' => ['recent_errors' => $recentErrors]
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => 'External API is working properly',
            'details' => ['recent_errors' => 0]
        ];
    }

    private function checkStorage(): array
    {
        try {
            $disk = storage_path();
            $freeSpace = disk_free_space($disk);
            $totalSpace = disk_total_space($disk);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = ($usedSpace / $totalSpace) * 100;
            
            if ($usagePercent > 90) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Storage usage is too high',
                    'details' => [
                        'usage_percent' => round($usagePercent, 2),
                        'free_space_mb' => round($freeSpace / 1024 / 1024, 2)
                    ]
                ];
            }
            
            if ($usagePercent > 80) {
                return [
                    'status' => 'degraded',
                    'message' => 'Storage usage is high',
                    'details' => [
                        'usage_percent' => round($usagePercent, 2),
                        'free_space_mb' => round($freeSpace / 1024 / 1024, 2)
                    ]
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Storage is adequate',
                'details' => [
                    'usage_percent' => round($usagePercent, 2),
                    'free_space_mb' => round($freeSpace / 1024 / 1024, 2)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Storage health check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function getRecentApiErrors(): int
    {
        $cutoffTime = now()->subMinutes(self::ERROR_WINDOW_MINUTES);
        
        return RecognitionAnalytics::where('created_at', '>=', $cutoffTime)
            ->where('status_code', '>=', 400)
            ->count();
    }

    private function determineOverallStatus(array $checks): string
    {
        $hasUnhealthy = false;
        $hasDegraded = false;
        
        foreach ($checks as $check) {
            if ($check['status'] === 'unhealthy') {
                $hasUnhealthy = true;
            } elseif ($check['status'] === 'degraded') {
                $hasDegraded = true;
            }
        }
        
        if ($hasUnhealthy) {
            return 'unhealthy';
        }
        
        if ($hasDegraded) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    private function updateErrorFlag(bool $isHealthy): void
    {
        if ($isHealthy) {
            Cache::forget(self::ERROR_FLAG_KEY);
        } else {
            Cache::put(self::ERROR_FLAG_KEY, true, now()->addMinutes(30));
        }
    }

    public function hasErrorFlag(): bool
    {
        return Cache::has(self::ERROR_FLAG_KEY);
    }
} 