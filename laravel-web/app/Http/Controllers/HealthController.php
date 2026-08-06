<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends BaseController
{
    /**
     * Health check endpoint - no auth required
     */
    public function check(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        return response()->json([
            'status' => 'ok',
            'version' => '1.0.0',
            'php' => phpversion(),
            'laravel' => app()->version(),
            'database' => $dbStatus,
            'time' => now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
