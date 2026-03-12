<?php

use Illuminate\Support\Facades\Route;

// Any web-specific routes can go here, I would probably put API documentation here
Route::get('/', function () {
    return response()->json([
        'name' => 'Slot Booking API',
        'version' => '1.0',
        'code_repo' => 'https://github.com/SirAndrewGotham/LogisticsApiTest'
    ]);
});
