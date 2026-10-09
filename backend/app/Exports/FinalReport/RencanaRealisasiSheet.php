<?php

namespace App\Exports\FinalReport;

use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 4 — rencana vs realisasi per item pekerjaan sampai periode laporan terakhir.
 *   rencana  = SUM(work_plans) s/d periode terakhir
 *   realisasi = SUM(progress_details) laporan DIKIRIM, bobot = volume / volume kontrak x bobot item
 */
class RencanaRealisasiSheet extends FinalReportSheet
{
    private int $header = 0;

    public function title(): string
    {
        return 'Rencana & Realisasi';
    }

    protected function tataHalaman(): array
    {
        return ['O', PageSetup::ORIENTATION_LANDSCAPE];
    }

    protected function barisHeaderCetak(): ?array
    {
        return [$this->header, $this->header + 1];
    }

    protected function tulis(Worksheet $sheet): int
    {
        $l = $this->data['laporan_terakhir'];
        $sampai = 's/d Minggu '.$l['minggu_ke_romawi'].' ('.$l['nama_periode'].')';

        $this->lebarKolom($sheet, [
            'A' => 5, 'B' => 40, 'C' => 8, 'D' => 12, 'E' => 9, 'F' => 12, 'G' => 11, 'H' => 10,
            'I' => 12, 'J' => 11, 'K' => 10, 'L' => 12, 'M' => 12, 'N' => 10, 'O' => 13,
        ]);

        $h1 = $this->header = $this->kepala($sheet, 'Rencana dan Realisasi Pekerjaan', 'O');
        $h2 = $h1 + 1;

        $sheet->fromArray([
            ['NO.', 'URAIAN PEKERJAAN', 'SATUAN', 'VOLUME KONTRAK', 'BOBOT (%)', 'RENCANA '.strtoupper($sampai), null, null, 'REALISASI '.strtoupper($sampai), null, null, 'SISA VOLUME', 'SELISIH (REALISASI - RENCANA)', null, 'STATUS'],
            [null, null, null, null, null, 'Target Volume', 'Progres Volume (%)', 'Bobot (%)', 'Volume', 'Realisasi Volume (%)', 'Bobot (%)', null, 'Volume', 'Bobot (%)', null],
        ], null, 'A'.$h1);

        foreach (['A', 'B', 'C', 'D', 'E', 'L', 'O'] as $kolom) {
            $sheet->mergeCells($kolom.$h1.':'.$kolom.$h2);
        }
        $sheet->mergeCells('F'.$h1.':H'.$h1);
        $sheet->mergeCells('I'.$h1.':K'.$h1);
        $sheet->mergeCells('M'.$h1.':N'.$h1);
        $this->gayaHeader($sheet, 'A'.$h1.':O'.$h2);
        $sheet->getRowDimension($h1)->setRowHeight(30);
        $sheet->getRowDimension($h2)->setRowHeight(28);

        $baris = $h2 + 1;
        $mulai = $baris;
        $barisTebal = [];

        foreach ($this->data['kategori'] as $kategori) {
            $sheet->setCellValue('A'.$baris, $kategori['kode']);
            $sheet->setCellValue('B'.$baris, $kategori['nama']);
            $sheet->mergeCells('B'.$baris.':O'.$baris);
            $this->isi($sheet, 'A'.$baris.':O'.$baris, self::WARNA_KATEGORI);
            $barisTebal[] = $baris++;

            foreach ($kategori['items'] as $item) {
                $volSd = $item['realisasi_sd_bulan_ini']['volume'];

                $sheet->fromArray([[
                    $item['no'],
                    $item['uraian'],
                    $item['satuan'],
                    $item['volume'],
                    $item['bobot'],
                    $item['rencana_sd']['volume'],
                    $item['persen_rencana'],
                    $item['rencana_sd']['bobot'],
                    $volSd,
                    $item['keterangan_persen'],
                    $item['realisasi_sd_bulan_ini']['bobot'],
                    $item['sisa_volume'],
                    $item['selisih_volume'],
                    $item['selisih_bobot'],
                    $item['selesai'] ? 'Selesai' : ($volSd > 0 ? 'Berjalan' : 'Belum dikerjakan'),
                ]], null, 'A'.$baris, true);

                $this->tandaiNegatif($sheet, 'M'.$baris, $item['selisih_volume']);
                $this->tandaiNegatif($sheet, 'N'.$baris, $item['selisih_bobot']);
                $this->tinggiBaris($sheet, $baris, ['B' => $item['uraian']]);
                $baris++;
            }

            $s = $kategori['subtotal'];
            $this->barisJumlah($sheet, $baris, 'JUMLAH '.$kategori['nama'], $s);
            $barisTebal[] = $baris++;
        }

        $this->barisJumlah($sheet, $baris, 'JUMLAH TOTAL', $this->data['total']);
        $barisTebal[] = $baris;

        $this->gayaData($sheet, 'A'.$mulai.':O'.$baris);
        $this->rataTengah($sheet, 'A'.$mulai.':A'.$baris);
        $this->rataTengah($sheet, 'C'.$mulai.':C'.$baris);
        $this->rataTengah($sheet, 'O'.$mulai.':O'.$baris);
        foreach (['D', 'F', 'I', 'L', 'M'] as $kolom) {
            $this->formatAngka($sheet, $kolom.$mulai.':'.$kolom.$baris, self::FORMAT_VOLUME);
        }
        foreach (['E', 'G', 'H', 'J', 'K', 'N'] as $kolom) {
            $this->formatAngka($sheet, $kolom.$mulai.':'.$kolom.$baris, self::FORMAT_PERSEN);
        }
        foreach ($barisTebal as $tebal) {
            $sheet->getStyle('A'.$tebal.':O'.$tebal)->getFont()->setBold(true);
        }

        return $baris;
    }

    private function barisJumlah(Worksheet $sheet, int $baris, string $label, array $jumlah): void
    {
        $selisih = round($jumlah['realisasi_sd_bulan_ini']['bobot'] - $jumlah['rencana_sd']['bobot'], 4);

        $sheet->setCellValue('B'.$baris, $label);
        $sheet->mergeCells('B'.$baris.':D'.$baris);
        $sheet->setCellValue('E'.$baris, $jumlah['bobot']);
        $sheet->setCellValue('H'.$baris, $jumlah['rencana_sd']['bobot']);
        $sheet->setCellValue('K'.$baris, $jumlah['realisasi_sd_bulan_ini']['bobot']);
        $sheet->setCellValue('N'.$baris, $selisih);
        $this->tandaiNegatif($sheet, 'N'.$baris, $selisih);
        $this->isi($sheet, 'A'.$baris.':O'.$baris, self::WARNA_JUMLAH);
    }
}
