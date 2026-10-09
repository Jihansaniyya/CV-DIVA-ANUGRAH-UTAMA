<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Sheet 1 — identitas proyek, pihak terkait, dan ringkasan progres terakhir. */
class InformasiProyekSheet extends FinalReportSheet
{
    public function title(): string
    {
        return 'Informasi Proyek';
    }

    protected function tataHalaman(): array
    {
        return ['D', PageSetup::ORIENTATION_PORTRAIT];
    }

    protected function tulis(Worksheet $sheet): int
    {
        $h = $this->data['header'];
        $l = $this->data['laporan_terakhir'];
        $r = $this->data['ringkasan'];
        $titik = $this->data['kurva_s']['titik'];
        $hargaTotal = (float) ($this->data['total']['harga_pekerjaan'] ?? 0);
        $jumlahItem = $r['jumlah_pekerjaan'];
        $jumlahBulan = count(array_unique(array_column($titik, 'bulan_ke')));

        $this->lebarKolom($sheet, ['A' => 5, 'B' => 38, 'C' => 2, 'D' => 62]);
        $this->judul($sheet, 1, 'LAPORAN AKHIR PROYEK', 'D', 14);
        $this->judul($sheet, 2, 'INFORMASI PROYEK', 'D', 12);
        $sheet->getStyle('A2:D2')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        $bagian = [
            ['A.', 'IDENTITAS PROYEK', [
                ['Nama Paket Pekerjaan', $h['nama_proyek']],
                ['Nomor SPK', $h['nomor_spk']],
                ['Tanggal SPK', ReportFormatter::tanggal($h['tanggal_spk'])],
                ['Lokasi Pekerjaan', $h['lokasi']],
                ['Sumber Dana', $h['sumber_dana']],
                ['Tahun Anggaran', $h['tahun_anggaran']],
                ['Nilai Pekerjaan', $hargaTotal > 0 ? $hargaTotal : null, self::FORMAT_RUPIAH],
                ['Periode Pelaksanaan', ReportFormatter::rentangTanggal($h['tanggal_mulai'], $h['tanggal_selesai'])],
                ['Jangka Waktu Pelaksanaan', $h['jangka_waktu_hari'] ? $h['jangka_waktu_hari'].' hari kalender' : null],
                ['Jumlah Periode', count($titik).' minggu ('.$jumlahBulan.' bulan)'],
                ['Volume dan Satuan Pekerjaan', $jumlahItem.' item pekerjaan; rincian volume dan satuan pada sheet "Rencana & Realisasi"'],
            ]],
            ['B.', 'PIHAK TERKAIT', [
                ['Kontraktor Pelaksana', $h['kontraktor_pelaksana']],
                ['Konsultan Pengawas', $h['konsultan_pengawas']],
                ['Site Engineer', $h['nama_site_engineer']],
                ['Pelaksana Lapangan', $h['nama_pelaksana_lapangan']],
            ]],
            ['C.', 'RINGKASAN PROGRES', [
                ['Tanggal Laporan Progres Terakhir', ReportFormatter::tanggal($l['tanggal_laporan'])],
                ['Periode Laporan Terakhir', $l['nama_periode'].' (Minggu '.$l['minggu_ke_romawi'].', Bulan '.$l['bulan_ke_romawi'].'), '
                    .ReportFormatter::rentangTanggal($l['tanggal_mulai'], $l['tanggal_selesai'])],
                ['Jumlah Laporan Progres Terkirim', $l['jumlah_laporan'].' laporan'],
                ['Progres Rencana s/d Periode Terakhir', $r['rencana_kumulatif'], self::FORMAT_PERSEN],
                ['Progres Realisasi s/d Periode Terakhir', $r['realisasi_kumulatif'], self::FORMAT_PERSEN],
                ['Deviasi', $r['deviasi'], self::FORMAT_PERSEN],
                ['Total Bobot Rencana', $r['total_rencana'], self::FORMAT_PERSEN],
                ['Progres Akhir Realisasi', $r['realisasi_kumulatif'], self::FORMAT_PERSEN],
                ['Sisa Progres', $r['sisa_progres'], self::FORMAT_PERSEN],
                ['Status Proyek', $this->data['status_proyek']['label']],
                ['Keterangan Proyek', $this->data['keterangan_proyek']],
            ]],
        ];

        $baris = 4;

        foreach ($bagian as [$nomor, $judul, $isi]) {
            $this->judulBagian($sheet, $baris, $nomor, $judul);
            $mulai = ++$baris;

            foreach ($isi as $item) {
                [$label, $nilai] = $item;
                $label .= ($item[2] ?? null) === self::FORMAT_PERSEN ? ' (%)' : '';
                $sheet->setCellValue('B'.$baris, $label);
                $sheet->setCellValue('C'.$baris, ':');
                $sheet->setCellValue('D'.$baris, $nilai ?? '-');

                if (isset($item[2]) && $nilai !== null) {
                    $sheet->getStyle('D'.$baris)->getNumberFormat()->setFormatCode($item[2]);
                }

                if (str_starts_with($label, 'Deviasi')) {
                    $this->tandaiNegatif($sheet, 'D'.$baris, $nilai);
                }

                $this->tinggiBaris($sheet, $baris, ['D' => is_string($nilai) ? $nilai : null], 17);
                $baris++;
            }

            $rentang = 'B'.$mulai.':D'.($baris - 1);
            $this->garis($sheet, $rentang);
            $sheet->getStyle($rentang)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $sheet->getStyle('D'.$mulai.':D'.($baris - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('C'.$mulai.':C'.($baris - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B'.$mulai.':B'.($baris - 1))->getFont()->setBold(true);
            $baris++;
        }

        $sheet->setCellValue('B'.$baris, 'Dokumen disusun otomatis dari data aplikasi pada '.now()->translatedFormat('d F Y H:i').'.');
        $sheet->mergeCells('B'.$baris.':D'.$baris);
        $sheet->getStyle('B'.$baris)->getFont()->setItalic(true)->setSize(8);

        return $baris;
    }
}
