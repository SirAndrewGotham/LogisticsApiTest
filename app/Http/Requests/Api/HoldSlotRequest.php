<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HoldSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // In a real app, you might check permissions here
        // For now, allow all authenticated users
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
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
     * Get the validated data from the request.
     * Override to include route parameters.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Include slot_id from route
        $validated['slot_id'] = (int) $this->route('slot');

        return $validated;
    }
}
