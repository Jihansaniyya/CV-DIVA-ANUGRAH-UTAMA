<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'work_category_id' => ['nullable', 'exists:work_categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'uraian_pekerjaan' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'numeric', 'min:0.001'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'waktu_mulai' => ['nullable', 'date'],
            'waktu_selesai' => ['nullable', 'date', 'after_or_equal:waktu_mulai'],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'unit_id' => 'satuan pekerjaan', 'uraian_pekerjaan' => 'uraian pekerjaan',
            'volume' => 'volume pekerjaan', 'harga_satuan' => 'harga satuan',
            'waktu_mulai' => 'waktu mulai', 'waktu_selesai' => 'waktu selesai',
        ];
    }
}
