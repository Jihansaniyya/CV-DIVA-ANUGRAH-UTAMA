<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 3 — rekap laporan bulanan. Progres bulanan = jumlah minggu di dalam bulan tersebut saja,
 * sehingga tidak ada minggu yang terhitung pada dua bulan.
 */
class RekapBulananSheet extends FinalReportSheet
{
    private int $header = 0;

    public function title(): string
    {
        return 'Rekap Bulanan';
    }

    protected function tataHalaman(): array
    {
        return ['L', PageSetup::ORIENTATION_LANDSCAPE];
    }

    protected function barisHeaderCetak(): ?array
    {
        return [$this->header, $this->header + 1];
    }

    protected function tulis(Worksheet $sheet): int
    {
        $this->lebarKolom($sheet, [
            'A' => 5, 'B' => 9, 'C' => 26, 'D' => 9, 'E' => 10, 'F' => 10,
            'G' => 11, 'H' => 11, 'I' => 10, 'J' => 46, 'K' => 40, 'L' => 36,
        ]);

        $h1 = $this->header = $this->kepala($sheet, 'Rekap Laporan Bulanan', 'L');
        $h2 = $h1 + 1;

        $sheet->fromArray([
            ['NO.', 'BULAN KE', 'PERIODE', 'JUMLAH MINGGU', 'PROGRES BULANAN (%)', null, 'PROGRES KUMULATIF (%)', null, 'DEVIASI (%)', 'RINGKASAN PEKERJAAN YANG DILAKSANAKAN', 'CATATAN KENDALA', 'TINDAK LANJUT'],
            [null, null, null, null, 'Rencana', 'Realisasi', 'Rencana', 'Realisasi', null, null, null, null],
        ], null, 'A'.$h1);

        foreach (['A', 'B', 'C', 'D', 'I', 'J', 'K', 'L'] as $kolom) {
            $sheet->mergeCells($kolom.$h1.':'.$kolom.$h2);
        }
        $sheet->mergeCells('E'.$h1.':F'.$h1);
        $sheet->mergeCells('G'.$h1.':H'.$h1);
        $this->gayaHeader($sheet, 'A'.$h1.':L'.$h2);
        $sheet->getRowDimension($h1)->setRowHeight(28);

        $baris = $h2 + 1;
        $mulai = $baris;

        foreach ($this->data['rekap_bulanan'] as $i => $b) {
            $pekerjaan = $b['jumlah_laporan'] === 0
                ? 'Tidak ada laporan progres pada bulan ini.'
                : $this->poin(
                    array_map(fn ($p) => $p['uraian'].': '.$this->volume($p['volume']).' '.($p['satuan'] ?? '').' (bobot '.$this->persen($p['bobot']).')', $b['pekerjaan']),
                    'Tidak ada volume pekerjaan yang dilaporkan.',
                );

            $nomorKendala = array_map(fn ($k, $n) => ($n + 1).'. '.$this->teksKendala($k), $b['kendala'], array_keys($b['kendala']));
            $nomorTindakLanjut = array_map(
                fn ($k, $n) => ($n + 1).'. '.($k['tindak_lanjut'] ?: '-').' ('.$this->labelStatusKendala($k['status']).')',
                $b['kendala'],
                array_keys($b['kendala']),
            );
            $kendala = $nomorKendala === [] ? '-' : implode("\n", $nomorKendala);
            $tindakLanjut = $nomorTindakLanjut === [] ? '-' : implode("\n", $nomorTindakLanjut);

            $sheet->fromArray([[
                $i + 1,
                $b['bulan_ke_romawi'],
                ReportFormatter::rentangTanggal($b['tanggal_mulai'], $b['tanggal_selesai']),
                $b['jumlah_minggu'],
                $b['rencana'],
                $b['realisasi'],
                $b['rencana_kumulatif'],
                $b['realisasi_kumulatif'],
                $b['deviasi'],
                $pekerjaan,
                $kendala,
                $tindakLanjut,
            ]], null, 'A'.$baris, true);

            $this->tandaiNegatif($sheet, 'I'.$baris, $b['deviasi']);
            $this->tinggiBaris($sheet, $baris, ['C' => ReportFormatter::rentangTanggal($b['tanggal_mulai'], $b['tanggal_selesai']), 'J' => $pekerjaan, 'K' => $kendala, 'L' => $tindakLanjut]);
            $baris++;
        }

        $bulanan = collect($this->data['rekap_bulanan']);

        $sheet->setCellValue('A'.$baris, 'JUMLAH');
        $sheet->mergeCells('A'.$baris.':D'.$baris);
        $sheet->setCellValue('E'.$baris, round((float) $bulanan->sum('rencana'), 4));
        $sheet->setCellValue('F'.$baris, round((float) $bulanan->sum(fn ($b) => $b['realisasi'] ?? 0), 4));
        $sheet->setCellValue('G'.$baris, $this->data['ringkasan']['rencana_kumulatif']);
        $sheet->setCellValue('H'.$baris, $this->data['ringkasan']['realisasi_kumulatif']);
        $sheet->setCellValue('I'.$baris, $this->data['ringkasan']['deviasi']);
        $this->tandaiNegatif($sheet, 'I'.$baris, $this->data['ringkasan']['deviasi']);
        $sheet->getStyle('A'.$baris.':L'.$baris)->getFont()->setBold(true);
        $this->isi($sheet, 'A'.$baris.':L'.$baris, self::WARNA_JUMLAH);

        $this->gayaData($sheet, 'A'.$mulai.':L'.$baris);
        $this->rataTengah($sheet, 'A'.$mulai.':D'.$baris);
        $this->formatAngka($sheet, 'E'.$mulai.':I'.$baris, self::FORMAT_PERSEN);

        return $baris;
    }
}
