<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesWorkItemPeriods;
use App\Models\Period;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tambah kelompok pekerjaan beserta daftar pekerjaannya dalam satu kali simpan.
 * Kelompok dapat berupa kelompok yang sudah ada (work_category_id), kelompok
 * baru (kategori_baru), atau tanpa kelompok.
 */
class StoreWorkItemBatchRequest extends FormRequest
{
    use ValidatesWorkItemPeriods;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'work_category_id' => [
                'nullable', 'integer', 'prohibits:kategori_baru',
                Rule::exists('work_categories', 'id')->where('project_id', $project->id),
            ],
            'kategori_baru' => ['nullable', 'array'],
            'kategori_baru.kode' => ['nullable', 'string', 'max:10'],
            'kategori_baru.nama' => ['required_with:kategori_baru', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.uraian_pekerjaan' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'min:0.001'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'items.*.period_mulai_id' => ['required', ...$this->periodRule()],
            'items.*.period_selesai_id' => ['required', ...$this->periodRule()],
            'items.*.keterangan' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $urutan = Period::whereIn('id', collect($this->input('items', []))
                    ->flatMap(fn ($item) => [$item['period_mulai_id'] ?? null, $item['period_selesai_id'] ?? null])
                    ->filter())
                    ->pluck('urutan', 'id');

                foreach ($this->input('items', []) as $index => $item) {
                    $mulai = $urutan[$item['period_mulai_id'] ?? 0] ?? null;
                    $selesai = $urutan[$item['period_selesai_id'] ?? 0] ?? null;

                    if ($mulai !== null && $selesai !== null && $selesai < $mulai) {
                        $validator->errors()->add("items.$index.period_selesai_id", 'Periode selesai tidak boleh sebelum periode mulai.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'kategori_baru.nama' => 'nama kelompok pekerjaan',
            'kategori_baru.kode' => 'kode kelompok',
            'items' => 'daftar pekerjaan',
            'items.*.unit_id' => 'satuan pekerjaan',
            'items.*.uraian_pekerjaan' => 'uraian pekerjaan',
            'items.*.volume' => 'volume pekerjaan',
            'items.*.harga_satuan' => 'harga satuan',
            'items.*.period_mulai_id' => 'periode mulai',
            'items.*.period_selesai_id' => 'periode selesai',
        ];
    }
}
