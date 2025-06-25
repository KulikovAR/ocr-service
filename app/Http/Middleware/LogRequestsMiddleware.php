<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $requestData = $this->sanitizeRequestData($request);
        
        Log::info('Incoming request', $requestData);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $processingTime = ($endTime - $startTime) * 1000;
        
        $responseData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'processing_time_ms' => round($processingTime, 2),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'request_size' => $request->header('Content-Length', 0),
            'response_size' => strlen($response->getContent()),
        ];
        
        if ($response->getStatusCode() >= 400) {
            Log::error('Request failed', $responseData);
        } else {
            Log::info('Request completed', $responseData);
        }
        
        return $response;
    }
    
    private function sanitizeRequestData(Request $request): array
    {
        $data = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'query_params' => $request->query(),
            'body_params' => $request->except(['files']),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ];
        
        return $data;
    }
} 