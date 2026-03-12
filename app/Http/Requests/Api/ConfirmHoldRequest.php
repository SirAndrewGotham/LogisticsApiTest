<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmHoldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // In a real app, you might check if the user owns the hold
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
            // No body parameters needed for confirm action
            // All validation is in the route parameter
        ];
    }

    /**
     * Get the validated data from the request.
     * Override to include route parameters.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Include hold_id from route
        $validated['hold_id'] = (int) $this->route('hold');

        return $validated;
    }
}
