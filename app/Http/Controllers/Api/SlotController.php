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
    /**
     * Create a new SlotController instance.
     *
     * @param SlotService $slotService Service used to create and manage slot holds.
     */
    public function __construct(
        private SlotService $slotService
    ) {}

    / **
     * Create a hold for the specified slot.
     *
     * Creates a hold using the route slot identifier and the request's
     * "Idempotency-Key" header. On success, returns the created hold payload.
     *
     * @param HoldSlotRequest $request The validated request instance.
     * @param string $slot The slot identifier (UUID) from the route.
     * @return JsonResponse On success: the hold payload with HTTP 201. On capacity failure: JSON containing `error` (message), `slot_id` (the provided slot), and `remaining` set to 0 with HTTP 409.
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
