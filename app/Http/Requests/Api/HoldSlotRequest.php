<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HoldSlotRequest extends FormRequest
{
    /**
     * Determine whether the current user is authorized to perform this request.
     *
     * @return bool `true` if the request is authorized, `false` otherwise.
     */
    public function authorize(): bool
    {
        // In a real app, you might check permissions here
        // For now, allow all authenticated users
        return true;
    }

    /**
     * Provide validation rules for this request; currently none are defined for the request body.
     *
     * Route parameter `slot` is validated via the route constraint ->where('slot', '[0-9]+') and is exposed as `slot_id` by validated().
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // No request body validation needed for now
            // Only route parameter validation via ->where('slot', '[0-9]+')
        ];
    }

    /**
     * Return the validated request data augmented with the route's slot ID.
     *
     * The returned array contains the usual validated input plus a `slot_id` key
     * derived from the route parameter `slot` cast to an integer.
     *
     * @param mixed $key Optional key to retrieve from the validated data.
     * @param mixed $default Default value to return when the specified key is not present.
     * @return array The validated data array including `slot_id`.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Include slot_id from route
        $validated['slot_id'] = (int) $this->route('slot');

        return $validated;
    }
}
