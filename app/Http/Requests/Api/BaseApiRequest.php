<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BaseApiRequest extends FormRequest
{
    /**
     * Common rules for ID parameters.
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
     * Common messages for ID parameters.
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
