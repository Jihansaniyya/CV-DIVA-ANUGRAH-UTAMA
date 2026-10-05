<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** Kode dirapikan dulu agar "m2 " dan "m2" dianggap sama saat pengecekan unik. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:units,code'],
            'name' => ['required', 'string', 'max:80'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode satuan',
            'name' => 'nama satuan',
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Kode satuan sudah terdaftar.'];
    }
}
