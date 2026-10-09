<?php

namespace App\Exports;

use App\Exports\FinalReport\DokumentasiSheet;
use App\Exports\FinalReport\InformasiProyekSheet;
use App\Exports\FinalReport\KurvaSSheet;
use App\Exports\FinalReport\RekapBulananSheet;
use App\Exports\FinalReport\RekapitulasiAkhirSheet;
use App\Exports\FinalReport\RekapMingguanSheet;
use App\Exports\FinalReport\RencanaRealisasiSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Excel Laporan Akhir Proyek: satu workbook berisi tujuh sheet berurutan
 * (informasi proyek, rekap mingguan, rekap bulanan, rencana & realisasi, Kurva S,
 * dokumentasi, rekapitulasi akhir). Data berasal dari ReportService::final().
 */
class FinalReportExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        return [
            new InformasiProyekSheet($this->data),
            new RekapMingguanSheet($this->data),
            new RekapBulananSheet($this->data),
            new RencanaRealisasiSheet($this->data),
            new KurvaSSheet($this->data),
            new DokumentasiSheet($this->data),
            new RekapitulasiAkhirSheet($this->data),
        ];
    }
}
