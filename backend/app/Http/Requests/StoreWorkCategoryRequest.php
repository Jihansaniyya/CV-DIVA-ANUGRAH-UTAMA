<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'kode' => ['nullable', 'string', 'max:10'],
            'nama' => ['required', 'string', 'max:150'],
            'urutan' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return ['nama' => 'nama kelompok pekerjaan'];
    }
}
