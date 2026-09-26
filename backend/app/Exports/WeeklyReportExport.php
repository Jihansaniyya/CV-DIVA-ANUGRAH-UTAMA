<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel laporan mingguan mengikuti struktur laporan-mingguan.png. */
class WeeklyReportExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private int $barisTabelMulai = 11;

    private int $barisTabelSelesai = 11;

    private int $barisRekapSelesai = 11;

    public function __construct(private readonly array $data) {}

    public function title(): string
    {
        return 'Laporan Mingguan';
    }

    public function array(): array
    {
        $h = $this->data['header'];
        $p = $this->data['periode'];
        $kosong = array_fill(0, 14, null);

        $rows = [];
        $rows[] = $this->row(['LAPORAN MINGGUAN']);
        $rows[] = $kosong;

        $rows[] = $this->row(['PEKERJAAN', ':', $h['nama_proyek'], null, null, null, null, null, null, 'Bulan Ke', ':', ReportFormatter::romawi((int) $p['bulan_ke'])]);
        $rows[] = $this->row(['LOKASI', ':', $h['lokasi'], null, null, null, null, null, null, 'Minggu Ke', ':', ReportFormatter::romawi((int) $p['minggu_ke'])]);
        $rows[] = $this->row(['SUMBER DANA', ':', $h['sumber_dana'], null, null, null, null, null, null, 'Periode', ':', ReportFormatter::rentangTanggal($p['tanggal_mulai'], $p['tanggal_selesai'])]);
        $rows[] = $this->row(['TAHUN ANGGARAN', ':', $h['tahun_anggaran'], null, null, null, null, null, null, 'Kontraktor Pelaksana', ':', $h['kontraktor_pelaksana']]);
        $rows[] = $this->row(['NO. SPK', ':', $h['nomor_spk'], null, null, null, null, null, null, 'Konsultan Pengawas', ':', $h['konsultan_pengawas']]);
        $rows[] = $this->row(['TANGGAL SPK', ':', ReportFormatter::tanggal($h['tanggal_spk'])]);
        $rows[] = $kosong;

        // Header tabel (2 baris, kolom grup di-merge pada registerEvents).
        $rows[] = $this->row([
            'NO.', 'Uraian', 'Satuan', 'Volume', 'Harga Satuan (Rp.)', 'Harga Pekerjaan (Rp.)', 'Bobot (%)',
            'REALISASI MINGGU LALU', null, 'REALISASI MINGGU INI', null, 'REALISASI S/D MINGGU INI', null, 'Keterangan %',
        ]);
        $rows[] = $this->row([
            null, null, null, null, null, null, null,
            'Volume', 'Bobot (%)', 'Volume', 'Bobot (%)', 'Volume', 'Bobot (%)', null,
        ]);

        $this->barisTabelMulai = count($rows) + 1;

        foreach ($this->data['kategori'] as $kategori) {
            $rows[] = $this->row([$kategori['kode'], $kategori['nama']]);

            foreach ($kategori['items'] as $item) {
                $rows[] = $this->row([
                    $item['no'],
                    $item['uraian'],
                    $item['satuan'],
                    $item['volume'],
                    $item['harga_satuan'],
                    $item['harga_pekerjaan'],
                    $item['bobot'],
                    $item['realisasi_lalu']['volume'] ?: null,
                    $item['realisasi_lalu']['bobot'] ?: null,
                    $item['realisasi_ini']['volume'] ?: null,
                    $item['realisasi_ini']['bobot'] ?: null,
                    $item['realisasi_sd']['volume'] ?: null,
                    $item['realisasi_sd']['bobot'] ?: null,
                    $item['keterangan_persen'].'%',
                ]);
            }

            $s = $kategori['subtotal'];
            $rows[] = $this->row([
                null, 'JUMLAH '.$kategori['nama'], null, null, null,
                $s['harga_pekerjaan'], $s['bobot'], null,
                $s['realisasi_lalu']['bobot'] ?? 0, null,
                $s['realisasi_ini']['bobot'] ?? 0, null,
                $s['realisasi_sd']['bobot'] ?? 0,
            ]);
        }

        $t = $this->data['total'];
        $rows[] = $this->row([
            null, 'JUMLAH', null, null, null,
            $t['harga_pekerjaan'], $t['bobot'], null,
            $t['realisasi_lalu']['bobot'] ?? 0, null,
            $t['realisasi_ini']['bobot'] ?? 0, null,
            $t['realisasi_sd']['bobot'] ?? 0,
        ]);

        $this->barisTabelSelesai = count($rows);

        $r = $this->data['rekap'];
        $rows[] = $this->row([null, 'REALISASI MINGGU LALU', null, null, null, null, null, null, null, null, null, null, $r['realisasi_minggu_lalu']]);
        $rows[] = $this->row([null, 'REALISASI MINGGU INI', null, null, null, null, null, null, null, null, null, null, $r['realisasi_minggu_ini']]);
        $rows[] = $this->row([null, 'REALISASI SAMPAI DENGAN MINGGU INI', null, null, null, null, null, null, null, null, null, null, $r['realisasi_sd_minggu_ini']]);
        $rows[] = $this->row([null, 'RENCANA KOMULATIF SAMPAI DENGAN MINGGU INI', null, null, null, null, null, null, null, null, null, null, $r['rencana_kumulatif_sd_minggu_ini']]);
        $rows[] = $this->row([null, 'DEVIASI', null, null, null, null, null, null, null, null, null, null, $r['deviasi']]);
        $this->barisRekapSelesai = count($rows);

        $rows[] = $kosong;
        $rows[] = $kosong;
        $rows[] = $this->row([null, 'Diperiksa,', null, null, null, null, null, null, null, 'Dibuat Oleh']);
        $rows[] = $this->row([null, 'Konsultan Pengawas', null, null, null, null, null, null, null, 'Kontraktor Pelaksana']);
        $rows[] = $this->row([null, $h['konsultan_pengawas'], null, null, null, null, null, null, null, $h['kontraktor_pelaksana']]);
        $rows[] = $kosong;
        $rows[] = $kosong;
        $rows[] = $this->row([null, $h['nama_site_engineer'], null, null, null, null, null, null, null, $h['nama_pelaksana_lapangan']]);
        $rows[] = $this->row([null, 'Site Engineer', null, null, null, null, null, null, null, 'Pelaksana Lapangan']);

        return $rows;
    }

    private function row(array $values): array
    {
        return array_pad($values, 14, null);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6, 'B' => 42, 'C' => 9, 'D' => 11, 'E' => 16, 'F' => 18, 'G' => 10,
            'H' => 12, 'I' => 11, 'J' => 12, 'K' => 11, 'L' => 12, 'M' => 11, 'N' => 13,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $headerRow = $this->barisTabelMulai - 2;

                $sheet->mergeCells('A1:N1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'N'] as $col) {
                    $sheet->mergeCells($col.$headerRow.':'.$col.($headerRow + 1));
                }

                $sheet->mergeCells('H'.$headerRow.':I'.$headerRow);
                $sheet->mergeCells('J'.$headerRow.':K'.$headerRow);
                $sheet->mergeCells('L'.$headerRow.':M'.$headerRow);

                $sheet->getStyle('A'.$headerRow.':N'.($headerRow + 1))->getFont()->setBold(true);
                $sheet->getStyle('A'.$headerRow.':N'.($headerRow + 1))->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('A'.$headerRow.':N'.$this->barisTabelSelesai)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle('D'.$this->barisTabelMulai.':N'.$this->barisTabelSelesai)
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('E'.$this->barisTabelMulai.':F'.$this->barisTabelSelesai)
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('A3:A8')->getFont()->setBold(true);
                $sheet->getStyle('J3:J7')->getFont()->setBold(true);
                $sheet->getStyle('B'.$this->barisTabelSelesai.':N'.$this->barisRekapSelesai)->getFont()->setBold(true);
                $sheet->getStyle('M'.($this->barisTabelSelesai + 1).':M'.$this->barisRekapSelesai)
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                $sheet->getPageSetup()->setOrientation('landscape');
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}
