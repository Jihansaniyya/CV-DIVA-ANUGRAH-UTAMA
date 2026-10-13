<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Blok identitas proyek pada header laporan Excel.
 *
 * Kiri: label A:B, titik dua C, nilai D:G. Kanan: label H:I, titik dua J, nilai K sampai kolom akhir.
 * Setiap item identitas berbentuk [labelKiri, nilaiKiri, labelKanan, nilaiKanan].
 */
trait MenataIdentitasProyek
{
    private int $barisIdentitasAwal = 3;

    /** @param array{0: string, 1: ?string, 2: ?string, 3: ?string} $item */
    private function barisIdentitas(array $item): array
    {
        [$labelKiri, $nilaiKiri, $labelKanan, $nilaiKanan] = $item;

        return [
            $labelKiri, null, ':', $nilaiKiri, null, null, null,
            $labelKanan, null, $labelKanan === null ? null : ':', $nilaiKanan,
        ];
    }

    /**
     * Gabung sel, wrap text, dan sesuaikan tinggi baris dengan panjang isi.
     *
     * @param  array<int, array{0: string, 1: ?string, 2: ?string, 3: ?string}>  $identitas
     * @param  array<string, float|int>  $lebarKolom
     */
    private function styleIdentitas(Worksheet $sheet, array $identitas, string $kolomAkhir, array $lebarKolom): void
    {
        $barisAwal = $this->barisIdentitasAwal;
        $barisAkhir = $barisAwal + count($identitas) - 1;

        $kapasitasKiri = $this->kapasitasKarakter($lebarKolom, 'D', 'G');
        $kapasitasKanan = $this->kapasitasKarakter($lebarKolom, 'K', $kolomAkhir);

        foreach ($identitas as $i => [, $nilaiKiri, , $nilaiKanan]) {
            $baris = $barisAwal + $i;

            $sheet->mergeCells("A{$baris}:B{$baris}");
            $sheet->mergeCells("D{$baris}:G{$baris}");
            $sheet->mergeCells("H{$baris}:I{$baris}");
            $sheet->mergeCells("K{$baris}:{$kolomAkhir}{$baris}");

            $jumlahBaris = max(
                1,
                (int) ceil(mb_strlen((string) $nilaiKiri) / $kapasitasKiri),
                (int) ceil(mb_strlen((string) $nilaiKanan) / $kapasitasKanan),
            );
            $sheet->getRowDimension($baris)->setRowHeight(15 * $jumlahBaris + 2);
        }

        $sheet->getStyle("A{$barisAwal}:{$kolomAkhir}{$barisAkhir}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);
        $sheet->getStyle("C{$barisAwal}:C{$barisAkhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J{$barisAwal}:J{$barisAkhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("A{$barisAwal}:A{$barisAkhir}")->getFont()->setBold(true);
        $sheet->getStyle("H{$barisAwal}:H{$barisAkhir}")->getFont()->setBold(true);
    }

    /** Perkiraan jumlah karakter per baris pada rentang kolom gabungan. */
    private function kapasitasKarakter(array $lebarKolom, string $dari, string $sampai): int
    {
        $total = 0;

        for ($i = Coordinate::columnIndexFromString($dari); $i <= Coordinate::columnIndexFromString($sampai); $i++) {
            $total += $lebarKolom[Coordinate::stringFromColumnIndex($i)] ?? 9;
        }

        return max(1, (int) floor($total * 0.9));
    }
}
