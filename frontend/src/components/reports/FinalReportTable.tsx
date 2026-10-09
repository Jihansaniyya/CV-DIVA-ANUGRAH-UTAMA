import { ReportHeader, ReportSignature } from '@/components/reports/ReportHeader'
import { ReportSummary } from '@/components/reports/ReportSummary'
import type { FinalReport, FinalReportProgressRow } from '@/types'
import { angka, rentangTanggal, rupiah, tanggal } from '@/utils/format'
import { Fragment } from 'react'

/** Nilai bobot dari kolom subtotal/total (`{ bobot }`) atau angka langsung. */
function bobotDari(nilai: unknown): number {
  if (typeof nilai === 'number') return nilai
  if (nilai && typeof nilai === 'object' && 'bobot' in nilai) return Number((nilai as { bobot: number }).bobot)

  return 0
}

function JudulBagian({ judul }: { judul: string }) {
  return <h3 className="mt-6 mb-2 text-sm font-bold text-ink">{judul}</h3>
}

/** Daftar sheet file Excel beserta jumlah data, ditampilkan sebelum laporan diunduh. */
function IsiFileExcel({ data }: { data: FinalReport }) {
  const ringkasan = data.ringkasan
  if (!ringkasan) return null

  const sheet = [
    { nama: 'Informasi Proyek', isi: 'Identitas, pihak terkait, ringkasan progres' },
    { nama: 'Rekap Mingguan', isi: `${data.rekap_mingguan.length} minggu` },
    { nama: 'Rekap Bulanan', isi: `${data.rekap_bulanan.length} bulan` },
    { nama: 'Rencana & Realisasi', isi: `${ringkasan.jumlah_pekerjaan} item pekerjaan` },
    { nama: 'Kurva S', isi: `${data.kurva_s.titik.length} periode + grafik` },
    { nama: 'Dokumentasi', isi: ringkasan.jumlah_foto > 0 ? `${ringkasan.jumlah_foto} foto` : 'Belum ada foto' },
    { nama: 'Rekapitulasi Akhir', isi: `${ringkasan.jumlah_kendala} kendala, kesimpulan, pengesahan` },
  ]

  return (
    <div className="no-print mt-4 rounded-lg border border-line bg-surface/60 p-3 sm:p-4">
      <p className="text-xs font-semibold text-ink">Isi file Excel Laporan Akhir</p>
      <ol className="mt-2 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
        {sheet.map((item, indeks) => (
          <li key={item.nama} className="rounded-md border border-line bg-white px-3 py-2">
            <span className="font-medium text-ink">
              {indeks + 1}. {item.nama}
            </span>
            <span className="block text-muted">{item.isi}</span>
          </li>
        ))}
      </ol>
    </div>
  )
}

function TabelProgres({ judul, baris }: { judul: string; baris: ({ key: number; periode: string; tanggal: string } & FinalReportProgressRow)[] }) {
  return (
    <div className="report-section mt-3">
      <p className="mb-1 text-xs font-semibold text-ink">{judul}</p>
      <div className="app-scroll-x">
        <table className="report-table min-w-max">
          <thead>
            <tr>
              <th>NO.</th>
              <th className="min-w-40">Periode</th>
              <th className="min-w-48">Tanggal</th>
              <th>
                Rencana
                <br />
                (%)
              </th>
              <th>
                Rencana Kumulatif
                <br />
                (%)
              </th>
              <th>
                Realisasi Periode
                <br />
                (%)
              </th>
              <th>
                Realisasi Kumulatif
                <br />
                (%)
              </th>
              <th>
                Deviasi
                <br />
                (%)
              </th>
            </tr>
          </thead>
          <tbody>
            {baris.map((item, indeks) => (
              <tr key={item.key}>
                <td className="text-center">{indeks + 1}</td>
                <td>{item.periode}</td>
                <td>{item.tanggal}</td>
                <td className="num">{angka(item.rencana, 2)}</td>
                <td className="num">{angka(item.rencana_kumulatif, 2)}</td>
                <td className="num">{angka(item.realisasi, 2)}</td>
                <td className="num">{angka(item.realisasi_kumulatif, 2)}</td>
                <td className={`num font-semibold ${item.deviasi !== null && item.deviasi < -0.005 ? 'text-danger' : 'text-success'}`}>
                  {angka(item.deviasi, 2)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

/** Laporan akhir: rekap kondisi proyek sampai laporan progres terakhir. Dipanggil hanya bila `laporan_terakhir` tersedia. */
export function FinalReportTable({ data }: { data: FinalReport }) {
  const terakhir = data.laporan_terakhir
  const ringkasan = data.ringkasan

  if (!terakhir || !ringkasan || !data.total) return null

  const total = data.total
  const hargaTotal = Number(total.harga_pekerjaan ?? 0)

  return (
    <div className="bg-white p-4 sm:p-6">
      <ReportHeader
        judul="LAPORAN AKHIR"
        header={data.header}
        kanan={[
          { label: 'Kontraktor Pelaksana', nilai: data.header.kontraktor_pelaksana ?? '-' },
          { label: 'Konsultan Pengawas', nilai: data.header.konsultan_pengawas ?? '-' },
          { label: 'Jumlah Harga Pekerjaan', nilai: hargaTotal > 0 ? rupiah(hargaTotal) : '-' },
          { label: 'Periode Pelaksanaan', nilai: rentangTanggal(data.header.tanggal_mulai, data.header.tanggal_selesai) },
          { label: 'Status Proyek', nilai: data.status_proyek.label },
          { label: 'Laporan Progres Terakhir', nilai: tanggal(terakhir.tanggal_laporan) },
        ]}
      />

      <IsiFileExcel data={data} />

      <JudulBagian judul="REKAPITULASI PEKERJAAN" />
      <div className="app-scroll-x">
        <table className="report-table min-w-max">
          <thead>
            <tr>
              <th rowSpan={2}>NO.</th>
              <th rowSpan={2} className="min-w-64">
                Uraian Pekerjaan
              </th>
              <th rowSpan={2}>Satuan</th>
              <th rowSpan={2}>Volume</th>
              <th rowSpan={2}>
                Harga Satuan
                <br />
                (Rp.)
              </th>
              <th rowSpan={2}>
                Jumlah Harga
                <br />
                (Rp.)
              </th>
              <th rowSpan={2}>
                BOBOT
                <br />
                (%)
              </th>
              <th colSpan={2}>REALISASI S/D BULAN LALU</th>
              <th colSpan={2}>REALISASI BULAN TERAKHIR (BULAN {terakhir.bulan_ke_romawi})</th>
              <th colSpan={2}>REALISASI S/D AKHIR</th>
              <th rowSpan={2}>
                KETERANGAN
                <br />%
              </th>
            </tr>
            <tr>
              {[0, 1, 2].map((kolom) => (
                <Fragment key={kolom}>
                  <th>Volume</th>
                  <th>Bobot (%)</th>
                </Fragment>
              ))}
            </tr>
          </thead>

          <tbody>
            {data.kategori.map((kategori) => (
              <Fragment key={kategori.kode}>
                <tr className="font-semibold">
                  <td className="text-center">{kategori.kode}</td>
                  <td colSpan={13}>{kategori.nama}</td>
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
                    <td className="num">{angka(item.realisasi_bulan_lalu.volume, 2)}</td>
                    <td className="num">{angka(item.realisasi_bulan_lalu.bobot, 2)}</td>
                    <td className="num">{angka(item.realisasi_bulan_ini.volume, 2)}</td>
                    <td className="num">{angka(item.realisasi_bulan_ini.bobot, 2)}</td>
                    <td className="num">{angka(item.realisasi_sd_bulan_ini.volume, 2)}</td>
                    <td className="num">{angka(item.realisasi_sd_bulan_ini.bobot, 2)}</td>
                    <td className="num">{angka(item.keterangan_persen, 0)}%</td>
                  </tr>
                ))}

                <tr className="bg-surface font-semibold">
                  <td />
                  <td colSpan={4}>JUMLAH {kategori.nama}</td>
                  <td className="num">{angka(Number(kategori.subtotal.harga_pekerjaan ?? 0), 2)}</td>
                  <td className="num">{angka(bobotDari(kategori.subtotal.bobot), 2)}</td>
                  <td />
                  <td className="num">{angka(bobotDari(kategori.subtotal.realisasi_bulan_lalu), 2)}</td>
                  <td />
                  <td className="num">{angka(bobotDari(kategori.subtotal.realisasi_bulan_ini), 2)}</td>
                  <td />
                  <td className="num">{angka(bobotDari(kategori.subtotal.realisasi_sd_bulan_ini), 2)}</td>
                  <td />
                </tr>
              </Fragment>
            ))}
          </tbody>

          <tfoot>
            <tr className="bg-surface font-bold">
              <td />
              <td colSpan={4} className="text-center">
                JUMLAH TOTAL
              </td>
              <td className="num">{angka(hargaTotal, 2)}</td>
              <td className="num">{angka(bobotDari(total.bobot), 2)}</td>
              <td />
              <td className="num">{angka(bobotDari(total.realisasi_bulan_lalu), 2)}</td>
              <td />
              <td className="num">{angka(bobotDari(total.realisasi_bulan_ini), 2)}</td>
              <td />
              <td className="num">{angka(bobotDari(total.realisasi_sd_bulan_ini), 2)}</td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      <JudulBagian judul="REKAPITULASI PROGRES" />
      <TabelProgres
        judul="Rekap Progres per Bulan"
        baris={data.rekap_bulanan.map((item) => ({
          ...item,
          key: item.bulan_ke,
          periode: `Bulan ${item.bulan_ke_romawi}`,
          tanggal: rentangTanggal(item.tanggal_mulai, item.tanggal_selesai),
        }))}
      />
      <TabelProgres
        judul="Rekap Progres per Minggu"
        baris={data.rekap_mingguan.map((item) => ({
          key: item.period_id,
          periode: `${item.nama_periode} (Minggu ${item.minggu_ke_romawi})`,
          tanggal: rentangTanggal(item.tanggal_mulai, item.tanggal_selesai),
          rencana: item.rencana,
          rencana_kumulatif: item.rencana_kumulatif,
          realisasi: item.aktual,
          realisasi_kumulatif: item.aktual_kumulatif,
          deviasi: item.deviasi,
        }))}
      />

      <JudulBagian judul="REALISASI AKHIR" />
      <dl className="flex flex-col gap-1 text-xs">
        {[
          ['Periode laporan terakhir', `${terakhir.nama_periode} (Minggu ${terakhir.minggu_ke_romawi}, Bulan ${terakhir.bulan_ke_romawi}) · ${rentangTanggal(terakhir.tanggal_mulai, terakhir.tanggal_selesai)}`],
          ['Laporan progres terkirim', `${terakhir.jumlah_laporan} laporan`],
          ['Status proyek', data.status_proyek.label],
        ].map(([label, nilai]) => (
          <div key={label} className="flex gap-2">
            <dt className="w-44 shrink-0 font-semibold text-ink">{label}</dt>
            <dd className="flex-1 text-ink">: {nilai}</dd>
          </div>
        ))}
      </dl>
      <ReportSummary
        judul="REALISASI AKHIR"
        baris={[
          { label: 'Progres periode terakhir', nilai: ringkasan.realisasi_periode_terakhir },
          { label: 'Realisasi s/d bulan lalu', nilai: ringkasan.realisasi_bulan_lalu },
          { label: 'Realisasi bulan terakhir', nilai: ringkasan.realisasi_bulan_terakhir },
          { label: 'Realisasi s/d periode terakhir', nilai: ringkasan.realisasi_kumulatif },
          { label: 'Rencana s/d periode terakhir', nilai: ringkasan.rencana_kumulatif },
          { label: 'Deviasi', nilai: ringkasan.deviasi, deviasi: true },
        ]}
      />

      {data.kesimpulan.length > 0 && (
        <div className="report-section">
          <JudulBagian judul="KESIMPULAN" />
          <ol className="list-decimal space-y-1 pl-5 text-xs text-ink">
            {data.kesimpulan.map((kalimat) => (
              <li key={kalimat}>{kalimat}</li>
            ))}
          </ol>
        </div>
      )}

      <ReportSignature header={data.header} tanggalDokumen={terakhir.tanggal_laporan} />
    </div>
  )
}
