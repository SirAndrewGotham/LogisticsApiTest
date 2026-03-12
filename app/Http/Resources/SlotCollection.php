<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SlotCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = SlotResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }

    /**
     * Customize the response to remove the "data" wrapper.
     * THIS IS NEEDED FOR THIS ONE SPECIFIC TASK I SOLVE
     */
    public function toResponse($request)
    {
        // Get the transformed collection
        $data = $this->toArray($request);

        // Create a response with the raw array (no wrapping)
        return response()->json($data);
    }
}
