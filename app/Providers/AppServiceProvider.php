<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    protected function configureRateLimiters(): void
    {
        // ─── Login: 5/min per IP (unauthenticated) ────────────────
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // ─── Register: 3/min per IP (unauthenticated) ─────────────
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // ─── Sensor data: 30/min per user (token hash) ────────────
        RateLimiter::for('sensor-data', function (Request $request) {
            return Limit::perMinute(30)->by($this->userKey($request, 'sensor'));
        });

        // ─── FCM token: 10/min per user (token hash) ──────────────
        RateLimiter::for('fcm-token', function (Request $request) {
            return Limit::perMinute(10)->by($this->userKey($request, 'fcm'));
        });

        // ─── Everything else: 60/min per user (token hash) ────────
        RateLimiter::for('api-general', function (Request $request) {
            return Limit::perMinute(60)->by($this->userKey($request, 'api'));
        });
    }

    /**
     * Build a rate-limit key that prefers the bearer token (hashed),
     * falling back to IP when no token is present.
     */
    protected function userKey(Request $request, string $prefix): string
    {
        $token = $request->bearerToken();

        if ($token) {
            return $prefix . ':token:' . hash('sha256', $token);
        }

        return $prefix . ':ip:' . $request->ip();
    }
}