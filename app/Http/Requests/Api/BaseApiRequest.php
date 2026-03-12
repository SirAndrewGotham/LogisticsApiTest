<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BaseApiRequest extends FormRequest
{
    /**
     * Validation rules for an ID parameter.
     *
     * Generates standard validation rules for the specified field name.
     *
     * @param string $field The field name to apply the rules to (default: "id").
     * @return array An associative array mapping the field name to its validation rules.
     */
    protected function idRules(string $field = 'id'): array
    {
        return [
            $field => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * Return custom validation messages for an ID field.
     *
     * @param string $field The request field name to target in message keys (e.g., "id" or "user_id").
     * @return array An associative array mapping validation rule keys for the given field (e.g., "{$field}.required") to their error messages.
     */
    protected function idMessages(string $field = 'id'): array
    {
        return [
            "{$field}.required" => "The {$field} is required",
            "{$field}.integer" => "The {$field} must be an integer",
            "{$field}.min" => "The {$field} must be a positive number",
        ];
    }
}
