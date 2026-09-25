import { ReportHeader, ReportSignature } from '@/components/reports/ReportHeader'
import type { MonthlyReport } from '@/types'
import { angka, rentangTanggal } from '@/utils/format'
import { Fragment } from 'react'

function jadwalSubtotal(nilai: unknown): { period_id: number; bobot: number }[] {
  return Array.isArray(nilai) ? (nilai as { period_id: number; bobot: number }[]) : []
}

/** Laporan bulanan sesuai struktur laporan-bulanan.png. */
export function MonthlyReportTable({ data }: { data: MonthlyReport }) {
  const kolom = data.kolom_periode
  const jumlahKolom = 7 + kolom.length + 1

  // Kelompokkan kolom minggu ke dalam bulan (BULAN I, BULAN II, ...).
  const bulan = kolom.reduce<{ bulan_ke_romawi: string; jumlah: number }[]>((hasil, item) => {
    const terakhir = hasil[hasil.length - 1]

    if (terakhir && terakhir.bulan_ke_romawi === item.bulan_ke_romawi) {
      terakhir.jumlah += 1
    } else {
      hasil.push({ bulan_ke_romawi: item.bulan_ke_romawi, jumlah: 1 })
    }

    return hasil
  }, [])

  return (
    <div className="bg-white p-4 sm:p-6">
      <ReportHeader
        judul="LAPORAN BULANAN"
        header={data.header}
        kanan={[
          { label: 'Bulan Ke', nilai: data.periode.bulan_ke_romawi },
          { label: 'Periode', nilai: rentangTanggal(data.periode.tanggal_mulai, data.periode.tanggal_selesai) },
          { label: 'Kontraktor Pelaksana', nilai: data.header.kontraktor_pelaksana ?? '-' },
          { label: 'Konsultan Pengawas', nilai: data.header.konsultan_pengawas ?? '-' },
        ]}
      />

      <div className="app-scroll-x">
        <table className="report-table min-w-max">
          <thead>
            <tr>
              <th rowSpan={3}>NO.</th>
              <th rowSpan={3} className="min-w-64">
                Uraian
              </th>
              <th rowSpan={3}>Satuan</th>
              <th rowSpan={3}>Volume</th>
              <th rowSpan={3}>
                Harga Satuan
                <br />
                (Rp.)
              </th>
              <th rowSpan={3}>
                Harga Pekerjaan
                <br />
                (Rp.)
              </th>
              <th rowSpan={3}>
                BOBOT
                <br />
                (%)
              </th>
              <th colSpan={kolom.length}>
                JANGKA WAKTU PELAKSANAAN {data.header.jangka_waktu_hari ?? '-'} HARI KALENDER
              </th>
              <th rowSpan={3}>
                KETERANGAN
                <br />%
              </th>
            </tr>
            <tr>
              {bulan.map((item) => (
                <th key={item.bulan_ke_romawi} colSpan={item.jumlah}>
                  BULAN {item.bulan_ke_romawi}
                </th>
              ))}
            </tr>
            <tr>
              {kolom.map((item) => (
                <th key={item.period_id} className="min-w-20">
                  {item.minggu_ke_romawi}
                </th>
              ))}
            </tr>
          </thead>

          <tbody>
            {data.kategori.map((kategori) => (
              <Fragment key={kategori.kode}>
                <tr className="font-semibold">
                  <td className="text-center">{kategori.kode}</td>
                  <td colSpan={jumlahKolom - 1}>{kategori.nama}</td>
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
                    {item.jadwal.map((sel) => (
                      <td key={sel.period_id} className="num">
                        {sel.bobot > 0 ? angka(sel.bobot, 2) : ''}
                      </td>
                    ))}
                    <td className="num">{angka(item.keterangan_persen, 0)}%</td>
                  </tr>
                ))}

                <tr className="bg-surface font-semibold">
                  <td />
                  <td colSpan={4}>JUMLAH {kategori.nama}</td>
                  <td className="num">{angka(Number(kategori.subtotal.harga_pekerjaan ?? 0), 2)}</td>
                  <td className="num">{angka(Number(kategori.subtotal.bobot ?? 0), 2)}</td>
                  {jadwalSubtotal(kategori.subtotal.jadwal).map((sel) => (
                    <td key={sel.period_id} className="num">
                      {sel.bobot > 0 ? angka(sel.bobot, 2) : ''}
                    </td>
                  ))}
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
              {jadwalSubtotal(data.total.jadwal).map((sel) => (
                <td key={sel.period_id} className="num">
                  {sel.bobot > 0 ? angka(sel.bobot, 2) : ''}
                </td>
              ))}
              <td />
            </tr>

            <tr>
              <td rowSpan={2} colSpan={5} className="text-center font-semibold">
                RENCANA
              </td>
              <td colSpan={2} className="font-semibold">
                MINGGUAN (%)
              </td>
              {data.rekap_periode.map((item) => (
                <td key={item.period_id} className="num">
                  {angka(item.rencana_mingguan, 3)}
                </td>
              ))}
              <td />
            </tr>
            <tr>
              <td colSpan={2} className="font-semibold">
                KOMULATIF (%)
              </td>
              {data.rekap_periode.map((item) => (
                <td key={item.period_id} className="num">
                  {angka(item.rencana_kumulatif, 3)}
                </td>
              ))}
              <td />
            </tr>

            <tr>
              <td rowSpan={2} colSpan={5} className="text-center font-semibold">
                REALISASI
              </td>
              <td colSpan={2} className="font-semibold">
                MINGGUAN (%)
              </td>
              {data.rekap_periode.map((item) => (
                <td key={item.period_id} className="num">
                  {item.realisasi_mingguan === null ? '' : angka(item.realisasi_mingguan, 3)}
                </td>
              ))}
              <td />
            </tr>
            <tr>
              <td colSpan={2} className="font-semibold">
                KOMULATIF (%)
              </td>
              {data.rekap_periode.map((item) => (
                <td key={item.period_id} className="num">
                  {item.realisasi_kumulatif === null ? '' : angka(item.realisasi_kumulatif, 3)}
                </td>
              ))}
              <td />
            </tr>

            <tr>
              <td colSpan={7} className="text-center font-semibold">
                DEVIASI
              </td>
              {data.rekap_periode.map((item) => (
                <td
                  key={item.period_id}
                  className={`num font-semibold ${item.deviasi !== null && item.deviasi < 0 ? 'text-danger' : 'text-success'}`}
                >
                  {item.deviasi === null ? '' : angka(item.deviasi, 3)}
                </td>
              ))}
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      <dl className="mt-4 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div className="app-card p-3">
          <dt className="text-muted">Realisasi bulan lalu</dt>
          <dd className="text-base font-semibold text-ink">{angka(data.rekap.realisasi_bulan_lalu, 2)}%</dd>
        </div>
        <div className="app-card p-3">
          <dt className="text-muted">Realisasi bulan ini</dt>
          <dd className="text-base font-semibold text-ink">{angka(data.rekap.realisasi_bulan_ini, 2)}%</dd>
        </div>
        <div className="app-card p-3">
          <dt className="text-muted">Realisasi s/d bulan ini</dt>
          <dd className="text-base font-semibold text-ink">{angka(data.rekap.realisasi_sd_bulan_ini, 2)}%</dd>
        </div>
        <div className="app-card p-3">
          <dt className="text-muted">Rencana s/d bulan ini</dt>
          <dd className="text-base font-semibold text-ink">{angka(data.rekap.rencana_sd_bulan_ini, 2)}%</dd>
        </div>
      </dl>

      <ReportSignature header={data.header} tanggalDokumen={data.periode.tanggal_selesai} />
    </div>
  )
}
