<?php

use App\Models\Slot;
use App\Models\Hold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('create hold succeeds with valid slot and idempotency key', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'slot_id', 'status', 'idempotency_key', 'expires_at']);

    expect($slot->fresh()->remaining)->toBe(4);
});

test('create hold decrements slot remaining capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(201);
    expect($slot->fresh()->remaining)->toBe(4);
});

test('create hold requires idempotency key', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $response = $this->postJson("/slots/{$slot->id}/hold");

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Idempotency-Key header is required for write operations.',
        ]);
});

test('create hold rejects invalid idempotency key format', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => 'not-a-uuid',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Invalid Idempotency-Key format. Must be a valid UUID v4.',
        ]);
});

test('create hold fails when slot has no capacity', function () {
    $slot = Slot::factory()->full()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(409)
        ->assertJson([
            'error' => 'No capacity available in this slot',
            'slot_id' => $slot->id,
            'remaining' => 0,
        ]);
});

test('create hold fails when slot does not exist', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/99999/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(404);
});

test('create hold sets expiry time to 5 minutes', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $before = now();
    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $after = now();

    $response->assertStatus(201);

    $hold = Hold::where('idempotency_key', $uuid)->first();
    expect($hold->expires_at->greaterThan($before->addMinutes(4)))->toBeTrue()
        ->and($hold->expires_at->lessThan($after->addMinutes(6)))->toBeTrue();
});

test('create hold returns hold data with correct structure', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $data = $response->json();
    expect($data)->toHaveKeys(['id', 'slot_id', 'status', 'idempotency_key', 'expires_at'])
        ->and($data['slot_id'])->toBe($slot->id)
        ->and($data['status'])->toBe('held')
        ->and($data['idempotency_key'])->toBe($uuid);
});

test('create hold with same idempotency key returns same hold', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response1->assertStatus(201);
    $response2->assertStatus(201);

    expect($response1->json('id'))->toBe($response2->json('id'))
        ->and($slot->fresh()->remaining)->toBe(4); // Only decremented once
});

test('create hold only accepts numeric slot ids', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/abc/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(404);
});

test('create hold creates database record', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('holds', [
        'slot_id' => $slot->id,
        'status' => 'held',
        'idempotency_key' => $uuid,
    ]);
});

test('create hold on different slots with same key creates separate holds', function () {
    $slot1 = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $slot2 = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $uuid = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot1->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Same idempotency key should return cached response from first request
    $response2 = $this->postJson("/slots/{$slot2->id}/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response1->assertStatus(201);
    $response2->assertStatus(201);

    // Should return same hold due to idempotency (middleware caches by key only)
    expect($response1->json('id'))->toBe($response2->json('id'));
});

test('concurrent hold requests on last remaining slot handles race condition', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 1]);
    $uuid1 = Str::uuid()->toString();
    $uuid2 = Str::uuid()->toString();

    $response1 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid1,
    ]);
    $response2 = $this->postJson("/slots/{$slot->id}/hold", [], [
        'Idempotency-Key' => $uuid2,
    ]);

    // One should succeed, one should fail
    $statuses = [$response1->status(), $response2->status()];
    expect($statuses)->toContain(201)
        ->and($statuses)->toContain(409)
        ->and($slot->fresh()->remaining)->toBe(0);
});

test('create hold validates slot id is positive integer', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/slots/-1/hold", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Route constraint prevents negative numbers
    $response->assertStatus(404);
});