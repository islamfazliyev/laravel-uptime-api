<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMonitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minDelay = 3;

        return [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'check_interval' => "required|integer|min:{$minDelay}",
        ];
    }

    public function messages(): array
    {
        return [
            'check_interval.min' => "Your current plan requires a minimum delay of :min minutes.",
        ];
    }
}
