<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesWorkItemPeriods;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkItemRequest extends FormRequest
{
    use ValidatesWorkItemPeriods;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'work_category_id' => ['nullable', 'exists:work_categories,id'],
            'unit_id' => ['sometimes', 'required', 'exists:units,id'],
            'uraian_pekerjaan' => ['sometimes', 'required', 'string', 'max:255'],
            'volume' => ['sometimes', 'required', 'numeric', 'min:0.001'],
            'harga_satuan' => ['sometimes', 'required', 'numeric', 'min:0'],
            'period_mulai_id' => ['sometimes', 'required', ...$this->periodRule()],
            'period_selesai_id' => ['sometimes', 'required', ...$this->periodRule()],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'unit_id' => 'satuan pekerjaan', 'uraian_pekerjaan' => 'uraian pekerjaan',
            'volume' => 'volume pekerjaan', 'harga_satuan' => 'harga satuan',
            'period_mulai_id' => 'periode mulai', 'period_selesai_id' => 'periode selesai',
        ];
    }
}
