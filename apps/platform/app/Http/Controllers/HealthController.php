<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            Log::error('Health check database failure', ['exception' => $e::class]);

            return response()->json([
                'status' => 'unavailable',
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'application' => 'wasplex',
            'database' => 'ok',
        ]);
    }
}
