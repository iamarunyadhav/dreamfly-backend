<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    protected const HIDDEN_FIELDS = ['password', 'password_confirmation', 'token'];

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        Log::channel('api')->info(sprintf('%s %s', $request->method(), $request->path()), [
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'payload' => $request->except(self::HIDDEN_FIELDS),
        ]);

        return $response;
    }
}
