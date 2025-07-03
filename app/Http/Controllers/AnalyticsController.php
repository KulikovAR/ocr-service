<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Показывает дашборд аналитики
     */
    public function dashboard()
    {
        $stats = $this->analyticsService->getDashboardStats();
        
        return view('analytics.dashboard', compact('stats'));
    }
} 