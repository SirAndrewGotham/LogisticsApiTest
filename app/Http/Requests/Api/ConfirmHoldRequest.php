<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmHoldRequest extends FormRequest
{
    /**
     * Allow all users to make this request.
     *
     * @return bool `true` if the request is authorized, `false` otherwise.
     */
    public function authorize(): bool
    {
        // In a real app, you might check if the user owns the hold
        return true;
    }

    /**
     * Define validation rules for the request; none are applied to the request body.
     *
     * The hold identifier is validated via the route parameter named `hold`, so this method returns an empty rules array.
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
         * Return the request's validated data augmented with the route-derived hold ID.
         *
         * When no `$key` is provided, the returned array includes a `hold_id` entry cast to
         * integer from the route parameter named `hold`.
         *
         * @param string|int|null $key Optional key to retrieve from the validated data.
         * @param mixed|null $default Default value to return when `$key` is provided and missing.
         * @return array|mixed The validated data (array when no `$key` is supplied) with `hold_id` added. 
         */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Include hold_id from route
        $validated['hold_id'] = (int) $this->route('hold');

        return $validated;
    }
}
