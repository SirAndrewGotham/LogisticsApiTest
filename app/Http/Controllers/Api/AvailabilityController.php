<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slot;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Return all slots with their `id`, `capacity`, and `remaining`, ordered by `id`.
     *
     * @return array An array of slot records; each element contains the keys `id`, `capacity`, and `remaining`.
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
