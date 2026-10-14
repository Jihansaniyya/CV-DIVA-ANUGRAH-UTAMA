<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ReportStatus;
use App\Services\UploadLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan bersama form progres (tambah dan ubah draf).
 *
 * Kelengkapan bukti (lokasi, keterangan, foto) tidak divalidasi di sini karena draf boleh belum lengkap;
 * pemeriksaan itu dilakukan ProgressService setiap kali laporan berubah menjadi DIKIRIM.
 */
trait ProgressReportRules
{
    /** Field bersarang dikirim sebagai JSON string ketika memakai multipart/form-data. */
    protected function prepareForValidation(): void
    {
        foreach (['details', 'issues', 'photo_captions'] as $key) {
            $nilai = $this->input($key);

            if (is_string($nilai)) {
                $decoded = json_decode($nilai, true);

                if (is_array($decoded)) {
                    $this->merge([$key => $decoded]);
                }
            }
        }
    }

    /** @return array<string,mixed> */
    protected function aturanIsiLaporan(bool $detailWajib): array
    {
        $batas = app(UploadLimitService::class);

        return [
            'keterangan' => ['nullable', 'string'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'cuaca' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(ReportStatus::class)],

            'details' => [$detailWajib ? 'required' : 'sometimes', 'array', 'min:1'],
            'details.*.work_item_id' => ['required', 'integer', 'distinct', 'exists:work_items,id'],
            'details.*.volume_realisasi' => ['required', 'numeric', 'min:0'],
            'details.*.keterangan' => ['nullable', 'string'],

            'issues' => ['nullable', 'array'],
            'issues.*.work_item_id' => ['nullable', 'integer', 'exists:work_items,id'],
            'issues.*.jenis_kendala' => ['nullable', 'in:CUACA,MATERIAL,TENAGA_KERJA,PERALATAN,TEKNIS,LAINNYA'],
            'issues.*.deskripsi' => ['required', 'string'],
            'issues.*.alasan_keterlambatan' => ['nullable', 'string'],
            'issues.*.tindak_lanjut' => ['nullable', 'string'],
            'issues.*.status' => ['nullable', 'in:TERBUKA,DALAM_PENANGANAN,SELESAI'],

            'photos' => ['nullable', 'array', 'max:'.$batas->fotoMaksJumlah()],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'max:'.$batas->fotoMaksKb()],
            'photo_captions' => ['nullable', 'array'],
            'photo_captions.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Total ukuran foto dalam satu permintaan tidak boleh melewati batas server. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $totalMaks = app(UploadLimitService::class)->totalMaksKb();
                $foto = array_filter((array) $this->file('photos', []), fn ($f) => $f instanceof UploadedFile);

                if ($totalMaks <= 0 || $foto === []) {
                    return;
                }

                $totalKb = array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $foto)) / 1024;

                if ($totalKb > $totalMaks) {
                    $validator->errors()->add(
                        'photos',
                        'Total ukuran foto melebihi batas '.UploadLimitService::label($totalMaks).' per pengiriman. Kurangi jumlah atau ukuran foto.'
                    );
                }
            },
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
            'photos' => 'foto dokumentasi',
            'photos.*' => 'foto dokumentasi',
            'issues.*.deskripsi' => 'deskripsi kendala',
            'client_request_id' => 'identitas permintaan',
        ];
    }

    public function messages(): array
    {
        $batas = app(UploadLimitService::class);
        $ukuran = UploadLimitService::label($batas->fotoMaksKb());

        return [
            'details.required' => 'Laporan harus memuat minimal satu pekerjaan.',
            'details.*.work_item_id.distinct' => 'Pekerjaan yang sama tidak boleh diinput dua kali dalam satu progres.',
            'photos.max' => 'Maksimal '.$batas->fotoMaksJumlah().' foto per progres.',
            'photos.*.mimes' => 'Foto harus berformat JPG, JPEG, atau PNG.',
            'photos.*.image' => 'Foto harus berformat JPG, JPEG, atau PNG.',
            'photos.*.max' => 'Ukuran foto maksimal '.$ukuran.'.',
            // Berkas yang melebihi upload_max_filesize dibuang PHP sehingga hanya tercatat "gagal diunggah".
            'photos.*.uploaded' => 'Foto gagal diunggah. Pastikan ukuran setiap foto tidak melebihi '.$ukuran.'.',
            'photos.*.file' => 'Foto gagal diunggah. Pastikan ukuran setiap foto tidak melebihi '.$ukuran.'.',
        ];
    }
}
