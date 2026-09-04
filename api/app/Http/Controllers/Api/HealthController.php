<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Lightweight liveness probe: reports app status plus database and Redis checks.
     */
    public function index(): JsonResponse
    {
        $status = 'ok';
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'error';
            $status = 'degraded';
        }

        try {
            Cache::store('redis')->put('health-check', true, 10);
            $checks['redis'] = 'ok';
        } catch (\Throwable) {
            $checks['redis'] = 'error';
            $status = 'degraded';
        }

        return response()->json([
            'status' => $status,
            'service' => 'sajio-api',
            'version' => '0.1.0',
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ]);
    }
}
