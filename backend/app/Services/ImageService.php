<?php

namespace App\Services;

/**
 * Memperkecil foto dokumentasi menjadi JPEG dengan sisi terpanjang terbatas.
 *
 * Foto lapangan dari ponsel bisa mencapai beberapa MB; untuk dokumentasi progres resolusi
 * 1600 px sudah cukup jelas saat dilihat maupun dicetak, dengan ukuran ±200-400 KB.
 */
class ImageService
{
    public const SISI_FOTO = 1600;

    public const KUALITAS = 82;

    /**
     * Tulis versi kecil dari $sumber ke $tujuan (JPEG). Mengembalikan false bila GD tidak tersedia,
     * berkas bukan gambar, atau gambar sudah cukup kecil sehingga tidak perlu diproses.
     */
    public function perkecil(string $sumber, string $tujuan, int $sisiMaksimum = self::SISI_FOTO, int $kualitas = self::KUALITAS): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $info = @getimagesize($sumber);

        if (! $info || $info[0] <= 0 || $info[1] <= 0) {
            return false;
        }

        [$lebar, $tinggi] = $info;
        $sudahJpeg = $info[2] === IMAGETYPE_JPEG;

        // JPEG kecil dibiarkan; PNG selalu dikonversi karena foto PNG jauh lebih besar dari JPEG.
        if ($sudahJpeg && max($lebar, $tinggi) <= $sisiMaksimum && filesize($sumber) <= 600 * 1024 && ! $this->perluDiputar($sumber)) {
            return false;
        }

        $gambar = @imagecreatefromstring((string) file_get_contents($sumber));

        if ($gambar === false) {
            return false;
        }

        $gambar = $this->tegakkan($gambar, $sumber, $info[2]);
        [$lebar, $tinggi] = [imagesx($gambar), imagesy($gambar)];

        $skala = min(1, $sisiMaksimum / max($lebar, $tinggi));
        $lebarBaru = max(1, (int) round($lebar * $skala));
        $tinggiBaru = max(1, (int) round($tinggi * $skala));

        // Latar putih agar bagian transparan PNG tidak menjadi hitam saat disimpan sebagai JPEG.
        $kanvas = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagefill($kanvas, 0, 0, (int) imagecolorallocate($kanvas, 255, 255, 255));
        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        imagedestroy($gambar);

        $berhasil = imagejpeg($kanvas, $tujuan, $kualitas);
        imagedestroy($kanvas);

        return $berhasil;
    }

    private function perluDiputar(string $sumber): bool
    {
        return in_array($this->orientasi($sumber), [3, 6, 8], true);
    }

    /**
     * Nilai tag Orientation (0x0112) dari EXIF JPEG. Dibaca langsung dari header berkas agar tetap
     * berfungsi walau ekstensi PHP exif tidak aktif.
     */
    private function orientasi(string $sumber): int
    {
        $data = (string) @file_get_contents($sumber, false, null, 0, 128 * 1024);

        if (! str_starts_with($data, "\xFF\xD8")) {
            return 1;
        }

        $posisi = 2;
        $panjangData = strlen($data);

        while ($posisi + 4 <= $panjangData && $data[$posisi] === "\xFF") {
            $penanda = ord($data[$posisi + 1]);
            $panjang = unpack('n', substr($data, $posisi + 2, 2))[1];

            if ($penanda === 0xE1 && substr($data, $posisi + 4, 6) === "Exif\0\0") {
                $tiff = $posisi + 10;
                $le = substr($data, $tiff, 2) === 'II';
                $u16 = fn (int $o) => unpack($le ? 'v' : 'n', substr($data, $o, 2))[1] ?? 0;
                $u32 = fn (int $o) => unpack($le ? 'V' : 'N', substr($data, $o, 4))[1] ?? 0;
                $ifd = $tiff + $u32($tiff + 4);
                $jumlah = $u16($ifd);

                for ($i = 0; $i < $jumlah; $i++) {
                    $entri = $ifd + 2 + $i * 12;

                    if ($entri + 12 > $panjangData) {
                        break;
                    }

                    if ($u16($entri) === 0x0112) {
                        return (int) $u16($entri + 8);
                    }
                }

                return 1;
            }

            if ($penanda === 0xDA) {
                break;
            }

            $posisi += 2 + $panjang;
        }

        return 1;
    }

    /** Putar foto sesuai orientasi EXIF kamera; GD tidak membawa data EXIF ke hasil. */
    private function tegakkan(\GdImage $gambar, string $sumber, int $tipe): \GdImage
    {
        if ($tipe !== IMAGETYPE_JPEG) {
            return $gambar;
        }

        $sudut = match ($this->orientasi($sumber)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($sudut === 0) {
            return $gambar;
        }

        $diputar = imagerotate($gambar, $sudut, 0);

        if ($diputar === false) {
            return $gambar;
        }

        imagedestroy($gambar);

        return $diputar;
    }
}
