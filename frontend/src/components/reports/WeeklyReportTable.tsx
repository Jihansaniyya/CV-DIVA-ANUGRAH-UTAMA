import { ReportHeader, ReportSignature } from '@/components/reports/ReportHeader'
import type { WeeklyReport } from '@/types'
import { angka, rentangTanggal } from '@/utils/format'
import { Fragment } from 'react'

function bobot(nilai: unknown): number {
  if (typeof nilai === 'object' && nilai !== null && 'bobot' in nilai) {
    return Number((nilai as { bobot: number }).bobot)
  }

  return 0
}

/** Laporan mingguan sesuai struktur laporan-mingguan.png. */
export function WeeklyReportTable({ data }: { data: WeeklyReport }) {
  return (
    <div className="bg-white p-4 sm:p-6">
      <ReportHeader
        judul="LAPORAN MINGGUAN"
        header={data.header}
        kanan={[
          { label: 'Bulan Ke', nilai: data.periode.bulan_ke_romawi },
          { label: 'Minggu Ke', nilai: data.periode.minggu_ke_romawi },
          { label: 'Periode', nilai: rentangTanggal(data.periode.tanggal_mulai, data.periode.tanggal_selesai) },
          { label: 'Kontraktor Pelaksana', nilai: data.header.kontraktor_pelaksana ?? '-' },
          { label: 'Konsultan Pengawas', nilai: data.header.konsultan_pengawas ?? '-' },
        ]}
      />

      <div className="app-scroll-x">
        <table className="report-table min-w-max">
          <thead>
            <tr>
              <th rowSpan={2}>NO.</th>
              <th rowSpan={2} className="min-w-64">
                Uraian
              </th>
              <th rowSpan={2}>Satuan</th>
              <th rowSpan={2}>Volume</th>
              <th rowSpan={2}>
                Harga Satuan
                <br />
                (Rp.)
              </th>
              <th rowSpan={2}>
                Harga Pekerjaan
                <br />
                (Rp.)
              </th>
              <th rowSpan={2}>
                BOBOT
                <br />
                (%)
              </th>
              <th colSpan={2}>REALISASI MINGGU LALU</th>
              <th colSpan={2}>REALISASI MINGGU INI</th>
              <th colSpan={2}>REALISASI S/D MINGGU INI</th>
              <th rowSpan={2}>
                KETERANGAN
                <br />%
              </th>
            </tr>
            <tr>
              <th>VOLUME</th>
              <th>BOBOT (%)</th>
              <th>VOLUME</th>
              <th>BOBOT (%)</th>
              <th>VOLUME</th>
              <th>BOBOT (%)</th>
            </tr>
          </thead>

          <tbody>
            {data.kategori.map((kategori) => (
              <Fragment key={kategori.kode}>
                <tr className="font-semibold">
                  <td className="text-center">{kategori.kode}</td>
                  <td colSpan={12}>{kategori.nama}</td>
                </tr>

                {kategori.items.map((item) => (
                  <tr key={item.work_item_id}>
                    <td className="text-center">{item.no}</td>
                    <td>{item.uraian}</td>
                    <td className="text-center">{item.satuan}</td>
                    <td className="num">{angka(item.volume, 2)}</td>
                    <td className="num">{item.harga_satuan === null ? '-' : angka(item.harga_satuan, 2)}</td>
                    <td className="num">{item.harga_pekerjaan === null ? '-' : angka(item.harga_pekerjaan, 2)}</td>
                    <td className="num">{angka(item.bobot, 2)}</td>
                    <td className="num">{item.realisasi_lalu.volume ? angka(item.realisasi_lalu.volume, 2) : '-'}</td>
                    <td className="num">{item.realisasi_lalu.bobot ? angka(item.realisasi_lalu.bobot, 2) : '-'}</td>
                    <td className="num">{item.realisasi_ini.volume ? angka(item.realisasi_ini.volume, 2) : '-'}</td>
                    <td className="num">{item.realisasi_ini.bobot ? angka(item.realisasi_ini.bobot, 2) : '-'}</td>
                    <td className="num">{item.realisasi_sd.volume ? angka(item.realisasi_sd.volume, 2) : '-'}</td>
                    <td className="num">{item.realisasi_sd.bobot ? angka(item.realisasi_sd.bobot, 2) : '-'}</td>
                    <td className="num">{angka(item.keterangan_persen, 0)}%</td>
                  </tr>
                ))}

                <tr className="bg-surface font-semibold">
                  <td />
                  <td colSpan={4}>JUMLAH {kategori.nama}</td>
                  <td className="num">{angka(Number(kategori.subtotal.harga_pekerjaan ?? 0), 2)}</td>
                  <td className="num">{angka(Number(kategori.subtotal.bobot ?? 0), 2)}</td>
                  <td />
                  <td className="num">{angka(bobot(kategori.subtotal.realisasi_lalu), 2)}</td>
                  <td />
                  <td className="num">{angka(bobot(kategori.subtotal.realisasi_ini), 2)}</td>
                  <td />
                  <td className="num">{angka(bobot(kategori.subtotal.realisasi_sd), 2)}</td>
                  <td />
                </tr>
              </Fragment>
            ))}
          </tbody>

          <tfoot>
            <tr className="bg-surface font-bold">
              <td />
              <td colSpan={4} className="text-center">
                JUMLAH
              </td>
              <td className="num">{angka(Number(data.total.harga_pekerjaan ?? 0), 2)}</td>
              <td className="num">{angka(Number(data.total.bobot ?? 0), 2)}</td>
              <td />
              <td className="num">{angka(bobot(data.total.realisasi_lalu), 2)}</td>
              <td />
              <td className="num">{angka(bobot(data.total.realisasi_ini), 2)}</td>
              <td />
              <td className="num">{angka(bobot(data.total.realisasi_sd), 2)}</td>
              <td />
            </tr>
            <tr>
              <td colSpan={12} className="text-right font-semibold">
                REALISASI SAMPAI DENGAN MINGGU INI
              </td>
              <td className="num font-semibold">{angka(data.rekap.realisasi_sd_minggu_ini, 2)}</td>
              <td />
            </tr>
            <tr>
              <td colSpan={12} className="text-right font-semibold">
                RENCANA KOMULATIF SAMPAI DENGAN MINGGU INI
              </td>
              <td className="num font-semibold">{angka(data.rekap.rencana_kumulatif_sd_minggu_ini, 2)}</td>
              <td />
            </tr>
            <tr>
              <td colSpan={12} className="text-right font-semibold">
                DEVIASI
              </td>
              <td className={`num font-semibold ${data.rekap.deviasi < 0 ? 'text-danger' : 'text-success'}`}>
                {angka(data.rekap.deviasi, 2)}
              </td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      <ReportSignature header={data.header} tanggalDokumen={data.periode.tanggal_selesai} />
    </div>
  )
}
