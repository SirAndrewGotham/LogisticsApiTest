<?php

use App\Models\Slot;
use App\Http\Resources\SlotResource;
use App\Http\Resources\SlotCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('slot resource transforms slot correctly', function () {
    $slot = Slot::factory()->create([
        'capacity' => 10,
        'remaining' => 5,
    ]);

    $resource = new SlotResource($slot);
    $array = $resource->toArray(Request::create('/'));

    expect($array)->toHaveKeys(['slot_id', 'capacity', 'remaining'])
        ->and($array['slot_id'])->toBe($slot->id)
        ->and($array['capacity'])->toBe(10)
        ->and($array['remaining'])->toBe(5);
});

test('slot resource handles zero remaining', function () {
    $slot = Slot::factory()->create([
        'capacity' => 10,
        'remaining' => 0,
    ]);

    $resource = new SlotResource($slot);
    $array = $resource->toArray(Request::create('/'));

    expect($array['remaining'])->toBe(0);
});

test('slot collection transforms multiple slots', function () {
    $slots = Slot::factory()->count(3)->create();

    $collection = new SlotCollection($slots);
    $array = $collection->toArray(Request::create('/'));

    expect($array)->toBeArray()
        ->and($array)->toHaveCount(3)
        ->and($array[0])->toHaveKeys(['slot_id', 'capacity', 'remaining']);
});

test('slot collection removes data wrapper in response', function () {
    $slots = Slot::factory()->count(2)->create();

    $collection = new SlotCollection($slots);
    $response = $collection->toResponse(Request::create('/'));

    $content = json_decode($response->getContent(), true);

    // Should be array directly, not wrapped in 'data' key
    expect($content)->toBeArray()
        ->and($content)->toHaveCount(2)
        ->and($content)->not->toHaveKey('data')
        ->and($content[0])->toHaveKey('slot_id');
});

test('slot collection handles empty collection', function () {
    $collection = new SlotCollection(collect([]));
    $array = $collection->toArray(Request::create('/'));

    expect($array)->toBeArray()
        ->and($array)->toHaveCount(0);
});

test('slot collection uses slot resource for items', function () {
    $collection = new SlotCollection(collect([]));

    expect($collection->collects)->toBe(SlotResource::class);
});

test('slot resource transformation is consistent', function () {
    $slot = Slot::factory()->create([
        'id' => 1,
        'capacity' => 100,
        'remaining' => 50,
    ]);

    $resource1 = new SlotResource($slot);
    $resource2 = new SlotResource($slot);

    $array1 = $resource1->toArray(Request::create('/'));
    $array2 = $resource2->toArray(Request::create('/'));

    expect($array1)->toBe($array2);
});

test('slot collection preserves slot ordering', function () {
    $slot1 = Slot::factory()->create(['id' => 1]);
    $slot2 = Slot::factory()->create(['id' => 2]);
    $slot3 = Slot::factory()->create(['id' => 3]);

    $collection = new SlotCollection(collect([$slot1, $slot2, $slot3]));
    $array = $collection->toArray(Request::create('/'));

    expect($array[0]['slot_id'])->toBe(1)
        ->and($array[1]['slot_id'])->toBe(2)
        ->and($array[2]['slot_id'])->toBe(3);
});