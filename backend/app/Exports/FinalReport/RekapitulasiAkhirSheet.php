<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 7 — rekapitulasi akhir: capaian progres, pekerjaan selesai/tersisa, kendala, kesimpulan,
 * dan kolom pengesahan. Kesimpulan disusun ReportService dari angka laporan, bukan teks bebas.
 */
class RekapitulasiAkhirSheet extends FinalReportSheet
{
    public function title(): string
    {
        return 'Rekapitulasi Akhir';
    }

    protected function tataHalaman(): array
    {
        return ['G', PageSetup::ORIENTATION_PORTRAIT];
    }

    protected function tulis(Worksheet $sheet): int
    {
        $h = $this->data['header'];
        $r = $this->data['ringkasan'];
        $l = $this->data['laporan_terakhir'];

        $this->lebarKolom($sheet, ['A' => 5, 'B' => 40, 'C' => 9, 'D' => 13, 'E' => 13, 'F' => 13, 'G' => 12]);
        $this->judul($sheet, 1, 'LAPORAN AKHIR PROYEK', 'G', 14);
        $this->judul($sheet, 2, 'REKAPITULASI AKHIR', 'G', 12);
        $sheet->getStyle('A2:G2')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        // A. Identitas singkat
        $baris = 4;
        $this->judulBagian($sheet, $baris++, 'A.', 'IDENTITAS PROYEK');
        $baris = $this->labelNilai($sheet, $baris, [
            ['Paket Pekerjaan', $h['nama_proyek']],
            ['Lokasi', $h['lokasi']],
            ['Nomor SPK', $h['nomor_spk']],
            ['Periode Pelaksanaan', ReportFormatter::rentangTanggal($h['tanggal_mulai'], $h['tanggal_selesai'])],
            ['Kontraktor Pelaksana', $h['kontraktor_pelaksana']],
            ['Data s/d Laporan Progres', ReportFormatter::tanggal($l['tanggal_laporan']).' ('.$l['nama_periode'].')'],
        ]) + 1;

        // B. Capaian progres
        $this->judulBagian($sheet, $baris++, 'B.', 'CAPAIAN PROGRES');
        $barisDeviasi = $baris + 3;
        $baris = $this->labelNilai($sheet, $baris, [
            ['Total Bobot Rencana', $r['total_rencana'], true],
            ['Progres Rencana s/d Periode Terakhir', $r['rencana_kumulatif'], true],
            ['Progres Realisasi s/d Periode Terakhir', $r['realisasi_kumulatif'], true],
            ['Deviasi Akhir', $r['deviasi'], true],
            ['Sisa Progres', $r['sisa_progres'], true],
            ['Status Proyek', $this->data['status_proyek']['label']],
        ]) + 1;
        $this->tandaiNegatif($sheet, 'C'.$barisDeviasi, $r['deviasi']);

        // C. Ringkasan hasil pekerjaan
        $this->judulBagian($sheet, $baris++, 'C.', 'RINGKASAN HASIL PEKERJAAN');
        $sheet->setCellValue('B'.$baris, $r['jumlah_pekerjaan_selesai'].' dari '.$r['jumlah_pekerjaan'].' item pekerjaan telah mencapai volume kontrak.');
        $sheet->mergeCells('B'.$baris.':G'.$baris);
        $baris += 1;
        $baris = $this->tabelPekerjaan($sheet, $baris) + 2;

        // D. Kendala
        $this->judulBagian($sheet, $baris++, 'D.', 'CATATAN KENDALA PENTING');
        $baris = $this->tabelKendala($sheet, $baris) + 2;

        // E. Kesimpulan
        $this->judulBagian($sheet, $baris++, 'E.', 'KESIMPULAN');
        foreach ($this->data['kesimpulan'] as $i => $kalimat) {
            $sheet->setCellValue('A'.$baris, ($i + 1).'.');
            $sheet->setCellValue('B'.$baris, $kalimat);
            $sheet->mergeCells('B'.$baris.':G'.$baris);
            $sheet->getStyle('A'.$baris.':B'.$baris)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $this->rataTengah($sheet, 'A'.$baris);
            $this->tinggiBaris($sheet, $baris, ['B:G' => $kalimat]);
            $baris++;
        }

        return $this->pengesahan($sheet, $baris + 2);
    }

    /** @param  list<array{0:string,1:mixed,2?:bool}>  $isi */
    private function labelNilai(Worksheet $sheet, int $baris, array $isi): int
    {
        $mulai = $baris;

        foreach ($isi as $item) {
            [$label, $nilai] = $item;
            $sheet->setCellValue('B'.$baris, $label.(empty($item[2]) ? '' : ' (%)'));
            $sheet->setCellValue('C'.$baris, $nilai ?? '-');
            $sheet->mergeCells('C'.$baris.':G'.$baris);

            if (! empty($item[2])) {
                $sheet->getStyle('C'.$baris)->getNumberFormat()->setFormatCode(self::FORMAT_PERSEN);
            }

            $this->tinggiBaris($sheet, $baris, ['C:G' => is_string($nilai) ? $nilai : null]);
            $baris++;
        }

        $this->gayaData($sheet, 'B'.$mulai.':G'.($baris - 1));
        $sheet->getStyle('B'.$mulai.':B'.($baris - 1))->getFont()->setBold(true);
        $sheet->getStyle('C'.$mulai.':C'.($baris - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return $baris;
    }

    private function tabelPekerjaan(Worksheet $sheet, int $baris): int
    {
        $header = $baris;
        $sheet->fromArray([['NO.', 'URAIAN PEKERJAAN', 'SATUAN', 'VOLUME KONTRAK', 'VOLUME REALISASI', 'SISA VOLUME', 'REALISASI (%)']], null, 'A'.$header);
        $this->gayaHeader($sheet, 'A'.$header.':G'.$header);
        $sheet->getRowDimension($header)->setRowHeight(28);
        $baris++;

        $items = collect($this->data['kategori'])->flatMap(fn (array $k) => $k['items']);
        $kelompok = [
            'Pekerjaan Selesai' => $items->where('selesai', true)->values(),
            'Pekerjaan Belum Selesai' => $items->where('selesai', false)->values(),
        ];

        foreach ($kelompok as $judul => $daftar) {
            $sheet->setCellValue('A'.$baris, $judul.' ('.$daftar->count().' item)');
            $sheet->mergeCells('A'.$baris.':G'.$baris);
            $sheet->getStyle('A'.$baris)->getFont()->setBold(true);
            $this->isi($sheet, 'A'.$baris.':G'.$baris, self::WARNA_KATEGORI);
            $baris++;

            if ($daftar->isEmpty()) {
                $sheet->setCellValue('B'.$baris, 'Tidak ada.');
                $baris++;

                continue;
            }

            foreach ($daftar as $i => $item) {
                $sheet->fromArray([[
                    $i + 1,
                    $item['uraian'],
                    $item['satuan'],
                    $item['volume'],
                    $item['realisasi_sd_bulan_ini']['volume'],
                    $item['sisa_volume'],
                    $item['keterangan_persen'],
                ]], null, 'A'.$baris, true);
                $this->tinggiBaris($sheet, $baris, ['B' => $item['uraian']]);
                $baris++;
            }
        }

        $akhir = $baris - 1;
        $this->gayaData($sheet, 'A'.($header + 1).':G'.$akhir);
        $this->rataTengah($sheet, 'A'.($header + 1).':A'.$akhir);
        $this->rataTengah($sheet, 'C'.($header + 1).':C'.$akhir);
        $this->formatAngka($sheet, 'D'.($header + 1).':F'.$akhir, self::FORMAT_VOLUME);
        $this->formatAngka($sheet, 'G'.($header + 1).':G'.$akhir, self::FORMAT_PERSEN);

        return $akhir;
    }

    private function tabelKendala(Worksheet $sheet, int $baris): int
    {
        $kendala = $this->data['kendala'];

        if ($kendala === []) {
            $sheet->setCellValue('B'.$baris, 'Tidak ada kendala yang dicatat pada laporan progres.');
            $sheet->mergeCells('B'.$baris.':G'.$baris);

            return $baris;
        }

        $header = $baris;
        $sheet->fromArray([['NO.', 'KENDALA', 'TANGGAL', null, 'TINDAK LANJUT', null, 'STATUS']], null, 'A'.$header);
        $sheet->mergeCells('C'.$header.':D'.$header);
        $sheet->mergeCells('E'.$header.':F'.$header);
        $this->gayaHeader($sheet, 'A'.$header.':G'.$header);
        $baris++;

        // Kendala yang belum selesai ditampilkan lebih dulu.
        usort($kendala, fn ($a, $b) => ($a['status'] === 'SELESAI') <=> ($b['status'] === 'SELESAI'));

        foreach ($kendala as $i => $k) {
            $teks = $this->teksKendala($k);
            $tanggal = ReportFormatter::tanggal($k['tanggal_laporan']).($k['nama_periode'] ? "\n(".$k['nama_periode'].')' : '');

            $sheet->fromArray([[
                $i + 1, $teks, $tanggal, null, $k['tindak_lanjut'] ?: '-', null, $this->labelStatusKendala($k['status']),
            ]], null, 'A'.$baris, true);
            $sheet->mergeCells('C'.$baris.':D'.$baris);
            $sheet->mergeCells('E'.$baris.':F'.$baris);
            $this->tinggiBaris($sheet, $baris, ['B' => $teks, 'C:D' => $tanggal, 'E:F' => $k['tindak_lanjut']]);
            $baris++;
        }

        $this->gayaData($sheet, 'A'.($header + 1).':G'.($baris - 1));
        $this->rataTengah($sheet, 'A'.($header + 1).':A'.($baris - 1));
        $this->rataTengah($sheet, 'C'.($header + 1).':C'.($baris - 1));
        $this->rataTengah($sheet, 'G'.($header + 1).':G'.($baris - 1));

        return $baris - 1;
    }

    /** Kolom pengesahan; nama hanya diisi dari data proyek, selebihnya dibiarkan kosong. */
    private function pengesahan(Worksheet $sheet, int $baris): int
    {
        $h = $this->data['header'];
        $kosong = '(.......................................)';

        $sheet->setCellValue('E'.$baris, '........................, ............................');
        $baris++;

        $isi = [
            ['Diperiksa oleh,', 'Dibuat oleh,'],
            ['Konsultan Pengawas', 'Kontraktor Pelaksana'],
            [$h['konsultan_pengawas'] ?: '', $h['kontraktor_pelaksana'] ?: ''],
        ];

        foreach ($isi as [$kiri, $kanan]) {
            $sheet->setCellValue('B'.$baris, $kiri);
            $sheet->setCellValue('E'.$baris, $kanan);
            $baris++;
        }

        $barisNama = $baris + 4;
        $sheet->setCellValue('B'.$barisNama, $h['nama_site_engineer'] ?: $kosong);
        $sheet->setCellValue('E'.$barisNama, $h['nama_pelaksana_lapangan'] ?: $kosong);
        $sheet->setCellValue('B'.($barisNama + 1), 'Site Engineer');
        $sheet->setCellValue('E'.($barisNama + 1), 'Pelaksana Lapangan');

        for ($i = $baris - 4; $i <= $barisNama + 1; $i++) {
            $sheet->mergeCells('E'.$i.':G'.$i);
            $this->rataTengah($sheet, 'B'.$i.':G'.$i);
        }

        $sheet->getStyle('B'.$barisNama.':G'.$barisNama)->getFont()->setBold(true)->setUnderline(true);

        return $barisNama + 1;
    }
}
