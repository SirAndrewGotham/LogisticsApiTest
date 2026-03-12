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
     * Enforces idempotency for write HTTP methods by replaying cached responses or caching new ones keyed by an Idempotency-Key.
     *
     * For POST, PUT, PATCH, and DELETE requests the middleware reads or generates an Idempotency-Key (UUID v4), validates it,
     * and uses it together with the request method, path, and user identity to build a cache key. If a cached response exists it
     * is replayed; otherwise the request is processed and successful (2xx) responses are cached.
     *
     * @param  \Illuminate\Http\Request  $request The incoming HTTP request.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next The next middleware/handler.
     * @return \Symfony\Component\HttpFoundation\Response HTTP response — either a replayed cached response (contains `X-Idempotent-Replayed: true`),
     *         a newly processed response (contains `X-Idempotent-Processed: true`), or a 400 JSON error when the Idempotency-Key is not a valid UUID v4.
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
         * Determine whether idempotency should be enforced for the given HTTP request.
         *
         * @param Request $request The HTTP request to evaluate.
         * @return bool `true` if the request method is POST, PUT, PATCH, or DELETE; `false` otherwise.
         */
    private function shouldApplyIdempotency(Request $request): bool
    {
        $method = $request->method();

        // Apply to write operations only
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Builds a deterministic cache key for storing or retrieving an idempotent response.
     *
     * @param Request $request The HTTP request used to derive the HTTP method, path, and authenticated user (falls back to 'guest').
     * @param string $idempotencyKey The idempotency key (UUID) associated with the request.
     * @return string The generated cache key, including the idempotency prefix.
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
     * Construct an HTTP response from a previously cached response payload and mark it as replayed.
     *
     * @param array $cachedResponse Associative array with keys:
     *                              - 'content' (string): response body,
     *                              - 'status' (int): HTTP status code,
     *                              - 'headers' (array): response headers.
     * @return Response The reconstructed HTTP response. The returned response includes the
     *                  header `X-Idempotent-Replayed: true`.
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
         * Process the incoming request through the next handler and cache the response when successful.
         *
         * Caches the response under the provided cache key if the response status is in the 2xx range,
         * and adds the `X-Idempotent-Processed: true` header to the returned response.
         *
         * @param Request $request The incoming HTTP request.
         * @param Closure $next The next middleware or request handler.
         * @param string $cacheKey Cache key used to store the response if successful.
         * @return Response The HTTP response returned by the next handler; will include the `X-Idempotent-Processed` header. 
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
     * Store the given HTTP response in the idempotency cache under the provided cache key.
     *
     * Stores the response's content, status code, headers, and a `cached_at` timestamp using
     * the TTL defined by `IDEMPOTENCY_TTL_SECONDS`.
     *
     * @param string $cacheKey The cache key to store the response under.
     * @param Response $response The HTTP response to cache.
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
