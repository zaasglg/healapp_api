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
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'numeric', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'account_type' => ['required', 'string', 'in:client,specialist,pansionat,agency'],
            'organization_name' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];
    }
}

