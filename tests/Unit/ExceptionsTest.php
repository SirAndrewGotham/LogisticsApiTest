<?php

use App\Exceptions\SlotCapacityException;
use App\Exceptions\HoldExpiredException;
use App\Exceptions\HoldNotConfirmableException;

test('slot capacity exception can be created with message', function () {
    $exception = new SlotCapacityException('No capacity');

    expect($exception->getMessage())->toBe('No capacity')
        ->and($exception->getSlotId())->toBeNull();
});

test('slot capacity exception uses default message', function () {
    $exception = new SlotCapacityException();

    expect($exception->getMessage())->toBe('No capacity available in this slot')
        ->and($exception->getSlotId())->toBeNull();
});

test('slot capacity exception can store slot id', function () {
    $exception = new SlotCapacityException('No capacity', 123);

    expect($exception->getMessage())->toBe('No capacity')
        ->and($exception->getSlotId())->toBe(123);
});

test('slot capacity exception extends exception', function () {
    $exception = new SlotCapacityException();

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('hold expired exception can be created', function () {
    $exception = new HoldExpiredException('Hold expired');

    expect($exception->getMessage())->toBe('Hold expired')
        ->and($exception)->toBeInstanceOf(Exception::class);
});

test('hold not confirmable exception can be created', function () {
    $exception = new HoldNotConfirmableException('Cannot confirm');

    expect($exception->getMessage())->toBe('Cannot confirm')
        ->and($exception)->toBeInstanceOf(Exception::class);
});

test('exceptions are throwable', function () {
    expect(fn() => throw new SlotCapacityException('test'))
        ->toThrow(SlotCapacityException::class, 'test');

    expect(fn() => throw new HoldExpiredException('test'))
        ->toThrow(HoldExpiredException::class, 'test');

    expect(fn() => throw new HoldNotConfirmableException('test'))
        ->toThrow(HoldNotConfirmableException::class, 'test');
});