<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slot;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return Slot::query()
            ->select(['id', 'capacity', 'remaining'])
            ->orderBy('id')
            ->get()
            ->toArray();
    }
}
