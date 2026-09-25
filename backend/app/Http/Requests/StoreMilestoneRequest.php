<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['nullable', 'exists:periods,id'],
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'tanggal_target' => ['required', 'date'],
            'target_persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:BELUM_TERCAPAI,TERCAPAI,TERLAMBAT'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'nama milestone', 'tanggal_target' => 'tanggal target',
            'target_persentase' => 'target persentase',
        ];
    }
}
