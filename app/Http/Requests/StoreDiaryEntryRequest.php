<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiaryEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'type' => ['required', 'string', Rule::in(['care', 'physical', 'excretion', 'symptom'])],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required'],
            'notes' => ['nullable', 'string'],
            'recorded_at' => ['required', 'date'],
        ];
    }
}
