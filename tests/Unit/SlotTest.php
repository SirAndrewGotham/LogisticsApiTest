<?php

use App\Models\Slot;
use App\Models\Hold;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('slot can be created with capacity and remaining', function () {
    $slot = Slot::create([
        'capacity' => 10,
        'remaining' => 5,
    ]);

    expect($slot->capacity)->toBe(10)
        ->and($slot->remaining)->toBe(5);
});

test('slot enforces remaining cannot be less than zero', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $slot->remaining = -1;

    $slot->save();
})->throws(InvalidArgumentException::class, 'Remaining cannot be less than 0');

test('slot enforces remaining cannot exceed capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $slot->remaining = 11;

    $slot->save();
})->throws(InvalidArgumentException::class, 'Remaining cannot exceed capacity');

test('slot has holds relationship', function () {
    $slot = Slot::factory()->create();

    expect($slot->holds())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('slot available scope filters slots with remaining capacity', function () {
    Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    Slot::factory()->create(['capacity' => 10, 'remaining' => 0]);
    Slot::factory()->create(['capacity' => 10, 'remaining' => 3]);

    $availableSlots = Slot::available()->get();

    expect($availableSlots)->toHaveCount(2)
        ->and($availableSlots->pluck('remaining')->min())->toBeGreaterThan(0);
});

test('slot decrement remaining decreases capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $result = $slot->decrementRemaining();

    expect($result)->toBeTrue()
        ->and($slot->fresh()->remaining)->toBe(4);
});

test('slot decrement remaining returns false when no capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 0]);

    $result = $slot->decrementRemaining();

    expect($result)->toBeFalse()
        ->and($slot->fresh()->remaining)->toBe(0);
});

test('slot increment remaining increases capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $result = $slot->incrementRemaining();

    expect($result)->toBeTrue()
        ->and($slot->fresh()->remaining)->toBe(6);
});

test('slot increment remaining throws exception when at capacity', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 10]);

    $slot->incrementRemaining();
})->throws(RuntimeException::class, 'Cannot increment remaining beyond capacity');

test('slot factory creates valid slots', function () {
    $slot = Slot::factory()->create();

    expect($slot->capacity)->toBeGreaterThanOrEqual(1)
        ->and($slot->remaining)->toBeGreaterThanOrEqual(0)
        ->and($slot->remaining)->toBeLessThanOrEqual($slot->capacity);
});

test('slot factory available state creates slot with remaining capacity', function () {
    $slot = Slot::factory()->available()->create();

    expect($slot->remaining)->toBeGreaterThan(0);
});

test('slot factory full state creates slot with no remaining capacity', function () {
    $slot = Slot::factory()->full()->create();

    expect($slot->remaining)->toBe(0);
});

test('slot attributes are properly cast', function () {
    $slot = Slot::factory()->create();

    expect($slot->capacity)->toBeInt()
        ->and($slot->remaining)->toBeInt();
});

test('slot validation prevents negative capacity on update', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $slot->capacity = 3;
    $slot->save();
})->throws(InvalidArgumentException::class, 'Remaining cannot exceed capacity');