<?php

namespace App\Http\Controllers\Api;

use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class HoldController
{
    public function __construct(
        private SlotService $slotService
    ) {}

    /**
     * Confirm a hold
     * POST /api/holds/{hold}/confirm
     */
    public function confirm(int $holdId): JsonResponse
    {
        try {
            $this->slotService->confirmHold($holdId);
            return response()->json(['message' => 'Hold confirmed']);
        } catch (HttpException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'hold_id' => $holdId,
            ], $e->getStatusCode());
        }
    }

    /**
     * Cancel a hold
     * DELETE /api/holds/{hold}
     */
    public function destroy(int $holdId): JsonResponse
    {
        try {
            $this->slotService->cancelHold($holdId);
            return response()->json(['message' => 'Hold cancelled']);
        } catch (HttpException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'hold_id' => $holdId,
            ], $e->getStatusCode());
        }
    }
}
