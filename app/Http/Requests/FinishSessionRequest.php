<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'finished_at' => ['required', 'date'],
            'final_score' => ['required', 'integer', 'min:0'],
            'max_level_reached' => ['required', 'integer', 'min:1'],
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:600'],
        ];
    }
}

