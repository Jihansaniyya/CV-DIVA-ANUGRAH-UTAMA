<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use App\Services\ImageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 6 — foto dokumentasi dari laporan progres terkirim, disisipkan langsung ke sel.
 *
 * Foto diperkecil (sisi terpanjang maksimal 1000 px, lewat ImageService) ke berkas sementara sebelum disisipkan agar
 * ukuran file Excel tetap wajar; berkas sementara dihapus setelah export selesai.
 */
class DokumentasiSheet extends FinalReportSheet
{
    /** Kotak foto di dalam sel (piksel). */
    private const LEBAR_FOTO = 330;

    private const TINGGI_FOTO = 235;

    private const SISI_MAKSIMUM = 1000;

    private int $header = 0;

    /** @var list<string> */
    private array $berkasSementara = [];

    public function __destruct()
    {
        foreach ($this->berkasSementara as $berkas) {
            @unlink($berkas);
        }
    }

    public function title(): string
    {
        return 'Dokumentasi';
    }

    protected function tataHalaman(): array
    {
        return ['C', PageSetup::ORIENTATION_PORTRAIT];
    }

    protected function barisHeaderCetak(): ?array
    {
        return [$this->header, $this->header];
    }

    protected function tulis(Worksheet $sheet): int
    {
        // Lebar kolom B ~ 50 karakter = ±355 px, cukup untuk kotak foto beserta jarak tepi.
        $this->lebarKolom($sheet, ['A' => 5, 'B' => 50, 'C' => 46]);

        $header = $this->header = $this->kepala($sheet, 'Dokumentasi Proyek', 'C');
        $sheet->fromArray([['NO.', 'FOTO', 'KETERANGAN']], null, 'A'.$header);
        $this->gayaHeader($sheet, 'A'.$header.':C'.$header);

        $foto = $this->data['dokumentasi'];
        $baris = $header + 1;

        if ($foto === []) {
            $sheet->setCellValue('A'.$baris, 'Belum ada foto dokumentasi pada laporan progres yang dikirim.');
            $sheet->mergeCells('A'.$baris.':C'.$baris);
            $this->gayaData($sheet, 'A'.$baris.':C'.$baris);
            $this->rataTengah($sheet, 'A'.$baris);
            $sheet->getRowDimension($baris)->setRowHeight(30);

            return $baris;
        }

        $mulai = $baris;

        foreach ($foto as $i => $f) {
            $sheet->setCellValue('A'.$baris, $i + 1);
            $sheet->setCellValue('C'.$baris, $this->keterangan($f));
            $sheet->getRowDimension($baris)->setRowHeight((self::TINGGI_FOTO + 16) * 0.75);

            if (! $this->sisipkanFoto($sheet, 'B'.$baris, $f['file_path'])) {
                $sheet->setCellValue('B'.$baris, 'Berkas foto tidak ditemukan pada penyimpanan aplikasi.');
                $sheet->getStyle('B'.$baris)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $baris++;
        }

        $this->gayaData($sheet, 'A'.$mulai.':C'.($baris - 1));
        $this->rataTengah($sheet, 'A'.$mulai.':A'.($baris - 1));

        return $baris - 1;
    }

    private function keterangan(array $f): string
    {
        $waktu = $f['diunggah_pada']
            ? Carbon::parse($f['diunggah_pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i')
            : null;

        $baris = [
            'Tanggal laporan' => ReportFormatter::tanggal($f['tanggal_laporan']),
            'Waktu unggah' => $waktu,
            'Periode' => $f['nama_periode'],
            'Lokasi' => $f['lokasi'],
            'Pekerjaan' => $f['uraian_pekerjaan'],
            'Keterangan foto' => $f['caption'],
            'Catatan laporan' => $f['keterangan'],
        ];

        return collect($baris)
            ->map(fn ($nilai, $label) => $label.': '.(trim((string) $nilai) !== '' ? $nilai : '-'))
            ->implode("\n");
    }

    private function sisipkanFoto(Worksheet $sheet, string $sel, string $relatif): bool
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($relatif)) {
            return false;
        }

        $sumber = $this->perkecil($disk->path($relatif));
        $ukuran = $sumber ? @getimagesize($sumber) : false;

        if (! $ukuran || $ukuran[0] <= 0 || $ukuran[1] <= 0) {
            return false;
        }

        $skala = min(self::LEBAR_FOTO / $ukuran[0], self::TINGGI_FOTO / $ukuran[1]);
        $lebar = (int) round($ukuran[0] * $skala);
        $tinggi = (int) round($ukuran[1] * $skala);

        $drawing = new Drawing;
        $drawing->setPath($sumber);
        $drawing->setResizeProportional(false);
        $drawing->setWidth($lebar);
        $drawing->setHeight($tinggi);
        $drawing->setCoordinates($sel);
        $drawing->setOffsetX((int) max((self::LEBAR_FOTO + 20 - $lebar) / 2, 4));
        $drawing->setOffsetY((int) max((self::TINGGI_FOTO + 20 - $tinggi) / 2, 4));
        $drawing->setWorksheet($sheet);

        return true;
    }

    /** Salinan foto yang diperkecil; foto kecil atau tanpa dukungan GD dipakai apa adanya. */
    private function perkecil(string $path): ?string
    {
        if (! @getimagesize($path)) {
            return null;
        }

        $dasar = (string) tempnam(sys_get_temp_dir(), 'foto_');
        $tujuan = $dasar.'.jpg';
        array_push($this->berkasSementara, $dasar, $tujuan);

        return app(ImageService::class)->perkecil($path, $tujuan, self::SISI_MAKSIMUM) ? $tujuan : $path;
    }
}
