<?php

use App\Models\Slot;
use App\Models\Hold;
use App\Services\SlotService;
use App\Exceptions\SlotCapacityException;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\HoldNotConfirmableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SlotService();
    Cache::flush();
});

test('get available slots returns slot data', function () {
    $slot1 = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $slot2 = Slot::factory()->create(['capacity' => 20, 'remaining' => 10]);

    $result = $this->service->getAvailableSlots();

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toHaveKeys(['slot_id', 'capacity', 'remaining'])
        ->and($result[0]['slot_id'])->toBe($slot1->id)
        ->and($result[0]['capacity'])->toBe(10)
        ->and($result[0]['remaining'])->toBe(5)
        ->and($result[1]['slot_id'])->toBe($slot2->id);
});

test('get available slots caches results', function () {
    Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $result1 = $this->service->getAvailableSlots();
    $result2 = $this->service->getAvailableSlots();

    expect($result1)->toBe($result2)
        ->and(Cache::has('slots.availability'))->toBeTrue();
});

test('create hold successfully creates hold and decrements capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();

    $hold = $this->service->createHold($slot->id, $idempotencyKey);

    expect($hold)->toBeInstanceOf(Hold::class)
        ->and($hold->slot_id)->toBe($slot->id)
        ->and($hold->status)->toBe('held')
        ->and($hold->idempotency_key)->toBe($idempotencyKey)
        ->and($slot->fresh()->remaining)->toBe(4);
});

test('create hold throws exception when slot has no capacity', function () {
    $slot = Slot::factory()->full()->create();
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();

    $this->service->createHold($slot->id, $idempotencyKey);
})->throws(SlotCapacityException::class, 'No capacity available in this slot');

test('create hold throws exception for invalid idempotency key', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $this->service->createHold($slot->id, 'not-a-uuid');
})->throws(InvalidArgumentException::class);

test('create hold returns existing hold for duplicate idempotency key', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();

    $hold1 = $this->service->createHold($slot->id, $idempotencyKey);
    $hold2 = $this->service->createHold($slot->id, $idempotencyKey);

    expect($hold1->id)->toBe($hold2->id)
        ->and($slot->fresh()->remaining)->toBe(4); // Should only decrement once
});

test('create hold invalidates availability cache', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    // Populate cache
    $this->service->getAvailableSlots();
    expect(Cache::has('slots.availability'))->toBeTrue();

    // Create hold should invalidate cache
    $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
    $this->service->createHold($slot->id, $idempotencyKey);

    expect(Cache::has('slots.availability'))->toBeFalse();
});

test('confirm hold successfully confirms valid hold', function () {
    $hold = Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->service->confirmHold($hold->id);

    expect($hold->fresh()->status)->toBe('confirmed');
});

test('confirm hold throws exception for expired hold', function () {
    $hold = Hold::factory()->expired()->create(['status' => 'held']);

    $this->service->confirmHold($hold->id);
})->throws(HoldExpiredException::class, 'Hold has expired');

test('confirm hold throws exception for non-held status', function () {
    $hold = Hold::factory()->confirmed()->create();

    $this->service->confirmHold($hold->id);
})->throws(HoldNotConfirmableException::class, 'Hold is not in a confirmable state');

test('confirm hold marks expired hold as expired when attempting confirm', function () {
    $hold = Hold::factory()->expired()->create(['status' => 'held']);

    try {
        $this->service->confirmHold($hold->id);
    } catch (HoldExpiredException $e) {
        // Expected
    }

    expect($hold->fresh()->status)->toBe('expired');
});

test('confirm hold invalidates availability cache', function () {
    $hold = Hold::factory()->create(['status' => 'held', 'expires_at' => now()->addMinutes(5)]);

    // Populate cache
    $this->service->getAvailableSlots();
    expect(Cache::has('slots.availability'))->toBeTrue();

    // Confirm hold should invalidate cache
    $this->service->confirmHold($hold->id);

    expect(Cache::has('slots.availability'))->toBeFalse();
});

test('cancel hold successfully cancels held hold and returns capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $hold = Hold::factory()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->service->cancelHold($hold->id);

    expect($hold->fresh()->status)->toBe('cancelled')
        ->and($slot->fresh()->remaining)->toBe(6);
});

test('cancel hold throws exception for non-held status', function () {
    $hold = Hold::factory()->confirmed()->create();

    $this->service->cancelHold($hold->id);
})->throws(HoldNotConfirmableException::class, 'Only held holds can be cancelled');

test('cancel hold does not return capacity for expired hold', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $hold = Hold::factory()->expired()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
    ]);

    $this->service->cancelHold($hold->id);

    expect($hold->fresh()->status)->toBe('cancelled')
        ->and($slot->fresh()->remaining)->toBe(5); // Should not increment
});

test('cancel hold invalidates availability cache', function () {
    $hold = Hold::factory()->create(['status' => 'held', 'expires_at' => now()->addMinutes(5)]);

    // Populate cache
    $this->service->getAvailableSlots();
    expect(Cache::has('slots.availability'))->toBeTrue();

    // Cancel hold should invalidate cache
    $this->service->cancelHold($hold->id);

    expect(Cache::has('slots.availability'))->toBeFalse();
});

test('cleanup expired holds marks holds as expired and returns capacity', function () {
    $slot1 = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    $slot2 = Slot::factory()->create(['capacity' => 20, 'remaining' => 10]);

    Hold::factory()->expired()->create([
        'slot_id' => $slot1->id,
        'status' => 'held',
    ]);
    Hold::factory()->expired()->create([
        'slot_id' => $slot2->id,
        'status' => 'held',
    ]);
    Hold::factory()->create([
        'status' => 'held',
        'expires_at' => now()->addMinutes(5),
    ]); // Not expired

    $count = $this->service->cleanupExpiredHolds();

    expect($count)->toBe(2)
        ->and($slot1->fresh()->remaining)->toBe(6)
        ->and($slot2->fresh()->remaining)->toBe(11)
        ->and(Hold::where('status', 'expired')->count())->toBe(2);
});

test('cleanup expired holds does not process non-held holds', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    Hold::factory()->expired()->confirmed()->create([
        'slot_id' => $slot->id,
    ]);

    $count = $this->service->cleanupExpiredHolds();

    expect($count)->toBe(0)
        ->and($slot->fresh()->remaining)->toBe(5);
});

test('cleanup expired holds invalidates cache when holds are cleaned', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    Hold::factory()->expired()->create([
        'slot_id' => $slot->id,
        'status' => 'held',
    ]);

    // Populate cache
    $this->service->getAvailableSlots();
    expect(Cache::has('slots.availability'))->toBeTrue();

    // Cleanup should invalidate cache
    $this->service->cleanupExpiredHolds();

    expect(Cache::has('slots.availability'))->toBeFalse();
});

test('cleanup expired holds does not invalidate cache when no holds cleaned', function () {
    // Populate cache
    $this->service->getAvailableSlots();
    expect(Cache::has('slots.availability'))->toBeTrue();

    // Cleanup with no expired holds should not invalidate cache
    $count = $this->service->cleanupExpiredHolds();

    expect($count)->toBe(0)
        ->and(Cache::has('slots.availability'))->toBeTrue();
});

test('invalidate availability cache clears cache and lock', function () {
    Cache::put('slots.availability', ['data'], 60);

    $this->service->invalidateAvailabilityCache();

    expect(Cache::has('slots.availability'))->toBeFalse();
});

test('create hold handles concurrent requests with locking', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 1]);
    $idempotencyKey1 = \Illuminate\Support\Str::uuid()->toString();
    $idempotencyKey2 = \Illuminate\Support\Str::uuid()->toString();

    // First hold should succeed
    $hold1 = $this->service->createHold($slot->id, $idempotencyKey1);
    expect($hold1)->toBeInstanceOf(Hold::class)
        ->and($slot->fresh()->remaining)->toBe(0);

    // Second hold should fail - no capacity
    expect(fn() => $this->service->createHold($slot->id, $idempotencyKey2))
        ->toThrow(SlotCapacityException::class);
});

test('get available slots returns empty array when no slots exist', function () {
    $result = $this->service->getAvailableSlots();

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(0);
});