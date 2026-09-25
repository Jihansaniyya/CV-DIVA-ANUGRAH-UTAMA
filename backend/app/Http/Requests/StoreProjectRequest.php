<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'nama_proyek' => ['required', 'string', 'max:255'],
            'nomor_spk' => ['nullable', 'string', 'max:120'],
            'nomor_pekerjaan' => ['nullable', 'string', 'max:120'],
            'nomor_proyek' => ['nullable', 'string', 'max:120'],
            'lokasi' => ['required', 'string', 'max:255'],
            'sumber_dana' => ['nullable', 'string', 'max:150'],
            'tahun_anggaran' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tanggal_spk' => ['nullable', 'date'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'jangka_waktu_hari' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'kontraktor_pelaksana' => ['nullable', 'string', 'max:150'],
            'konsultan_pengawas' => ['nullable', 'string', 'max:150'],
            'nama_site_engineer' => ['nullable', 'string', 'max:120'],
            'nama_pelaksana_lapangan' => ['nullable', 'string', 'max:120'],
            'qs_user_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_proyek' => 'nama proyek', 'lokasi' => 'lokasi proyek',
            'tanggal_mulai' => 'tanggal mulai', 'tanggal_selesai' => 'tanggal selesai',
            'qs_user_id' => 'QS penanggung jawab',
        ];
    }
}
