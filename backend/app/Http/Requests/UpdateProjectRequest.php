<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesQsPenanggungJawab;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    use ValidatesQsPenanggungJawab;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_proyek' => ['sometimes', 'required', 'string', 'max:255'],
            'nomor_spk' => ['sometimes', 'required', 'string', 'max:120'],
            'lokasi' => ['sometimes', 'required', 'string', 'max:255'],
            'sumber_dana' => ['sometimes', 'required', 'string', 'max:150'],
            'tahun_anggaran' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_spk' => ['sometimes', 'required', 'date'],
            'tanggal_mulai' => ['sometimes', 'required', 'date'],
            'tanggal_selesai' => ['sometimes', 'required', 'date', 'after_or_equal:tanggal_mulai'],
            'jangka_waktu_hari' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
            'kontraktor_pelaksana' => ['sometimes', 'required', 'string', 'max:150'],
            'konsultan_pengawas' => ['sometimes', 'required', 'string', 'max:150'],
            'nama_site_engineer' => ['sometimes', 'required', 'string', 'max:120'],
            'nama_pelaksana_lapangan' => ['sometimes', 'required', 'string', 'max:120'],
            'qs_user_id' => ['sometimes', ...$this->qsRule($this->route('project')?->qs_user_id)],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_proyek' => 'nama proyek', 'lokasi' => 'lokasi proyek',
            'tanggal_mulai' => 'tanggal mulai', 'tanggal_selesai' => 'tanggal selesai',
            'qs_user_id' => 'QS penanggung jawab', 'kontraktor_pelaksana' => 'kontraktor pelaksana',
            'konsultan_pengawas' => 'konsultan pengawas', 'nama_site_engineer' => 'nama site engineer',
            'nomor_spk' => 'nomor SPK', 'sumber_dana' => 'sumber dana', 'tahun_anggaran' => 'tahun anggaran',
            'tanggal_spk' => 'tanggal SPK', 'jangka_waktu_hari' => 'jangka waktu',
            'nama_pelaksana_lapangan' => 'nama pelaksana lapangan',
        ];
    }
}
