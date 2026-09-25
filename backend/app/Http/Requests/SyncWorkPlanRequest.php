<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncWorkPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.work_item_id' => ['required', 'integer', 'exists:work_items,id'],
            'rows.*.period_id' => ['required', 'integer', 'exists:periods,id'],
            'rows.*.target_volume' => ['required', 'numeric', 'min:0'],
            'rows.*.catatan' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rows' => 'baris rencana',
            'rows.*.work_item_id' => 'pekerjaan',
            'rows.*.period_id' => 'periode',
            'rows.*.target_volume' => 'target volume',
        ];
    }
}
