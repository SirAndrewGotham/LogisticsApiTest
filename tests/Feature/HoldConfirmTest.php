<?php

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('confirm hold succeeds for valid held hold', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Hold confirmed']);

    expect($hold->fresh()->status)->toBe('confirmed');
});

test('confirm hold updates status to confirmed', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($hold->fresh()->status)->toBe('confirmed');
});

test('confirm hold requires idempotency key', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->postJson("/holds/{$hold->id}/confirm");

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Idempotency-Key header is required for write operations.',
        ]);
});

test('confirm hold rejects invalid idempotency key', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => 'not-a-uuid',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Invalid Idempotency-Key format. Must be a valid UUID v4.',
        ]);
});

test('confirm hold fails for expired hold', function () {
    $hold = Hold::factory()->expired()->create([
        'status' => 'held',
    ]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500) // HttpException from service
        ->assertJsonStructure(['error', 'hold_id']);
});

test('confirm hold fails for already confirmed hold', function () {
    $hold = Hold::factory()->confirmed()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500)
        ->assertJsonStructure(['error', 'hold_id']);
});

test('confirm hold fails for cancelled hold', function () {
    $hold = Hold::factory()->cancelled()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500)
        ->assertJsonStructure(['error', 'hold_id']);
});

test('confirm hold fails for non-existent hold', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/99999/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(404);
});

test('confirm hold does not change slot capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $hold = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Remaining should stay the same (was already decremented on hold)
    expect($slot->fresh()->remaining)->toBe(5);
});

test('confirm hold only accepts numeric hold ids', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/abc/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(404);
});

test('confirm hold returns hold id in error response', function () {
    $hold = Hold::factory()->confirmed()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500)
        ->assertJson(['hold_id' => $hold->id]);
});

test('confirm hold with same idempotency key returns cached response', function () {
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

    $response1->assertStatus(200);
    $response2->assertStatus(200);

    // Both should return success (cached)
    expect($response1->json())->toBe($response2->json());
});

test('confirm hold marks expired hold as expired', function () {
    $hold = Hold::factory()->expired()->create([
        'status' => 'held',
    ]);
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/{$hold->id}/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500);
    expect($hold->fresh()->status)->toBe('expired');
});

test('confirm hold validates hold id is positive integer', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->postJson("/holds/-1/confirm", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Route constraint prevents negative numbers
    $response->assertStatus(404);
});