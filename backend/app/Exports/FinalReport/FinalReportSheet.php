<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Dasar setiap sheet Laporan Akhir. Isi sheet ditulis langsung ke worksheet pada AfterSheet
 * agar tata letak (judul, identitas, tabel bertingkat, foto, grafik) dapat diatur penuh.
 *
 * Seluruh angka berasal dari ReportService::final(); sheet tidak menghitung ulang progres.
 */
abstract class FinalReportSheet implements WithEvents, WithTitle
{
    /** Angka persentase (nilai sudah dalam satuan %, header kolom memberi keterangan "(%)"). */
    protected const FORMAT_PERSEN = '#,##0.00';

    protected const FORMAT_VOLUME = '#,##0.000';

    protected const FORMAT_RUPIAH = '"Rp "#,##0.00';

    protected const WARNA_HEADER = 'FFD9E1F2';

    protected const WARNA_KATEGORI = 'FFF2F2F2';

    protected const WARNA_JUMLAH = 'FFE7E6E6';

    protected const WARNA_NEGATIF = 'FFC00000';

    public function __construct(protected readonly array $data) {}

    /** Tulis isi sheet; kembalikan baris terakhir yang terisi. */
    abstract protected function tulis(Worksheet $sheet): int;

    /** @return array{0:string,1:string} kolom terakhir & orientasi halaman */
    abstract protected function tataHalaman(): array;

    /** Baris header tabel yang diulang di setiap halaman cetak, `null` bila tidak ada. */
    protected function barisHeaderCetak(): ?array
    {
        return null;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
                $sheet->setShowGridlines(false);

                $barisAkhir = $this->tulis($sheet);
                [$kolomAkhir, $orientasi] = $this->tataHalaman();
                $this->aturCetak($sheet, $kolomAkhir, $barisAkhir, $orientasi);
            },
        ];
    }

    /**
     * Judul laporan + identitas singkat proyek. Mengembalikan baris kosong pertama setelahnya.
     */
    protected function kepala(Worksheet $sheet, string $subjudul, string $kolomAkhir): int
    {
        $h = $this->data['header'];

        $this->judul($sheet, 1, 'LAPORAN AKHIR PROYEK', $kolomAkhir, 14);
        $this->judul($sheet, 2, strtoupper($subjudul), $kolomAkhir, 12);
        $sheet->getStyle('A2:'.$kolomAkhir.'2')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        $identitas = [
            ['Paket Pekerjaan', $h['nama_proyek']],
            ['Lokasi', $h['lokasi']],
            ['Nomor SPK', trim(($h['nomor_spk'] ?? '-').($h['tanggal_spk'] ? ', tanggal '.ReportFormatter::tanggal($h['tanggal_spk']) : ''))],
            ['Tahun Anggaran / Sumber Dana', ($h['tahun_anggaran'] ?? '-').' / '.($h['sumber_dana'] ?? '-')],
        ];

        $baris = 4;
        foreach ($identitas as [$label, $nilai]) {
            $sheet->setCellValue('A'.$baris, $label);
            $sheet->setCellValue('C'.$baris, ': '.($nilai ?: '-'));
            $sheet->mergeCells('A'.$baris.':B'.$baris);
            $sheet->mergeCells('C'.$baris.':'.$kolomAkhir.$baris);
            $sheet->getStyle('A'.$baris)->getFont()->setBold(true);
            $baris++;
        }

        return $baris + 1;
    }

    protected function judul(Worksheet $sheet, int $baris, string $teks, string $kolomAkhir, int $ukuran = 12): void
    {
        $sheet->setCellValue('A'.$baris, $teks);
        $sheet->mergeCells('A'.$baris.':'.$kolomAkhir.$baris);
        $sheet->getStyle('A'.$baris)->getFont()->setBold(true)->setSize($ukuran);
        $sheet->getStyle('A'.$baris)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($baris)->setRowHeight($ukuran + 8);
    }

    protected function judulBagian(Worksheet $sheet, int $baris, string $nomor, string $teks): void
    {
        $sheet->setCellValue('A'.$baris, $nomor);
        $sheet->setCellValue('B'.$baris, $teks);
        $sheet->getStyle('A'.$baris.':B'.$baris)->getFont()->setBold(true)->setSize(11);
    }

    /** @param  array<string,float>  $lebar */
    protected function lebarKolom(Worksheet $sheet, array $lebar): void
    {
        foreach ($lebar as $kolom => $nilai) {
            $sheet->getColumnDimension($kolom)->setWidth($nilai);
        }
    }

    protected function gayaHeader(Worksheet $sheet, string $rentang): void
    {
        $style = $sheet->getStyle($rentang);
        $style->getFont()->setBold(true);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $this->isi($sheet, $rentang, self::WARNA_HEADER);
        $this->garis($sheet, $rentang);
    }

    protected function garis(Worksheet $sheet, string $rentang): void
    {
        $sheet->getStyle($rentang)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    protected function isi(Worksheet $sheet, string $rentang, string $warna): void
    {
        $sheet->getStyle($rentang)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($warna);
    }

    /** Sel tabel data: rata atas, teks panjang dibungkus. */
    protected function gayaData(Worksheet $sheet, string $rentang): void
    {
        $this->garis($sheet, $rentang);
        $sheet->getStyle($rentang)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    }

    protected function rataTengah(Worksheet $sheet, string $rentang): void
    {
        $sheet->getStyle($rentang)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function formatAngka(Worksheet $sheet, string $rentang, string $format): void
    {
        $sheet->getStyle($rentang)->getNumberFormat()->setFormatCode($format);
        $sheet->getStyle($rentang)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /** Deviasi negatif (terlambat) ditandai merah. */
    protected function tandaiNegatif(Worksheet $sheet, string $sel, ?float $nilai): void
    {
        if ($nilai !== null && $nilai < -0.005) {
            $sheet->getStyle($sel)->getFont()->getColor()->setARGB(self::WARNA_NEGATIF);
        }
    }

    /**
     * Tinggi baris menyesuaikan teks terpanjang karena Excel tidak menghitung ulang tinggi baris
     * yang dibuat oleh pustaka saat file dibuka.
     *
     * @param  array<string,?string>  $teks  kolom atau rentang kolom ("B:D") => isi teks
     */
    protected function tinggiBaris(Worksheet $sheet, int $baris, array $teks, float $minimum = 15.0): void
    {
        $jumlahBaris = 1;

        foreach ($teks as $kolom => $isi) {
            [$dari, $sampai] = str_contains($kolom, ':') ? explode(':', $kolom) : [$kolom, $kolom];
            $lebar = 0.0;

            for ($i = Coordinate::columnIndexFromString($dari); $i <= Coordinate::columnIndexFromString($sampai); $i++) {
                $lebar += $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->getWidth();
            }

            $hurufPerBaris = max((int) floor($lebar * 1.05), 1);
            $n = 0;

            foreach (explode("\n", (string) $isi) as $teksBaris) {
                $n += max(1, (int) ceil(mb_strlen($teksBaris) / $hurufPerBaris));
            }

            $jumlahBaris = max($jumlahBaris, $n);
        }

        $sheet->getRowDimension($baris)->setRowHeight(max($minimum, $jumlahBaris * 12.75 + 3));
    }

    /** Teks berpoin dari beberapa baris; `$kosong` dipakai bila daftar kosong. */
    protected function poin(array $baris, string $kosong = '-'): string
    {
        $baris = array_values(array_filter($baris, fn ($b) => trim((string) $b) !== ''));

        if ($baris === []) {
            return $kosong;
        }

        return count($baris) === 1 ? $baris[0] : implode("\n", array_map(fn ($b) => '• '.$b, $baris));
    }

    protected function volume(float $nilai): string
    {
        return number_format($nilai, 3, ',', '.');
    }

    protected function persen(float $nilai): string
    {
        return number_format($nilai, 2, ',', '.').'%';
    }

    /** Ringkas satu kendala menjadi satu baris teks. */
    protected function teksKendala(array $k): string
    {
        $teks = '['.($k['jenis_kendala'] ?: 'Kendala').'] ';
        $teks .= $k['pekerjaan'] ? $k['pekerjaan'].': ' : '';

        return $teks.($k['deskripsi'] ?: '-');
    }

    protected function labelStatusKendala(?string $status): string
    {
        return match ($status) {
            'SELESAI' => 'Selesai',
            'DALAM_PENANGANAN' => 'Dalam penanganan',
            default => 'Terbuka',
        };
    }

    private function aturCetak(Worksheet $sheet, string $kolomAkhir, int $barisAkhir, string $orientasi): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setOrientation($orientasi);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(0);
        $setup->setHorizontalCentered(true);
        $setup->setPrintArea('A1:'.$kolomAkhir.$barisAkhir);

        if ($header = $this->barisHeaderCetak()) {
            $setup->setRowsToRepeatAtTopByStartAndEnd($header[0], $header[1]);
        }

        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.5)->setRight(0.5)->setHeader(0.3)->setFooter(0.3);
        $sheet->getHeaderFooter()->setOddFooter(
            '&L&8Laporan Akhir — '.str_replace('&', '&&', (string) $this->data['header']['nama_proyek']).'&R&8'.$this->title().' · Halaman &P dari &N'
        );
        $sheet->setSelectedCell('A1');
    }
}
