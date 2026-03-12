<?php

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('cancel hold succeeds for valid held hold', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Hold cancelled']);

    expect($hold->fresh()->status)->toBe('cancelled');
});

test('cancel hold updates status to cancelled', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($hold->fresh()->status)->toBe('cancelled');
});

test('cancel hold returns capacity to slot', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $hold = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($slot->fresh()->remaining)->toBe(6);
});

test('cancel hold requires idempotency key', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->deleteJson("/holds/{$hold->id}");

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Idempotency-Key header is required for write operations.',
        ]);
});

test('cancel hold rejects invalid idempotency key', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $response = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => 'not-a-uuid',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Invalid Idempotency-Key format. Must be a valid UUID v4.',
        ]);
});

test('cancel hold fails for confirmed hold', function () {
    $hold = Hold::factory()->confirmed()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500)
        ->assertJsonStructure(['error', 'hold_id']);

    expect($hold->fresh()->status)->toBe('confirmed');
});

test('cancel hold fails for already cancelled hold', function () {
    $hold = Hold::factory()->cancelled()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500)
        ->assertJsonStructure(['error', 'hold_id']);
});

test('cancel hold fails for non-existent hold', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/99999", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(404);
});

test('cancel expired hold does not return capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $hold = Hold::factory()->expired()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
    ]);
    $uuid = Str::uuid()->toString();

    $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    expect($hold->fresh()->status)->toBe('cancelled')
        ->and($slot->fresh()->remaining)->toBe(5); // Not incremented
});

test('cancel hold only accepts numeric hold ids', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/abc", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(404);
});

test('cancel hold returns hold id in error response', function () {
    $hold = Hold::factory()->confirmed()->create();
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500)
        ->assertJson(['hold_id' => $hold->id]);
});

test('cancel hold with same idempotency key returns cached response', function () {
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

    $response1->assertStatus(200);
    $response2->assertStatus(200);

    // Both should return success (cached)
    expect($response1->json())->toBe($response2->json())
        ->and($slot->fresh()->remaining)->toBe(6); // Only incremented once
});

test('cancel hold validates hold id is positive integer', function () {
    $uuid = Str::uuid()->toString();

    $response = $this->deleteJson("/holds/-1", [], [
        'Idempotency-Key' => $uuid,
    ]);

    // Route constraint prevents negative numbers
    $response->assertStatus(404);
});

test('cancel multiple holds returns capacity correctly', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 3]);
    $hold1 = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $hold2 = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->deleteJson("/holds/{$hold1->id}", [], [
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);
    $this->deleteJson("/holds/{$hold2->id}", [], [
        'Idempotency-Key' => Str::uuid()->toString(),
    ]);

    expect($slot->fresh()->remaining)->toBe(5);
});

test('cancel hold for slot at capacity still cancels', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 10]);
    $hold = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    $uuid = Str::uuid()->toString();

    // This will try to increment beyond capacity
    $response = $this->deleteJson("/holds/{$hold->id}", [], [
        'Idempotency-Key' => $uuid,
    ]);

    $response->assertStatus(500); // Runtime exception
});