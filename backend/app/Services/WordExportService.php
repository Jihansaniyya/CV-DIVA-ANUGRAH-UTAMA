<?php

namespace App\Services;

use App\Exports\ReportFormatter;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

/**
 * Membentuk dokumen Word (.docx) untuk laporan harian, mingguan, dan bulanan.
 * Struktur tabel mengikuti format laporan resmi, bukan tangkapan layar.
 */
class WordExportService
{
    private const GAYA_TABEL = [
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 50,
    ];

    private const HEADER_CELL = ['bgColor' => 'E2E8F0', 'valign' => 'center'];

    public function daily(array $data, string $path): string
    {
        $word = $this->newDocument();
        $section = $word->addSection(['orientation' => 'landscape']);

        $this->judul($section, 'LAPORAN HARIAN');
        $this->identitas($section, $data['header'], [
            'Periode' => ReportFormatter::rentangTanggal($data['periode']['dari'], $data['periode']['sampai']),
        ]);

        $tabel = $section->addTable(self::GAYA_TABEL);
        $this->barisHeader($tabel, ['Tanggal', 'Pelapor', 'Uraian Pekerjaan', 'Satuan', 'Volume Realisasi', 'Bobot Realisasi (%)', 'Keterangan', 'Material', 'Kendala', 'Tindak Lanjut']);

        foreach ($data['laporan'] as $laporan) {
            $material = collect($laporan['material'])->map(fn ($m) => $m['nama_material'].' '.$m['jumlah'].' '.($m['satuan'] ?? ''))->implode('; ');
            $kendala = collect($laporan['kendala'])->map(fn ($k) => $k['deskripsi'])->implode('; ');
            $tindak = collect($laporan['kendala'])->map(fn ($k) => $k['tindak_lanjut'])->filter()->implode('; ');
            $detail = $laporan['detail'] ?: [[]];

            foreach ($detail as $index => $d) {
                $this->barisData($tabel, [
                    $index === 0 ? ReportFormatter::tanggal($laporan['tanggal_laporan']) : '',
                    $index === 0 ? $laporan['pelapor'] : '',
                    $d['uraian_pekerjaan'] ?? '-',
                    $d['satuan'] ?? '',
                    isset($d['volume_realisasi']) ? $this->angka($d['volume_realisasi'], 2) : '',
                    isset($d['bobot_realisasi']) ? $this->angka($d['bobot_realisasi'], 2) : '',
                    $d['keterangan'] ?? $laporan['keterangan'] ?? '',
                    $index === 0 ? $material : '',
                    $index === 0 ? $kendala : '',
                    $index === 0 ? $tindak : '',
                ]);
            }
        }

        $section->addTextBreak(1);
        $section->addText('Total bobot realisasi: '.$this->angka($data['ringkasan']['bobot_realisasi'], 2).' %', ['bold' => true]);

        $this->tandaTangan($section, $data['header']);

        return $this->simpan($word, $path);
    }

    public function weekly(array $data, string $path): string
    {
        $word = $this->newDocument();
        $section = $word->addSection(['orientation' => 'landscape']);
        $h = $data['header'];
        $p = $data['periode'];

        $this->judul($section, 'LAPORAN MINGGUAN');
        $this->identitas($section, $h, [
            'Bulan Ke' => ReportFormatter::romawi((int) $p['bulan_ke']),
            'Minggu Ke' => ReportFormatter::romawi((int) $p['minggu_ke']),
            'Periode' => ReportFormatter::rentangTanggal($p['tanggal_mulai'], $p['tanggal_selesai']),
            'Kontraktor Pelaksana' => $h['kontraktor_pelaksana'],
            'Konsultan Pengawas' => $h['konsultan_pengawas'],
        ]);

        $tabel = $section->addTable(self::GAYA_TABEL);
        $this->barisHeader($tabel, [
            'NO.', 'Uraian', 'Satuan', 'Volume', 'Harga Satuan (Rp.)', 'Harga Pekerjaan (Rp.)', 'Bobot (%)',
            'Realisasi Minggu Lalu (Vol.)', 'Realisasi Minggu Lalu (Bobot %)',
            'Realisasi Minggu Ini (Vol.)', 'Realisasi Minggu Ini (Bobot %)',
            'Realisasi s/d Minggu Ini (Vol.)', 'Realisasi s/d Minggu Ini (Bobot %)', 'Ket. %',
        ]);

        foreach ($data['kategori'] as $kategori) {
            $this->barisKategori($tabel, 14, $kategori['kode'].'. '.$kategori['nama']);

            foreach ($kategori['items'] as $item) {
                $this->barisData($tabel, [
                    (string) $item['no'],
                    $item['uraian'],
                    $item['satuan'] ?? '',
                    $this->angka($item['volume'], 2),
                    $item['harga_satuan'] !== null ? $this->angka($item['harga_satuan'], 2) : '',
                    $item['harga_pekerjaan'] !== null ? $this->angka($item['harga_pekerjaan'], 2) : '',
                    $this->angka($item['bobot'], 2),
                    $this->angka($item['realisasi_lalu']['volume'], 2),
                    $this->angka($item['realisasi_lalu']['bobot'], 2),
                    $this->angka($item['realisasi_ini']['volume'], 2),
                    $this->angka($item['realisasi_ini']['bobot'], 2),
                    $this->angka($item['realisasi_sd']['volume'], 2),
                    $this->angka($item['realisasi_sd']['bobot'], 2),
                    $this->angka($item['keterangan_persen'], 2).'%',
                ]);
            }

            $s = $kategori['subtotal'];
            $this->barisData($tabel, [
                '', 'JUMLAH '.$kategori['nama'], '', '', '',
                $this->angka($s['harga_pekerjaan'], 2), $this->angka($s['bobot'], 2), '',
                $this->angka($s['realisasi_lalu']['bobot'] ?? 0, 2), '',
                $this->angka($s['realisasi_ini']['bobot'] ?? 0, 2), '',
                $this->angka($s['realisasi_sd']['bobot'] ?? 0, 2), '',
            ], true);
        }

        $t = $data['total'];
        $this->barisData($tabel, [
            '', 'JUMLAH', '', '', '',
            $this->angka($t['harga_pekerjaan'], 2), $this->angka($t['bobot'], 2), '',
            $this->angka($t['realisasi_lalu']['bobot'] ?? 0, 2), '',
            $this->angka($t['realisasi_ini']['bobot'] ?? 0, 2), '',
            $this->angka($t['realisasi_sd']['bobot'] ?? 0, 2), '',
        ], true);

        $section->addTextBreak(1);
        $rekap = $section->addTable(self::GAYA_TABEL);
        $r = $data['rekap'];
        $this->barisDuaKolom($rekap, 'REALISASI MINGGU LALU', $this->angka($r['realisasi_minggu_lalu'], 2).' %');
        $this->barisDuaKolom($rekap, 'REALISASI MINGGU INI', $this->angka($r['realisasi_minggu_ini'], 2).' %');
        $this->barisDuaKolom($rekap, 'REALISASI SAMPAI DENGAN MINGGU INI', $this->angka($r['realisasi_sd_minggu_ini'], 2).' %');
        $this->barisDuaKolom($rekap, 'RENCANA KOMULATIF SAMPAI DENGAN MINGGU INI', $this->angka($r['rencana_kumulatif_sd_minggu_ini'], 2).' %');
        $this->barisDuaKolom($rekap, 'DEVIASI', $this->angka($r['deviasi'], 2).' %');

        $this->tandaTangan($section, $h);

        return $this->simpan($word, $path);
    }

    public function monthly(array $data, string $path): string
    {
        $word = $this->newDocument();
        $section = $word->addSection(['orientation' => 'landscape']);
        $h = $data['header'];
        $p = $data['periode'];
        $kolomPeriode = $data['kolom_periode'];
        $jumlahKolom = 7 + count($kolomPeriode) + 1;

        $this->judul($section, 'LAPORAN BULANAN');
        $this->identitas($section, $h, [
            'Bulan Ke' => ReportFormatter::romawi((int) $p['bulan_ke']),
            'Periode' => ReportFormatter::rentangTanggal($p['tanggal_mulai'], $p['tanggal_selesai']),
            'Kontraktor Pelaksana' => $h['kontraktor_pelaksana'],
            'Konsultan Pengawas' => $h['konsultan_pengawas'],
        ]);

        $tabel = $section->addTable(self::GAYA_TABEL);
        $header = ['NO.', 'Uraian', 'Satuan', 'Volume', 'Harga Satuan (Rp.)', 'Harga Pekerjaan (Rp.)', 'Bobot (%)'];

        foreach ($kolomPeriode as $kolom) {
            $header[] = 'M-'.$kolom['minggu_ke_romawi'];
        }

        $header[] = 'Ket. %';
        $this->barisHeader($tabel, $header);

        foreach ($data['kategori'] as $kategori) {
            $this->barisKategori($tabel, $jumlahKolom, $kategori['kode'].'. '.$kategori['nama']);

            foreach ($kategori['items'] as $item) {
                $baris = [
                    (string) $item['no'], $item['uraian'], $item['satuan'] ?? '',
                    $this->angka($item['volume'], 2),
                    $item['harga_satuan'] !== null ? $this->angka($item['harga_satuan'], 2) : '',
                    $item['harga_pekerjaan'] !== null ? $this->angka($item['harga_pekerjaan'], 2) : '',
                    $this->angka($item['bobot'], 2),
                ];

                foreach ($item['jadwal'] as $jadwal) {
                    $baris[] = $jadwal['bobot'] > 0 ? $this->angka($jadwal['bobot'], 2) : '';
                }

                $baris[] = $this->angka($item['keterangan_persen'], 2).'%';
                $this->barisData($tabel, $baris);
            }

            $s = $kategori['subtotal'];
            $baris = ['', 'JUMLAH '.$kategori['nama'], '', '', '', $this->angka($s['harga_pekerjaan'], 2), $this->angka($s['bobot'], 2)];

            foreach ($s['jadwal'] ?? [] as $jadwal) {
                $baris[] = $jadwal['bobot'] > 0 ? $this->angka($jadwal['bobot'], 2) : '';
            }

            $baris[] = '';
            $this->barisData($tabel, $baris, true);
        }

        $t = $data['total'];
        $baris = ['', 'JUMLAH', '', '', '', $this->angka($t['harga_pekerjaan'], 2), $this->angka($t['bobot'], 2)];
        foreach ($t['jadwal'] ?? [] as $jadwal) {
            $baris[] = $jadwal['bobot'] > 0 ? $this->angka($jadwal['bobot'], 2) : '';
        }
        $baris[] = '';
        $this->barisData($tabel, $baris, true);

        $rekap = collect($data['rekap_periode']);
        $this->barisRekap($tabel, 'RENCANA Mingguan (%)', $rekap->pluck('rencana_mingguan')->all());
        $this->barisRekap($tabel, 'RENCANA Komulatif (%)', $rekap->pluck('rencana_kumulatif')->all());
        $this->barisRekap($tabel, 'REALISASI Mingguan (%)', $rekap->pluck('realisasi_mingguan')->all());
        $this->barisRekap($tabel, 'REALISASI Komulatif (%)', $rekap->pluck('realisasi_kumulatif')->all());
        $this->barisRekap($tabel, 'DEVIASI', $rekap->pluck('deviasi')->all());

        $section->addTextBreak(1);
        $ringkas = $section->addTable(self::GAYA_TABEL);
        $this->barisDuaKolom($ringkas, 'Realisasi bulan lalu', $this->angka($data['rekap']['realisasi_bulan_lalu'], 2).' %');
        $this->barisDuaKolom($ringkas, 'Realisasi bulan ini', $this->angka($data['rekap']['realisasi_bulan_ini'], 2).' %');
        $this->barisDuaKolom($ringkas, 'Realisasi s/d bulan ini', $this->angka($data['rekap']['realisasi_sd_bulan_ini'], 2).' %');
        $this->barisDuaKolom($ringkas, 'Rencana s/d bulan ini', $this->angka($data['rekap']['rencana_sd_bulan_ini'], 2).' %');
        $this->barisDuaKolom($ringkas, 'Deviasi', $this->angka($data['rekap']['deviasi'], 2).' %');

        $this->tandaTangan($section, $h);

        return $this->simpan($word, $path);
    }

    private function newDocument(): PhpWord
    {
        $word = new PhpWord;
        $word->getSettings()->setThemeFontLang(new Language(Language::EN_US));
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(9);

        return $word;
    }

    private function judul(Section $section, string $teks): void
    {
        $section->addText($teks, ['bold' => true, 'size' => 14, 'underline' => 'single'], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
    }

    /** @param array<string,string|null> $kanan */
    private function identitas(Section $section, array $h, array $kanan = []): void
    {
        $kiri = [
            'PEKERJAAN' => $h['nama_proyek'],
            'LOKASI' => $h['lokasi'],
            'SUMBER DANA' => $h['sumber_dana'],
            'TAHUN ANGGARAN' => (string) $h['tahun_anggaran'],
            'NO. SPK' => $h['nomor_spk'],
            'TANGGAL SPK' => ReportFormatter::tanggal($h['tanggal_spk']),
        ];

        $tabel = $section->addTable(['cellMargin' => 20]);
        $baris = max(count($kiri), count($kanan));
        $kiriKeys = array_keys($kiri);
        $kananKeys = array_keys($kanan);

        for ($i = 0; $i < $baris; $i++) {
            $tabel->addRow();
            $labelKiri = $kiriKeys[$i] ?? null;
            $tabel->addCell(2200)->addText($labelKiri ? $labelKiri : '', ['bold' => true]);
            $tabel->addCell(200)->addText($labelKiri ? ':' : '');
            $tabel->addCell(5200)->addText($labelKiri ? (string) ($kiri[$labelKiri] ?? '-') : '');

            $labelKanan = $kananKeys[$i] ?? null;
            $tabel->addCell(2600)->addText($labelKanan ? $labelKanan : '', ['bold' => true]);
            $tabel->addCell(200)->addText($labelKanan ? ':' : '');
            $tabel->addCell(3800)->addText($labelKanan ? (string) ($kanan[$labelKanan] ?? '-') : '');
        }

        $section->addTextBreak(1);
    }

    /** @param array<int,string> $kolom */
    private function barisHeader($tabel, array $kolom): void
    {
        $tabel->addRow();

        foreach ($kolom as $teks) {
            $tabel->addCell(null, self::HEADER_CELL)->addText($teks, ['bold' => true], ['alignment' => Jc::CENTER]);
        }
    }

    /** @param array<int,string> $kolom */
    private function barisData($tabel, array $kolom, bool $tebal = false): void
    {
        $tabel->addRow();

        foreach ($kolom as $index => $teks) {
            $align = $index >= 3 ? Jc::END : Jc::START;
            $tabel->addCell()->addText(htmlspecialchars((string) $teks), ['bold' => $tebal], ['alignment' => $index === 1 ? Jc::START : $align]);
        }
    }

    private function barisKategori($tabel, int $jumlahKolom, string $teks): void
    {
        $tabel->addRow();
        $tabel->addCell(null, ['gridSpan' => $jumlahKolom, 'bgColor' => 'F1F5F9'])
            ->addText(htmlspecialchars($teks), ['bold' => true]);
    }

    /** @param array<int,float|null> $nilai */
    private function barisRekap($tabel, string $judul, array $nilai): void
    {
        $tabel->addRow();
        $tabel->addCell(null, ['gridSpan' => 7, 'bgColor' => 'F8FAFC'])->addText($judul, ['bold' => true]);

        foreach ($nilai as $v) {
            $tabel->addCell()->addText($v === null ? '' : $this->angka((float) $v, 2), ['bold' => true], ['alignment' => Jc::END]);
        }

        $tabel->addCell()->addText('');
    }

    private function barisDuaKolom($tabel, string $label, string $nilai): void
    {
        $tabel->addRow();
        $tabel->addCell(7000)->addText($label, ['bold' => true]);
        $tabel->addCell(2500)->addText($nilai, ['bold' => true], ['alignment' => Jc::END]);
    }

    private function tandaTangan(Section $section, array $h): void
    {
        $section->addTextBreak(2);
        $tabel = $section->addTable(['cellMargin' => 40]);
        $tabel->addRow();
        $tabel->addCell(6500)->addText('Diperiksa,', null, ['alignment' => Jc::CENTER]);
        $tabel->addCell(6500)->addText('Dibuat Oleh', null, ['alignment' => Jc::CENTER]);

        $tabel->addRow();
        $tabel->addCell(6500)->addText('Konsultan Pengawas', null, ['alignment' => Jc::CENTER]);
        $tabel->addCell(6500)->addText('Kontraktor Pelaksana', null, ['alignment' => Jc::CENTER]);

        $tabel->addRow();
        $tabel->addCell(6500)->addText((string) ($h['konsultan_pengawas'] ?? '-'), null, ['alignment' => Jc::CENTER]);
        $tabel->addCell(6500)->addText((string) ($h['kontraktor_pelaksana'] ?? '-'), null, ['alignment' => Jc::CENTER]);

        $section->addTextBreak(3);

        $ttd = $section->addTable(['cellMargin' => 40]);
        $ttd->addRow();
        $ttd->addCell(6500)->addText((string) ($h['nama_site_engineer'] ?? '-'), ['bold' => true, 'underline' => 'single'], ['alignment' => Jc::CENTER]);
        $ttd->addCell(6500)->addText((string) ($h['nama_pelaksana_lapangan'] ?? '-'), ['bold' => true, 'underline' => 'single'], ['alignment' => Jc::CENTER]);

        $ttd->addRow();
        $ttd->addCell(6500)->addText('Site Engineer', null, ['alignment' => Jc::CENTER]);
        $ttd->addCell(6500)->addText('Pelaksana Lapangan', null, ['alignment' => Jc::CENTER]);
    }

    private function angka(float|int|null $nilai, int $desimal): string
    {
        return number_format((float) $nilai, $desimal, ',', '.');
    }

    private function simpan(PhpWord $word, string $path): string
    {
        $folder = dirname($path);

        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $word->save($path, 'Word2007');

        return $path;
    }
}
