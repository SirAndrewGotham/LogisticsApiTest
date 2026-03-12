<?php
// app/Http\Controllers\Api/SlotController.php

namespace App\Http\Controllers\Api;

use App\Exceptions\SlotCapacityException;
use App\Http\Requests\Api\HoldSlotRequest;
use App\Models\Slot;
use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

readonly class SlotController
{
    public function __construct(
        private SlotService $slotService
    ) {}

    /**
     * Create a hold on a specific slot
     * POST /api/slots/{slot}/hold
     *
     * Note: Idempotency is now handled by IdempotencyMiddleware
     * The middleware validates UUID format and caches responses
     */
    public function hold(HoldSlotRequest $request, $slot): JsonResponse
    {
        // Request is already validated by HoldSlotRequest
        $validated = $request->validated();

        try {
            $hold = $this->slotService->createHold(
                $slot, // Use the route parameter directly
                $request->header('Idempotency-Key')
            );

            return response()->json($hold, 201);

        } catch (SlotCapacityException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'slot_id' => $slot, // Use the route parameter
                'remaining' => 0,
            ], 409);
        }
        // Note: InvalidArgumentException is caught by middleware
    }
}
