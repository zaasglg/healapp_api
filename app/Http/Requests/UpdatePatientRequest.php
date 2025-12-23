<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
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
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['sometimes', 'required', 'string', Rule::in(['male', 'female'])],
            'weight' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'mobility' => ['sometimes', 'required', 'string', Rule::in(['walking', 'sitting', 'bedridden'])],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*' => ['string', 'max:255'],
            'needed_services' => ['nullable', 'array'],
            'needed_services.*' => ['string', 'max:255'],
            'wishes' => ['nullable', 'array'],
            'wishes.*' => ['string', 'max:500'],
        ];
    }
}
