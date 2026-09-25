<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama', 'username' => 'nama pengguna', 'password' => 'kata sandi',
            'role_id' => 'peran', 'is_active' => 'status akun',
        ];
    }
}
