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

/** Export Excel laporan harian (rekap laporan progres QS per tanggal). */
class DailyReportExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const KOLOM = 10;

    private int $barisTabelMulai = 10;

    private int $barisTabelSelesai = 10;

    public function __construct(private readonly array $data) {}

    public function title(): string
    {
        return 'Laporan Harian';
    }

    public function array(): array
    {
        $h = $this->data['header'];
        $kosong = $this->row([]);

        $rows = [];
        $rows[] = $this->row(['LAPORAN HARIAN']);
        $rows[] = $kosong;
        $rows[] = $this->row(['PEKERJAAN', ':', $h['nama_proyek']]);
        $rows[] = $this->row(['LOKASI', ':', $h['lokasi']]);
        $rows[] = $this->row(['NO. SPK', ':', $h['nomor_spk']]);
        $rows[] = $this->row(['PERIODE', ':', ReportFormatter::rentangTanggal($this->data['periode']['dari'], $this->data['periode']['sampai'])]);
        $rows[] = $kosong;

        $rows[] = $this->row([
            'Tanggal', 'Pelapor', 'Uraian Pekerjaan', 'Satuan', 'Volume Realisasi',
            'Bobot Realisasi (%)', 'Keterangan', 'Material', 'Kendala', 'Tindak Lanjut',
        ]);

        $this->barisTabelMulai = count($rows) + 1;

        foreach ($this->data['laporan'] as $laporan) {
            $material = collect($laporan['material'])
                ->map(fn ($m) => $m['nama_material'].' '.$m['jumlah'].' '.($m['satuan'] ?? ''))
                ->implode('; ');
            $kendala = collect($laporan['kendala'])->map(fn ($k) => $k['deskripsi'])->implode('; ');
            $tindak = collect($laporan['kendala'])->map(fn ($k) => $k['tindak_lanjut'])->filter()->implode('; ');

            if (empty($laporan['detail'])) {
                $rows[] = $this->row([
                    ReportFormatter::tanggal($laporan['tanggal_laporan']), $laporan['pelapor'],
                    '-', null, null, null, $laporan['keterangan'], $material, $kendala, $tindak,
                ]);

                continue;
            }

            foreach ($laporan['detail'] as $index => $detail) {
                $rows[] = $this->row([
                    $index === 0 ? ReportFormatter::tanggal($laporan['tanggal_laporan']) : null,
                    $index === 0 ? $laporan['pelapor'] : null,
                    $detail['uraian_pekerjaan'],
                    $detail['satuan'],
                    $detail['volume_realisasi'],
                    $detail['bobot_realisasi'],
                    $detail['keterangan'] ?? $laporan['keterangan'],
                    $index === 0 ? $material : null,
                    $index === 0 ? $kendala : null,
                    $index === 0 ? $tindak : null,
                ]);
            }
        }

        $this->barisTabelSelesai = max(count($rows), $this->barisTabelMulai);

        $rows[] = $kosong;
        $rows[] = $this->row([null, null, 'Total bobot realisasi (%)', null, null, $this->data['ringkasan']['bobot_realisasi']]);

        return $rows;
    }

    private function row(array $values): array
    {
        return array_pad($values, self::KOLOM, null);
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 20, 'C' => 38, 'D' => 9, 'E' => 15, 'F' => 16, 'G' => 30, 'H' => 30, 'I' => 30, 'J' => 30];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $headerRow = $this->barisTabelMulai - 1;

                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3:A6')->getFont()->setBold(true);

                $sheet->getStyle('A'.$headerRow.':J'.$headerRow)->getFont()->setBold(true);
                $sheet->getStyle('A'.$headerRow.':J'.$headerRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

                $sheet->getStyle('A'.$headerRow.':J'.$this->barisTabelSelesai)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle('G'.$this->barisTabelMulai.':J'.$this->barisTabelSelesai)
                    ->getAlignment()->setWrapText(true);

                $sheet->getPageSetup()->setOrientation('landscape');
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}
