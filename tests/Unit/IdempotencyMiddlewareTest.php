<?php

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->middleware = new IdempotencyMiddleware();
    Cache::flush();
});

test('middleware allows GET requests without idempotency key', function () {
    $request = Request::create('/test', 'GET');
    $next = fn($req) => new Response('OK', 200);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');
});

test('middleware requires idempotency key for POST requests', function () {
    $request = Request::create('/test', 'POST');
    $next = fn($req) => new Response('OK', 201);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(400)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('error')
        ->and(json_decode($response->getContent(), true)['error'])
        ->toBe('Idempotency-Key header is required for write operations.');
});

test('middleware requires idempotency key for PUT requests', function () {
    $request = Request::create('/test', 'PUT');
    $next = fn($req) => new Response('OK', 200);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(400);
});

test('middleware requires idempotency key for PATCH requests', function () {
    $request = Request::create('/test', 'PATCH');
    $next = fn($req) => new Response('OK', 200);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(400);
});

test('middleware requires idempotency key for DELETE requests', function () {
    $request = Request::create('/test', 'DELETE');
    $next = fn($req) => new Response('OK', 200);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(400);
});

test('middleware validates idempotency key is valid UUID', function () {
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', 'not-a-uuid');
    $next = fn($req) => new Response('OK', 201);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(400)
        ->and(json_decode($response->getContent(), true)['error'])
        ->toBe('Invalid Idempotency-Key format. Must be a valid UUID v4.');
});

test('middleware processes request with valid idempotency key', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => new Response('Created', 201);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getContent())->toBe('Created')
        ->and($response->headers->has('X-Idempotent-Processed'))->toBeTrue();
});

test('middleware caches successful responses', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => new Response('Created', 201);

    $response = $this->middleware->handle($request, $next);

    expect(Cache::has('idempotency.' . $uuid))->toBeTrue();
    $cached = Cache::get('idempotency.' . $uuid);
    expect($cached)->toHaveKeys(['content', 'status', 'headers', 'cached_at'])
        ->and($cached['content'])->toBe('Created')
        ->and($cached['status'])->toBe(201);
});

test('middleware does not cache non-successful responses', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => new Response('Bad Request', 400);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(400)
        ->and(Cache::has('idempotency.' . $uuid))->toBeFalse();
});

test('middleware replays cached response for duplicate request', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $callCount = 0;
    $next = function ($req) use (&$callCount) {
        $callCount++;
        return new Response('Created', 201);
    };

    // First request
    $response1 = $this->middleware->handle($request, $next);
    expect($callCount)->toBe(1)
        ->and($response1->headers->get('X-Idempotent-Processed'))->toBe('true');

    // Second request with same key
    $response2 = $this->middleware->handle($request, $next);
    expect($callCount)->toBe(1) // Should not call next again
        ->and($response2->getContent())->toBe('Created')
        ->and($response2->getStatusCode())->toBe(201)
        ->and($response2->headers->get('X-Idempotent-Replayed'))->toBe('true')
        ->and($response2->headers->has('X-Idempotent-Processed'))->toBeFalse();
});

test('middleware generates cache key from idempotency key only', function () {
    $uuid = Str::uuid()->toString();
    $request1 = Request::create('/test1', 'POST');
    $request1->headers->set('Idempotency-Key', $uuid);
    $request2 = Request::create('/test2', 'POST');
    $request2->headers->set('Idempotency-Key', $uuid);

    $next = fn($req) => new Response('Created', 201);

    // First request
    $response1 = $this->middleware->handle($request1, $next);
    expect($response1->headers->get('X-Idempotent-Processed'))->toBe('true');

    // Second request with same UUID but different path - should still replay
    $response2 = $this->middleware->handle($request2, $next);
    expect($response2->headers->get('X-Idempotent-Replayed'))->toBe('true');
});

test('middleware handles JSON response correctly', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => response()->json(['id' => 123, 'status' => 'created'], 201);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(201)
        ->and(json_decode($response->getContent(), true))
        ->toBe(['id' => 123, 'status' => 'created']);
});

test('middleware replays JSON response correctly', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => response()->json(['id' => 123], 201);

    // First request
    $this->middleware->handle($request, $next);

    // Second request
    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(201)
        ->and(json_decode($response->getContent(), true))
        ->toBe(['id' => 123])
        ->and($response->headers->get('X-Idempotent-Replayed'))->toBe('true');
});

test('middleware accepts lowercase uuid', function () {
    $uuid = strtolower(Str::uuid()->toString());
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => new Response('Created', 201);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(201);
});

test('middleware accepts uppercase uuid', function () {
    $uuid = strtoupper(Str::uuid()->toString());
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => new Response('Created', 201);

    $response = $this->middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(201);
});

test('middleware cache key uses correct prefix', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = fn($req) => new Response('Created', 201);

    $this->middleware->handle($request, $next);

    expect(Cache::has('idempotency.' . $uuid))->toBeTrue();
});

test('middleware preserves response headers in cache', function () {
    $uuid = Str::uuid()->toString();
    $request = Request::create('/test', 'POST');
    $request->headers->set('Idempotency-Key', $uuid);
    $next = function ($req) {
        $response = new Response('Created', 201);
        $response->headers->set('X-Custom-Header', 'custom-value');
        return $response;
    };

    // First request
    $this->middleware->handle($request, $next);

    // Second request - should replay with headers
    $response = $this->middleware->handle($request, $next);

    expect($response->headers->get('X-Custom-Header'))->toBe('custom-value');
});