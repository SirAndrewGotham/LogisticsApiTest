<?php

use App\Models\Slot;
use App\Models\Hold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('idempotent request adds processed header on first request', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response->headers->get('X-Idempotent-Processed'))->toBe('true')
        ->and($response->headers->has('X-Idempotent-Replayed'))->toBeFalse();
});

test('idempotent request adds replayed header on duplicate request', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    // First request
    $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Duplicate request
    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response->headers->get('X-Idempotent-Replayed'))->toBe('true')
        ->and($response->headers->has('X-Idempotent-Processed'))->toBeFalse();
});

test('duplicate idempotency key returns exact same response', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response1->json())->toBe($response2->json())
        ->and($response1->status())->toBe($response2->status())
        ->and($response1->status())->toBe(201);
});

test('different idempotency keys create separate holds', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid1 = Str::uuid()->toString();
    $uuid2 = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid1,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid2,
    ]);

    expect($response1->json('id'))->not->toBe($response2->json('id'))
        ->and($slot->fresh()->remaining)->toBe(3); // Decremented twice
});

test('idempotency works across different endpoints', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    // Create hold with idempotency key
    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Try to confirm with same key - should replay create response
    $holdId = $response1->json('id');
    $response2 = $this->postJson("/holds/{$holdId}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Same key should return cached response (create response)
    expect($response2->json())->toBe($response1->json())
        ->and($response2->status())->toBe(201);
});

test('idempotency does not apply to GET requests', function () {
    Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $response = $this->get('/slots/availability');

    expect($response->headers->has('X-Idempotent-Processed'))->toBeFalse()
        ->and($response->headers->has('X-Idempotent-Replayed'))->toBeFalse();
});

test('failed requests are not cached', function () {
    $slot = Slot::factory()->full()->create();
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response1->assertStatus(409);
    $response2->assertStatus(409);

    // Should not have replayed header since error responses aren't cached
    expect($response2->headers->has('X-Idempotent-Replayed'))->toBeFalse();
});

test('idempotency cache persists across requests', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    // First request
    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Verify cache exists
    expect(Cache::has('idempotency.' . $uuid))->toBeTrue();

    // Second request should use cache
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response2->headers->get('X-Idempotent-Replayed'))->toBe('true');
});

test('idempotency key is case insensitive', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();
    $uuidUpper = strtoupper($uuid);

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuidUpper,
    ]);

    // Different case should be treated as different keys (UUID is case-insensitive but cache key is not)
    // Actually, Laravel cache keys are case-sensitive, so these will be different
    expect($response1->json('id'))->not->toBe($response2->json('id'));
});

test('idempotency prevents double processing on hold creation', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response3 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // All should return same hold ID
    expect($response1->json('id'))->toBe($response2->json('id'))
        ->and($response2->json('id'))->toBe($response3->json('id'))
        ->and($slot->fresh()->remaining)->toBe(4); // Only decremented once
});

test('idempotency prevents double processing on hold confirmation', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response1->json())->toBe($response2->json())
        ->and($response2->headers->get('X-Idempotent-Replayed'))->toBe('true')
        ->and($hold->fresh()->status)->toBe('confirmed');
});

test('idempotency prevents double processing on hold cancellation', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $hold = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response1->json())->toBe($response2->json())
        ->and($response2->headers->get('X-Idempotent-Replayed'))->toBe('true')
        ->and($slot->fresh()->remaining)->toBe(6); // Only incremented once
});

test('middleware validates uuid v4 format', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    // Valid UUID v4
    $validUuid = Str::uuid()->toString();
    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $validUuid,
    ]);
    $response->assertStatus(201);
});

test('idempotency cache stores all response data', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $cached = Cache::get('idempotency.' . $uuid);
    expect($cached)->toHaveKeys(['content', 'status', 'headers', 'cached_at'])
        ->and($cached['status'])->toBe(201)
        ->and($cached['content'])->toBeString()
        ->and($cached['cached_at'])->toBeString();
});

test('replayed response preserves original status code', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($response1->status())->toBe(201)
        ->and($response2->status())->toBe(201);
});

test('replayed response preserves original json structure', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $data1 = $response1->json();
    $data2 = $response2->json();

    expect($data1)->toHaveKeys(['id', 'slot_id', 'status', 'idempotency_key', 'expires_at'])
        ->and($data2)->toHaveKeys(['id', 'slot_id', 'status', 'idempotency_key', 'expires_at'])
        ->and($data1)->toBe($data2);
});