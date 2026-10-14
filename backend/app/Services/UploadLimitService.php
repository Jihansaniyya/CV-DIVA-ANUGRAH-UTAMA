<?php

namespace App\Services;

/**
 * Batas unggah foto progres yang benar-benar dapat diterima server.
 *
 * Batas aplikasi (config/progres.php) tidak boleh melebihi konfigurasi PHP: berkas yang lebih besar dari
 * `upload_max_filesize` dibuang PHP sebelum Laravel memprosesnya, dan permintaan yang lebih besar dari
 * `post_max_size` ditolak seluruhnya. Frontend memakai angka yang sama melalui endpoint batas unggah.
 */
class UploadLimitService
{
    /** Cadangan untuk data form dan pembungkus multipart di luar isi berkas. */
    public const CADANGAN_MULTIPART_KB = 256;

    /** Ukuran maksimal satu foto (KB). */
    public function fotoMaksKb(): int
    {
        $aplikasi = (int) config('progres.foto_maks_kb', 5120);
        $php = $this->iniKeKb((string) ini_get('upload_max_filesize'));

        return $php > 0 ? min($aplikasi, $php) : $aplikasi;
    }

    public function fotoMaksJumlah(): int
    {
        $aplikasi = (int) config('progres.foto_maks_jumlah', 10);
        $php = (int) ini_get('max_file_uploads');

        return $php > 0 ? min($aplikasi, $php) : $aplikasi;
    }

    /** Total ukuran foto dalam satu pengiriman (KB), setelah dikurangi cadangan multipart. 0 = tanpa batas. */
    public function totalMaksKb(): int
    {
        $post = $this->iniKeKb((string) ini_get('post_max_size'));

        return $post > 0 ? max($post - self::CADANGAN_MULTIPART_KB, 0) : 0;
    }

    /** @return array{foto_maks_kb:int,foto_maks_jumlah:int,total_maks_kb:int} */
    public function ringkasan(): array
    {
        return [
            'foto_maks_kb' => $this->fotoMaksKb(),
            'foto_maks_jumlah' => $this->fotoMaksJumlah(),
            'total_maks_kb' => $this->totalMaksKb(),
        ];
    }

    /** "2M" -> 2048, "512K" -> 512, "1G" -> 1048576, "0" -> 0 (tanpa batas). */
    public function iniKeKb(string $nilai): int
    {
        $nilai = trim($nilai);

        if ($nilai === '' || $nilai === '0' || $nilai === '-1') {
            return 0;
        }

        $angka = (float) $nilai;

        return (int) floor(match (strtoupper(substr($nilai, -1))) {
            'G' => $angka * 1024 * 1024,
            'M' => $angka * 1024,
            'K' => $angka,
            default => $angka / 1024,
        });
    }

    /** Contoh: 2048 -> "2 MB", 512 -> "512 KB". */
    public static function label(int $kb): string
    {
        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1, ',', '.'), '0'), ',').' MB'
            : $kb.' KB';
    }
}
