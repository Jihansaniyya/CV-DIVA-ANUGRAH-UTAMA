<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('user')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role_id' => ['sometimes', 'required', 'exists:roles,id'],
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
