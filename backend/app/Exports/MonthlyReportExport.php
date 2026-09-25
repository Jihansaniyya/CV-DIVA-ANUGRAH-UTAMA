<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Export Excel laporan bulanan mengikuti struktur laporan-bulanan.png. */
class MonthlyReportExport implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private int $jumlahKolom;

    private int $jumlahPeriode;

    private int $barisHeaderTabel = 10;

    private int $barisTabelMulai = 12;

    private int $barisTabelSelesai = 12;

    private int $barisRekapMulai = 12;

    public function __construct(private readonly array $data)
    {
        $this->jumlahPeriode = count($this->data['kolom_periode']);
        // 7 kolom identitas + kolom periode + 1 kolom keterangan
        $this->jumlahKolom = 7 + $this->jumlahPeriode + 1;
    }

    public function title(): string
    {
        return 'Laporan Bulanan';
    }

    public function array(): array
    {
        $h = $this->data['header'];
        $p = $this->data['periode'];
        $kosong = $this->row([]);

        $kolomKanan = 8;

        $rows = [];
        $rows[] = $this->row(['LAPORAN BULANAN']);
        $rows[] = $kosong;
        $rows[] = $this->rowWithRight(['PEKERJAAN', ':', $h['nama_proyek']], $kolomKanan, ['Bulan Ke', ':', ReportFormatter::romawi((int) $p['bulan_ke'])]);
        $rows[] = $this->rowWithRight(['LOKASI', ':', $h['lokasi']], $kolomKanan, ['Periode', ':', ReportFormatter::rentangTanggal($p['tanggal_mulai'], $p['tanggal_selesai'])]);
        $rows[] = $this->rowWithRight(['SUMBER DANA', ':', $h['sumber_dana']], $kolomKanan, ['Kontraktor Pelaksana', ':', $h['kontraktor_pelaksana']]);
        $rows[] = $this->rowWithRight(['TAHUN ANGGARAN', ':', $h['tahun_anggaran']], $kolomKanan, ['Konsultan Pengawas', ':', $h['konsultan_pengawas']]);
        $rows[] = $this->row(['NO. SPK', ':', $h['nomor_spk']]);
        $rows[] = $this->row(['TANGGAL SPK', ':', ReportFormatter::tanggal($h['tanggal_spk'])]);
        $rows[] = $kosong;

        $this->barisHeaderTabel = count($rows) + 1;

        $judulJadwal = 'JANGKA WAKTU PELAKSANAAN '.($h['jangka_waktu_hari'] ?? '-').' HARI KALENDER';

        $baris1 = ['NO.', 'Uraian', 'Satuan', 'Volume', 'Harga Satuan (Rp.)', 'Harga Pekerjaan (Rp.)', 'Bobot (%)', $judulJadwal];
        $baris1 = array_pad($baris1, 7 + $this->jumlahPeriode, null);
        $baris1[] = 'Keterangan %';
        $rows[] = $this->row($baris1);

        $baris2 = array_fill(0, 7, null);
        foreach ($this->data['kolom_periode'] as $kolom) {
            $baris2[] = 'Minggu '.$kolom['minggu_ke_romawi'].' (Bulan '.$kolom['bulan_ke_romawi'].')';
        }
        $baris2[] = null;
        $rows[] = $this->row($baris2);

        $this->barisTabelMulai = count($rows) + 1;

        foreach ($this->data['kategori'] as $kategori) {
            $rows[] = $this->row([$kategori['kode'], $kategori['nama']]);

            foreach ($kategori['items'] as $item) {
                $baris = [
                    $item['no'], $item['uraian'], $item['satuan'], $item['volume'],
                    $item['harga_satuan'], $item['harga_pekerjaan'], $item['bobot'],
                ];

                foreach ($item['jadwal'] as $jadwal) {
                    $baris[] = $jadwal['bobot'] > 0 ? $jadwal['bobot'] : null;
                }

                $baris[] = $item['keterangan_persen'].'%';
                $rows[] = $this->row($baris);
            }

            $s = $kategori['subtotal'];
            $baris = [null, 'JUMLAH '.$kategori['nama'], null, null, null, $s['harga_pekerjaan'], $s['bobot']];

            foreach ($s['jadwal'] ?? [] as $jadwal) {
                $baris[] = $jadwal['bobot'] > 0 ? $jadwal['bobot'] : null;
            }

            $rows[] = $this->row($baris);
        }

        $t = $this->data['total'];
        $baris = [null, 'JUMLAH', null, null, null, $t['harga_pekerjaan'], $t['bobot']];
        foreach ($t['jadwal'] ?? [] as $jadwal) {
            $baris[] = $jadwal['bobot'] > 0 ? $jadwal['bobot'] : null;
        }
        $rows[] = $this->row($baris);

        $this->barisRekapMulai = count($rows) + 1;

        $rekap = collect($this->data['rekap_periode']);
        $rows[] = $this->rekapRow('RENCANA', 'Mingguan (%)', $rekap->pluck('rencana_mingguan')->all());
        $rows[] = $this->rekapRow(null, 'Komulatif (%)', $rekap->pluck('rencana_kumulatif')->all());
        $rows[] = $this->rekapRow('REALISASI', 'Mingguan (%)', $rekap->pluck('realisasi_mingguan')->all());
        $rows[] = $this->rekapRow(null, 'Komulatif (%)', $rekap->pluck('realisasi_kumulatif')->all());
        $rows[] = $this->rekapRow('DEVIASI', null, $rekap->pluck('deviasi')->all());

        $this->barisTabelSelesai = count($rows);

        $rows[] = $kosong;
        $rows[] = $kosong;
        $rows[] = $this->rowWithRight([null, 'Diperiksa,'], $kolomKanan, ['Dibuat Oleh']);
        $rows[] = $this->rowWithRight([null, 'Konsultan Pengawas'], $kolomKanan, ['Kontraktor Pelaksana']);
        $rows[] = $this->rowWithRight([null, $h['konsultan_pengawas']], $kolomKanan, [$h['kontraktor_pelaksana']]);
        $rows[] = $kosong;
        $rows[] = $kosong;
        $rows[] = $this->rowWithRight([null, $h['nama_site_engineer']], $kolomKanan, [$h['nama_pelaksana_lapangan']]);
        $rows[] = $this->rowWithRight([null, 'Site Engineer'], $kolomKanan, ['Pelaksana Lapangan']);

        return $rows;
    }

    /** @param array<int,float|null> $nilai */
    private function rekapRow(?string $judul, ?string $sub, array $nilai): array
    {
        $baris = array_fill(0, 7, null);
        $baris[1] = $judul;
        $baris[5] = $sub;

        foreach ($nilai as $v) {
            $baris[] = $v;
        }

        return $this->row($baris);
    }

    private function row(array $values): array
    {
        return array_pad($values, $this->jumlahKolom, null);
    }

    /** Gabungkan blok kiri dan blok kanan pada baris header identitas proyek. */
    private function rowWithRight(array $kiri, int $mulaiKanan, array $kanan): array
    {
        $baris = array_pad($kiri, $mulaiKanan, null);

        foreach ($kanan as $nilai) {
            $baris[] = $nilai;
        }

        return $this->row($baris);
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 6, 'B' => 42, 'C' => 9, 'D' => 11, 'E' => 16, 'F' => 18, 'G' => 10];

        for ($i = 0; $i < $this->jumlahPeriode; $i++) {
            $widths[Coordinate::stringFromColumnIndex(8 + $i)] = 12;
        }

        $widths[Coordinate::stringFromColumnIndex($this->jumlahKolom)] = 13;

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $kolomTerakhir = Coordinate::stringFromColumnIndex($this->jumlahKolom);
                $h1 = $this->barisHeaderTabel;
                $h2 = $h1 + 1;

                $sheet->mergeCells('A1:'.$kolomTerakhir.'1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                    $sheet->mergeCells($col.$h1.':'.$col.$h2);
                }

                $sheet->mergeCells('H'.$h1.':'.Coordinate::stringFromColumnIndex(7 + $this->jumlahPeriode).$h1);
                $sheet->mergeCells($kolomTerakhir.$h1.':'.$kolomTerakhir.$h2);

                $sheet->getStyle('A'.$h1.':'.$kolomTerakhir.$h2)->getFont()->setBold(true);
                $sheet->getStyle('A'.$h1.':'.$kolomTerakhir.$h2)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('A'.$h1.':'.$kolomTerakhir.$this->barisTabelSelesai)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle('D'.$this->barisTabelMulai.':'.$kolomTerakhir.$this->barisTabelSelesai)
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                $sheet->getStyle('A3:A8')->getFont()->setBold(true);
                $sheet->getStyle('B'.$this->barisRekapMulai.':'.$kolomTerakhir.$this->barisTabelSelesai)->getFont()->setBold(true);

                $sheet->getPageSetup()->setOrientation('landscape');
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}
