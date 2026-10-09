<?php

namespace App\Exports\FinalReport;

use App\Exports\ReportFormatter;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Properties;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 5 — tabel Kurva S seluruh periode proyek beserta grafik garis rencana vs realisasi kumulatif.
 * Data identik dengan halaman Kurva S: realisasi kosong untuk periode yang belum berjalan.
 */
class KurvaSSheet extends FinalReportSheet implements WithCharts
{
    private const TINGGI_GRAFIK = 24;

    private int $header = 0;

    public function title(): string
    {
        return 'Kurva S';
    }

    /**
     * Grafik ditambahkan pada AfterSheet karena posisinya bergantung pada jumlah periode.
     * Concern ini tetap dipasang agar writer menyertakan grafik ke file .xlsx.
     */
    public function charts(): array
    {
        return [];
    }

    protected function tataHalaman(): array
    {
        return ['J', PageSetup::ORIENTATION_LANDSCAPE];
    }

    protected function barisHeaderCetak(): ?array
    {
        return [$this->header, $this->header];
    }

    protected function tulis(Worksheet $sheet): int
    {
        $titik = $this->data['kurva_s']['titik'];

        $this->lebarKolom($sheet, [
            'A' => 5, 'B' => 12, 'C' => 9, 'D' => 9, 'E' => 36, 'F' => 11, 'G' => 13, 'H' => 11, 'I' => 13, 'J' => 11,
        ]);

        $header = $this->header = $this->kepala($sheet, 'Kurva S', 'J');
        $sheet->fromArray([[
            'NO.', 'PERIODE', 'MINGGU KE', 'BULAN KE', 'TANGGAL',
            'Rencana (%)', 'Rencana Kumulatif (%)', 'Realisasi (%)', 'Realisasi Kumulatif (%)', 'Deviasi (%)',
        ]], null, 'A'.$header);
        $this->gayaHeader($sheet, 'A'.$header.':J'.$header);
        $sheet->getRowDimension($header)->setRowHeight(30);

        $baris = $header + 1;

        foreach ($titik as $i => $t) {
            $sheet->fromArray([[
                $i + 1,
                $t['nama_periode'],
                ReportFormatter::romawi((int) $t['minggu_ke']),
                ReportFormatter::romawi((int) $t['bulan_ke']),
                ReportFormatter::rentangTanggal($t['tanggal_mulai'], $t['tanggal_selesai']),
                $t['rencana'],
                $t['rencana_kumulatif'],
                $t['aktual'],
                $t['aktual_kumulatif'],
                $t['deviasi'],
            ]], null, 'A'.$baris, true);
            $this->tandaiNegatif($sheet, 'J'.$baris, $t['deviasi']);
            $baris++;
        }

        $akhirData = $baris - 1;

        if ($titik === []) {
            $sheet->setCellValue('A'.$baris, 'Proyek belum memiliki periode pelaksanaan.');
            $sheet->mergeCells('A'.$baris.':J'.$baris);

            return $baris;
        }

        $this->gayaData($sheet, 'A'.($header + 1).':J'.$akhirData);
        $this->rataTengah($sheet, 'A'.($header + 1).':D'.$akhirData);
        $this->formatAngka($sheet, 'F'.($header + 1).':J'.$akhirData, self::FORMAT_PERSEN);

        $mulaiGrafik = $akhirData + 2;
        $sheet->addChart($this->grafik($header + 1, $akhirData, $mulaiGrafik));

        $catatan = $mulaiGrafik + self::TINGGI_GRAFIK + 1;
        $sheet->setCellValue('A'.$catatan, 'Sumbu horizontal: periode pelaksanaan (minggu). Sumbu vertikal: progres kumulatif (%). '
            .'Garis realisasi berhenti pada periode yang belum berjalan.');
        $sheet->mergeCells('A'.$catatan.':J'.$catatan);
        $sheet->getStyle('A'.$catatan)->getFont()->setItalic(true)->setSize(8);

        return $catatan;
    }

    private function grafik(int $mulai, int $selesai, int $barisGrafik): Chart
    {
        $jumlah = $selesai - $mulai + 1;
        $ref = fn (string $kolom, int $dari, ?int $sampai = null) => "'".$this->title()."'!\$".$kolom.'$'.$dari
            .($sampai !== null ? ':$'.$kolom.'$'.$sampai : '');

        $rencana = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $ref('G', $mulai, $selesai), null, $jumlah);
        $rencana->setLineColorProperties('1F4E79');
        $rencana->setLineStyleProperties(2.25);
        $realisasi = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $ref('I', $mulai, $selesai), null, $jumlah);
        $realisasi->setLineColorProperties('C00000');
        $realisasi->setLineStyleProperties(2.25);

        $series = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            [0, 1],
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('G', $mulai - 1), null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('I', $mulai - 1), null, 1),
            ],
            [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $ref('B', $mulai, $selesai), null, $jumlah)],
            [$rencana, $realisasi],
        );

        $sumbuY = new Axis;
        $sumbuY->setAxisOptionsProperties(Properties::AXIS_LABELS_NEXT_TO, null, null, null, null, null, '0', '100', '10');
        $sumbuY->setAxisNumberProperties('0');

        $chart = new Chart(
            'kurva_s',
            new Title('KURVA S — RENCANA vs REALISASI'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$series]),
            true,
            DataSeries::EMPTY_AS_GAP,
            new Title('Periode'),
            new Title('Progres Kumulatif (%)'),
            null,
            $sumbuY,
        );
        $chart->setTopLeftPosition('A'.$barisGrafik);
        $chart->setBottomRightPosition('K'.($barisGrafik + self::TINGGI_GRAFIK));

        return $chart;
    }
}
