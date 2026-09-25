<?php

namespace App\Http\Requests;

use App\Enums\ReportStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || (! $user->isQs() && ! $user->isAdmin())) {
            return false;
        }

        $project = Project::find($this->input('project_id', $this->route('progress')?->project_id));

        return $project !== null && $project->isAccessibleBy($user);
    }

    /** Field bersarang dikirim sebagai JSON string ketika memakai multipart/form-data. */
    protected function prepareForValidation(): void
    {
        foreach (['details', 'materials', 'issues', 'photo_captions'] as $key) {
            $nilai = $this->input($key);

            if (is_string($nilai)) {
                $decoded = json_decode($nilai, true);

                if (is_array($decoded)) {
                    $this->merge([$key => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'exists:projects,id'],
            'tanggal_laporan' => ['sometimes', 'required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'cuaca' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(ReportStatus::class)],

            'details' => ['sometimes', 'array', 'min:1'],
            'details.*.work_item_id' => ['required', 'integer', 'exists:work_items,id'],
            'details.*.volume_realisasi' => ['required', 'numeric', 'min:0'],
            'details.*.keterangan' => ['nullable', 'string'],

            'materials' => ['nullable', 'array'],
            'materials.*.nama_material' => ['required', 'string', 'max:150'],
            'materials.*.jumlah' => ['required', 'numeric', 'min:0'],
            'materials.*.unit_id' => ['nullable', 'exists:units,id'],
            'materials.*.satuan' => ['nullable', 'string', 'max:30'],
            'materials.*.keterangan' => ['nullable', 'string'],

            'issues' => ['nullable', 'array'],
            'issues.*.work_item_id' => ['nullable', 'integer', 'exists:work_items,id'],
            'issues.*.jenis_kendala' => ['nullable', 'in:CUACA,MATERIAL,TENAGA_KERJA,PERALATAN,TEKNIS,LAINNYA'],
            'issues.*.deskripsi' => ['required', 'string'],
            'issues.*.alasan_keterlambatan' => ['nullable', 'string'],
            'issues.*.tindak_lanjut' => ['nullable', 'string'],
            'issues.*.status' => ['nullable', 'in:TERBUKA,DALAM_PENANGANAN,SELESAI'],

            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'photo_captions' => ['nullable', 'array'],
            'photo_captions.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'project_id' => 'proyek',
            'tanggal_laporan' => 'tanggal laporan',
            'details' => 'detail pekerjaan',
            'details.*.work_item_id' => 'pekerjaan',
            'details.*.volume_realisasi' => 'volume realisasi',
            'photos.*' => 'foto dokumentasi',
            'issues.*.deskripsi' => 'deskripsi kendala',
            'materials.*.nama_material' => 'nama material',
        ];
    }

    public function messages(): array
    {
        return [
            'details.required' => 'Laporan harus memuat minimal satu pekerjaan.',
            'photos.*.mimes' => 'Foto harus berformat JPG, JPEG, atau PNG.',
            'photos.*.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
