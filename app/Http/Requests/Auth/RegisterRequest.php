<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        // Если передан invite_token, account_type не обязателен
        $rules = [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'numeric', 'unique:users,phone'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'invite_token' => ['nullable', 'string', 'max:64'],
            'account_type' => ['required_without:invite_token', 'string', 'in:client,specialist,pansionat,agency'],
            'organization_name' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];

        return $rules;
    }
}

