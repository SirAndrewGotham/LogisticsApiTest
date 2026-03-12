<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    private const string IDEMPOTENCY_CACHE_PREFIX = 'idempotency.';
    private const int IDEMPOTENCY_TTL_SECONDS = 86400; // 24 hours

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to POST, PUT, PATCH, DELETE methods
        if (!$this->shouldApplyIdempotency($request)) {
            return $next($request);
        }

        // Get idempotency key from header
        $idempotencyKey = $request->header('Idempotency-Key');

        // Generate key if not provided
        if (!$idempotencyKey) {
            $idempotencyKey = Str::uuid()->toString();
            $request->headers->set('Idempotency-Key', $idempotencyKey);
        }

        // Validate UUID format
        if (!Str::isUuid($idempotencyKey)) {
            return response()->json([
                'error' => 'Invalid Idempotency-Key format. Must be a valid UUID v4.',
            ], 400);
        }

        // Generate cache key based on method, path, and idempotency key
        $cacheKey = $this->generateCacheKey($request, $idempotencyKey);

        // Check if we have a cached response
        if ($cachedResponse = Cache::get($cacheKey)) {
            return $this->replayCachedResponse($cachedResponse);
        }

        // Process the request and cache the response
        return $this->processAndCache($request, $next, $cacheKey);
    }

    /**
     * Determine if idempotency should be applied to this request.
     */
    private function shouldApplyIdempotency(Request $request): bool
    {
        $method = $request->method();

        // Apply to write operations only
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Generate a unique cache key for the request.
     */
    private function generateCacheKey(Request $request, string $idempotencyKey): string
    {
        $method = $request->method();
        $path = $request->path();
        $userId = $request->user()?->id ?: 'guest';

        return self::IDEMPOTENCY_CACHE_PREFIX .
            md5("{$method}:{$path}:{$idempotencyKey}:{$userId}");
    }

    /**
     * Replay a previously cached response.
     */
    private function replayCachedResponse(array $cachedResponse): Response
    {
        $response = new Response(
            $cachedResponse['content'],
            $cachedResponse['status'],
            $cachedResponse['headers']
        );

        // Add header to indicate this is a cached response
        $response->headers->set('X-Idempotent-Replayed', 'true');

        return $response;
    }

    /**
     * Process request and cache the response.
     */
    private function processAndCache(Request $request, Closure $next, string $cacheKey): Response
    {
        // Process the request
        $response = $next($request);

        // Only cache successful responses (2xx)
        if ($response->isSuccessful()) {
            $this->cacheResponse($cacheKey, $response);
        }

        // Add header to indicate this is a new response
        $response->headers->set('X-Idempotent-Processed', 'true');

        return $response;
    }

    /**
     * Cache the response.
     */
    private function cacheResponse(string $cacheKey, Response $response): void
    {
        $cachedData = [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $cachedData, self::IDEMPOTENCY_TTL_SECONDS);
    }
}
