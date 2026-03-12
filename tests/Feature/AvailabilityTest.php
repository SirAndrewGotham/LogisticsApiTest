<?php

use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('availability endpoint returns all slots', function () {
    Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);
    Slot::factory()->create(['capacity' => 20, 'remaining' => 10]);

    $response = $this->get('/slots/availability');

    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonStructure([
            '*' => ['slot_id', 'capacity', 'remaining']
        ]);
});

test('availability endpoint returns empty array when no slots', function () {
    $response = $this->get('/slots/availability');

    $response->assertStatus(200)
        ->assertJson([]);
});

test('availability endpoint returns slots ordered by id', function () {
    $slot1 = Slot::factory()->create();
    $slot2 = Slot::factory()->create();
    $slot3 = Slot::factory()->create();

    $response = $this->get('/slots/availability');

    $data = $response->json();
    expect($data[0]['slot_id'])->toBe($slot1->id)
        ->and($data[1]['slot_id'])->toBe($slot2->id)
        ->and($data[2]['slot_id'])->toBe($slot3->id);
});

test('availability endpoint includes full capacity slots', function () {
    Slot::factory()->create(['capacity' => 10, 'remaining' => 0]);
    Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $response = $this->get('/slots/availability');

    $response->assertStatus(200)
        ->assertJsonCount(2);
});

test('availability endpoint returns correct slot data format', function () {
    $slot = Slot::factory()->create([
        'capacity' => 15,
        'remaining' => 7,
    ]);

    $response = $this->get('/slots/availability');

    $response->assertStatus(200)
        ->assertJson([
            [
                'slot_id' => $slot->id,
                'capacity' => 15,
                'remaining' => 7,
            ]
        ]);
});

test('availability endpoint does not include timestamps', function () {
    Slot::factory()->create();

    $response = $this->get('/slots/availability');

    $data = $response->json();
    expect($data[0])->not->toHaveKey('created_at')
        ->and($data[0])->not->toHaveKey('updated_at');
});

test('availability endpoint response is not wrapped in data key', function () {
    Slot::factory()->count(2)->create();

    $response = $this->get('/slots/availability');

    $data = $response->json();
    // Should be array directly, not wrapped
    expect($data)->toBeArray()
        ->and($data)->toHaveCount(2)
        ->and($data)->not->toHaveKey('data');
});

test('availability endpoint handles large number of slots', function () {
    Slot::factory()->count(50)->create();

    $response = $this->get('/slots/availability');

    $response->assertStatus(200)
        ->assertJsonCount(50);
});

test('availability endpoint returns fresh data after slot update', function () {
    $slot = Slot::factory()->create(['capacity' => 10, 'remaining' => 5]);

    $response1 = $this->get('/slots/availability');
    expect($response1->json()[0]['remaining'])->toBe(5);

    // Update slot
    $slot->update(['remaining' => 3]);
    \Illuminate\Support\Facades\Cache::flush(); // Clear cache

    $response2 = $this->get('/slots/availability');
    expect($response2->json()[0]['remaining'])->toBe(3);
});