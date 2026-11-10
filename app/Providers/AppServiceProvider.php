<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiter pengiriman laporan: maks 10 per jam dan 3 per menit (admin tidak dibatasi)
        RateLimiter::for('reports', function (Request $request) {
            if ($request->user()?->role === 'admin') {
                return Limit::none();
            }

            $userId = $request->user()?->id ?: $request->ip();

            $perMenit = (int) config('domain.rate_limit.per_menit', 3);
            $perJam = (int) config('domain.rate_limit.per_jam', 10);

            $pesanThrottle = 'Terlalu banyak laporan dalam waktu singkat, coba lagi beberapa menit lagi';

            $responseCallback = function (Request $request, array $headers) use ($pesanThrottle) {
                return back()
                    ->withInput()
                    ->with('error', $pesanThrottle)
                    ->withErrors([
                        'foto' => $pesanThrottle,
                        'rate_limit' => $pesanThrottle,
                    ])
                    ->withHeaders($headers);
            };

            return [
                Limit::perMinute($perMenit)->by($userId.':minute')->response($responseCallback),
                Limit::perHour($perJam)->by($userId.':hour')->response($responseCallback),
            ];
        });
    }
}
