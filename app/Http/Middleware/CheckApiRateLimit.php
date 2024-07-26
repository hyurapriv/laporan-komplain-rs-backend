<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class CheckApiRateLimit
{
    protected $limit = 2000; // Maksimal permintaan per menit
    protected $timeFrame = 60; // Waktu dalam detik

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $ip = $request->ip();
        $key = 'rate_limit_' . $ip;
        $requests = Cache::get($key, 0);

        if ($requests >= $this->limit) {
            return response()->json(['error' => 'Too Many Requests'], 429);
        }

        Cache::put($key, $requests + 1, $this->timeFrame);

        return $next($request);
    }
}
