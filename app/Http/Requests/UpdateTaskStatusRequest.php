<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(['completed', 'missed', 'cancelled'])],
            'comment' => ['required_if:status,missed', 'nullable', 'string'],
            'value' => ['nullable', 'array'], // JSON value for measurements
            'completed_at' => ['nullable', 'date_format:Y-m-d H:i:s'], // Actual completion time
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required_if' => 'A comment is required when marking a task as missed.',
        ];
    }
}
