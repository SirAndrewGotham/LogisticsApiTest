<?php

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('hold can be created with required fields', function () {
    $slot = Slot::factory()->create();
    $hold = Hold::create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
        'expires_at' => now()->addMinutes(5),
    ]);

    expect($hold->slot_id)->toBe($slot->id)
        ->and($hold->status)->toBe('held')
        ->and($hold->idempotency_key)->not->toBeNull()
        ->and($hold->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
});

test('hold belongs to slot', function () {
    $slot = Slot::factory()->create();
    $hold = Hold::factory()->create(['slot_id' => $slot->id]);

    expect($hold->slot)->toBeInstanceOf(Slot::class)
        ->and($hold->slot->id)->toBe($slot->id);
});

test('hold is expired when expires_at is in the past', function () {
    $hold = Hold::factory()->create([
        'expires_at' => now()->subMinute(),
    ]);

    expect($hold->isExpired())->toBeTrue();
});

test('hold is not expired when expires_at is in the future', function () {
    $hold = Hold::factory()->create([
        'expires_at' => now()->addMinutes(5),
    ]);

    expect($hold->isExpired())->toBeFalse();
});

test('hold can be marked as confirmed', function () {
    $hold = Hold::factory()->create(['status' => 'held']);

    $hold->markAsConfirmed();

    expect($hold->fresh()->status)->toBe('confirmed');
});

test('hold can be marked as cancelled', function () {
    $hold = Hold::factory()->create(['status' => 'held']);

    $hold->markAsCancelled();

    expect($hold->fresh()->status)->toBe('cancelled');
});

test('hold can be marked as expired', function () {
    $hold = Hold::factory()->create(['status' => 'held']);

    $hold->markAsExpired();

    expect($hold->fresh()->status)->toBe('expired');
});

test('hold expired scope returns only expired held holds', function () {
    Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->subMinute(),
    ]);
    Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);
    Hold::factory()->create([
        'status' => 'confirmed',
        'expires_at' => now()->subMinute(),
    ]);

    $expiredHolds = Hold::expired()->get();

    expect($expiredHolds)->toHaveCount(1)
        ->and($expiredHolds->first()->status)->toBe('held')
        ->and($expiredHolds->first()->isExpired())->toBeTrue();
});

test('hold factory creates valid hold', function () {
    $hold = Hold::factory()->create();

    expect($hold->slot_id)->not->toBeNull()
        ->and($hold->status)->toBe('held')
        ->and($hold->idempotency_key)->not->toBeNull()
        ->and($hold->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
});

test('hold factory confirmed state creates confirmed hold', function () {
    $hold = Hold::factory()->confirmed()->create();

    expect($hold->status)->toBe('confirmed');
});

test('hold factory cancelled state creates cancelled hold', function () {
    $hold = Hold::factory()->cancelled()->create();

    expect($hold->status)->toBe('cancelled');
});

test('hold factory expired state creates expired hold', function () {
    $hold = Hold::factory()->expired()->create();

    expect($hold->isExpired())->toBeTrue();
});

test('hold expires_at is cast to datetime', function () {
    $hold = Hold::factory()->create();

    expect($hold->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
});

test('idempotency key is unique', function () {
    $key = \Illuminate\Support\Str::uuid()->toString();
    Hold::factory()->create(['idempotency_key' => $key]);

    Hold::factory()->create(['idempotency_key' => $key]);
})->throws(Illuminate\Database\QueryException::class);

test('hold must have valid status', function () {
    $slot = Slot::factory()->create();

    Hold::create([
        'slot_id' => $slot->id,
        'status' => 'invalid_status',
        'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
        'expires_at' => now()->addMinutes(5),
    ]);
})->throws(Illuminate\Database\QueryException::class);