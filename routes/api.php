<?php
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\HoldController;
use Illuminate\Support\Facades\Route;

Route::prefix('slots')->group(function () {
    Route::get('availability', AvailabilityController::class);

    Route::post('{slot}/hold', [SlotController::class, 'hold'])
        ->where('slot', '[0-9]+'); // Validate slot is numeric

});

Route::prefix('holds')->group(function () {
    Route::post('/{hold}/confirm', [HoldController::class, 'confirm'])
        ->where('hold', '[0-9]+'); // Validate hold is numeric

    Route::delete('/{hold}', [HoldController::class, 'destroy'])
        ->where('hold', '[0-9]+'); // Validate hold is numeric
});
