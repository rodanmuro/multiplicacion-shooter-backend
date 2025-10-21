<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para registrar un disparo (Shot)
 */
class RecordShotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // La autorización se gestiona por middleware auth.google y pertenencia en el controlador
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
            'shot_at' => ['required', 'date'],
            'coordinate_x' => ['required', 'numeric', 'min:0', 'max:1200'],
            'coordinate_y' => ['required', 'numeric', 'min:0', 'max:800'],
            'factor_1' => ['required', 'integer', 'min:1', 'max:12'],
            'factor_2' => ['required', 'integer', 'min:1', 'max:12'],
            'correct_answer' => ['required', 'integer', 'min:0', 'max:144'],
            'card_value' => ['required', 'integer', 'min:0', 'max:144'],
            'is_correct' => ['required', 'boolean'],
        ];
    }
}

