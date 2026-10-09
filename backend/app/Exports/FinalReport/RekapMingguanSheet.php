<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 2 — rekap laporan mingguan. Angka progres = titik Kurva S per minggu (sumber yang sama dengan
 * laporan mingguan); uraian & kendala = isi laporan progres terkirim pada minggu tersebut.
 */
class RekapMingguanSheet extends FinalReportSheet
{
    private int $header = 0;

    public function title(): string
    {
        return 'Rekap Mingguan';
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
            'A' => 5, 'B' => 9, 'C' => 12, 'D' => 15, 'E' => 15, 'F' => 10, 'G' => 10,
            'H' => 11, 'I' => 11, 'J' => 10, 'K' => 50, 'L' => 44,
        ]);

        $h1 = $this->header = $this->kepala($sheet, 'Rekap Laporan Mingguan', 'L');
        $h2 = $h1 + 1;

        $sheet->fromArray([
            ['NO.', 'MINGGU KE', 'PERIODE', 'TANGGAL', null, 'PROGRES MINGGUAN (%)', null, 'PROGRES KUMULATIF (%)', null, 'DEVIASI (%)', 'URAIAN PEKERJAAN YANG DILAKSANAKAN', 'KENDALA / HAMBATAN & TINDAK LANJUT'],
            [null, null, null, 'Awal', 'Akhir', 'Rencana', 'Realisasi', 'Rencana', 'Realisasi', null, null, null],
        ], null, 'A'.$h1);

        foreach (['A', 'B', 'C', 'J', 'K', 'L'] as $kolom) {
            $sheet->mergeCells($kolom.$h1.':'.$kolom.$h2);
        }
        foreach (['D:E', 'F:G', 'H:I'] as $pasangan) {
            [$dari, $sampai] = explode(':', $pasangan);
            $sheet->mergeCells($dari.$h1.':'.$sampai.$h1);
        }
        $this->gayaHeader($sheet, 'A'.$h1.':L'.$h2);
        $sheet->getRowDimension($h1)->setRowHeight(28);

        $baris = $h2 + 1;
        $mulai = $baris;

        foreach ($this->data['rekap_mingguan'] as $i => $m) {
            $uraian = $m['jumlah_laporan'] === 0
                ? 'Tidak ada laporan progres pada minggu ini.'
                : $this->poin(array_merge(
                    array_map(fn ($p) => $p['uraian'].': '.$this->volume($p['volume']).' '.($p['satuan'] ?? ''), $m['pekerjaan']),
                    array_map(fn ($c) => 'Catatan: '.$c, $m['catatan']),
                ), 'Tidak ada volume pekerjaan yang dilaporkan.');

            $kendala = $this->poin(array_map(
                fn ($k) => $this->teksKendala($k).($k['tindak_lanjut'] ? ' — Tindak lanjut: '.$k['tindak_lanjut'] : '').' ('.$this->labelStatusKendala($k['status']).')',
                $m['kendala'],
            ));

            $sheet->fromArray([[
                $i + 1,
                $m['minggu_ke_romawi'],
                $m['nama_periode'],
                ReportFormatter::tanggal($m['tanggal_mulai']),
                ReportFormatter::tanggal($m['tanggal_selesai']),
                $m['rencana'],
                $m['aktual'],
                $m['rencana_kumulatif'],
                $m['aktual_kumulatif'],
                $m['deviasi'],
                $uraian,
                $kendala,
            ]], null, 'A'.$baris, true);

            $this->tandaiNegatif($sheet, 'J'.$baris, $m['deviasi']);
            $this->tinggiBaris($sheet, $baris, ['K' => $uraian, 'L' => $kendala]);
            $baris++;
        }

        $mingguan = collect($this->data['rekap_mingguan']);

        $sheet->setCellValue('A'.$baris, 'JUMLAH');
        $sheet->mergeCells('A'.$baris.':E'.$baris);
        $sheet->setCellValue('F'.$baris, round((float) $mingguan->sum('rencana'), 4));
        $sheet->setCellValue('G'.$baris, round((float) $mingguan->sum(fn ($m) => $m['aktual'] ?? 0), 4));
        $sheet->setCellValue('H'.$baris, $this->data['ringkasan']['rencana_kumulatif']);
        $sheet->setCellValue('I'.$baris, $this->data['ringkasan']['realisasi_kumulatif']);
        $sheet->setCellValue('J'.$baris, $this->data['ringkasan']['deviasi']);
        $this->tandaiNegatif($sheet, 'J'.$baris, $this->data['ringkasan']['deviasi']);
        $sheet->getStyle('A'.$baris.':L'.$baris)->getFont()->setBold(true);
        $this->isi($sheet, 'A'.$baris.':L'.$baris, self::WARNA_JUMLAH);

        $this->gayaData($sheet, 'A'.$mulai.':L'.$baris);
        $this->rataTengah($sheet, 'A'.$mulai.':E'.$baris);
        $this->formatAngka($sheet, 'F'.$mulai.':J'.$baris, self::FORMAT_PERSEN);

        $catatan = $baris + 2;
        $sheet->setCellValue('A'.$catatan, 'Catatan: progres (%) adalah bobot terhadap nilai kontrak; realisasi diambil dari laporan progres berstatus Dikirim sampai '
            .ReportFormatter::tanggal($this->data['laporan_terakhir']['tanggal_laporan']).'.');
        $sheet->mergeCells('A'.$catatan.':L'.$catatan);
        $sheet->getStyle('A'.$catatan)->getFont()->setItalic(true)->setSize(8);

        return $catatan;
    }
}
