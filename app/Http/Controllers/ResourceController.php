<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ResourceController extends Controller
{
    public function limitedOperation()
    {
        $key = 'resource-limit'.request()->ip();

        // السماح بـ 3 عمليات فقط بالدقيقة
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many operations'
            ], 429);
        }

        RateLimiter::hit($key, 60); // 60 ثانية

        return response()->json([
            'message' => 'Operation executed successfully'
        ], 200);
    }
}
