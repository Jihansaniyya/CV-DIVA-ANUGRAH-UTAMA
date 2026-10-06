import { ReportStatusBadge, StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState } from '@/components/ui/State'
import type { DashboardData, ReportStatus } from '@/types'
import { angka, hariIni, tanggal } from '@/utils/format'
import { CalendarDays, ClipboardList, Eye, FilePen, HardHat, Plus, SendHorizontal } from 'lucide-react'
import { Link } from 'react-router-dom'

const tautanUtama =
  'inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-dark'

const tautanKecil = 'inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline'

const tombolKecil =
  'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink transition-colors hover:border-primary hover:text-primary'

/** Beranda hanya menampilkan beberapa proyek terakhir diperbarui; daftar lengkap ada di halaman Proyek. */
const JUMLAH_PROYEK_BERANDA = 5

/** Kartu bagian bawah di layar lebar: mengisi sisa tinggi, isinya digulir di dalam kartu. */
const KARTU_PENUH = 'xl:flex xl:min-h-0 xl:flex-col'
const ISI_GULIR = 'xl:min-h-0 xl:flex-1 xl:overflow-y-auto'

export function QsDashboard({ data }: { data: DashboardData }) {
  // Backend sudah mengurutkan dari yang terakhir diperbarui.
  const proyek = (data.proyek_ditugaskan ?? []).slice(0, JUMLAH_PROYEK_BERANDA)
  const pekerjaan = data.pekerjaan_perlu_laporan ?? []
  const progresTerbaru = data.laporan_terbaru ?? []

  return (
    // Layar lebar: tinggi halaman dikunci setinggi layar (dikurangi navbar 4rem + padding 3rem) agar tidak perlu scroll;
    // kartu bagian bawah mengisi sisa ruang dan menggulir isinya sendiri bila datanya panjang.
    <div className="flex flex-col gap-4 xl:h-[calc(100dvh-7rem)] xl:gap-3">
      <div className="flex flex-wrap items-end justify-between gap-3 xl:shrink-0">
        <div>
          <h2 className="text-lg font-semibold text-ink">Beranda</h2>
          <p className="flex items-center gap-1.5 text-xs text-muted">
            <CalendarDays className="size-3.5" aria-hidden />
            Ringkasan pekerjaan per {tanggal(hariIni(), 'EEEE, dd MMMM yyyy')}
          </p>
        </div>
        <Link to="/progres/baru" className={tautanUtama}>
          <Plus className="size-4" aria-hidden />
          Tambah Progres
        </Link>
      </div>

      <section className="grid grid-cols-2 gap-3 sm:gap-4 xl:shrink-0 xl:grid-cols-4">
        <KpiCard compact label="Proyek Ditangani" value={data.kpi.total_proyek ?? 0} icon={ClipboardList} tone="navy" />
        <KpiCard compact label="Total Pekerjaan" value={data.kpi.total_pekerjaan ?? 0} icon={HardHat} tone="primary" />
        <KpiCard compact label="Progres Draf" value={data.kpi.laporan_draft ?? 0} icon={FilePen} tone="warning" hint="Belum dikirim" />
        <KpiCard compact label="Progres Terkirim" value={data.kpi.laporan_dikirim ?? 0} icon={SendHorizontal} tone="success" hint="Tercatat sebagai realisasi" />
      </section>

      <Card
        title="List Proyek"
        description={`${JUMLAH_PROYEK_BERANDA} proyek terakhir diperbarui yang Anda tangani.`}
        action={
          <Link to="/proyek" className={tautanKecil}>
            Lihat semua
          </Link>
        }
        className="xl:shrink-0"
        flush
      >
        {proyek.length === 0 ? (
          <EmptyState judul="Belum ada proyek" pesan="Hubungi Admin untuk penugasan proyek." />
        ) : (
          <ul className="divide-y divide-line">
            {proyek.map((item) => (
              <li key={item.id} className="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                <div className="min-w-0 flex-1">
                  <p className="text-sm leading-snug font-medium break-words text-ink">{item.nama_proyek}</p>
                  <p className="truncate text-[11px] text-muted">{item.lokasi}</p>
                </div>
                <div className="flex items-center gap-3">
                  <StatusBadge status={item.status} />
                  <ProgressBar nilai={item.progres_aktual} className="flex-1 sm:w-36 sm:flex-none" />
                  <Link to={`/proyek/${item.id}`} className={tombolKecil} aria-label={`Detail ${item.nama_proyek}`}>
                    <Eye className="size-3.5" aria-hidden />
                    Detail
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <section className="grid gap-4 lg:grid-cols-3 xl:min-h-0 xl:flex-1">
        <Card
          title="Pekerjaan Perlu Diperbarui"
          description="Pekerjaan dengan realisasi terendah pada proyek yang Anda tangani."
          className={`lg:col-span-2 ${KARTU_PENUH}`}
          bodyClassName={`p-0 ${ISI_GULIR}`}
        >
          {pekerjaan.length === 0 ? (
            <EmptyState judul="Semua pekerjaan sudah selesai" pesan="Tidak ada pekerjaan yang perlu diperbarui." />
          ) : (
            <ul className="divide-y divide-line">
              {pekerjaan.map((item) => (
                <li key={item.work_item_id} className="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:px-5">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-ink">{item.uraian_pekerjaan}</p>
                    <p className="truncate text-[11px] text-muted">{item.nama_proyek}</p>
                    <div className="mt-2 flex items-center gap-3">
                      <ProgressBar nilai={item.persentase} className="flex-1" />
                      <span className="shrink-0 text-[11px] whitespace-nowrap text-muted">
                        Sisa {angka(item.sisa_volume)} {item.satuan}
                      </span>
                    </div>
                  </div>
                  <Link
                    to={`/progres/baru?project_id=${item.project_id}&work_item_id=${item.work_item_id}`}
                    className={tombolKecil}
                  >
                    <Plus className="size-3.5" aria-hidden />
                    Tambah Progres
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card
          title="Progres Terbaru"
          action={
            <Link to="/progres" className={tautanKecil}>
              Lihat semua
            </Link>
          }
          className={KARTU_PENUH}
          bodyClassName={`p-0 ${ISI_GULIR}`}
        >
          {progresTerbaru.length === 0 ? (
            <EmptyState judul="Belum ada progres" pesan="Progres yang Anda input akan tampil di sini." />
          ) : (
            <ul className="divide-y divide-line">
              {progresTerbaru.map((item) => (
                <li key={item.id}>
                  <Link to={`/progres/${item.id}`} className="group flex items-center gap-3 px-4 py-3 hover:bg-surface/60 sm:px-5">
                    <span className="grid w-11 shrink-0 place-items-center rounded-lg bg-surface py-1.5 text-center">
                      <span className="text-sm leading-none font-semibold text-ink">{tanggal(item.tanggal_laporan, 'dd')}</span>
                      <span className="mt-0.5 text-[10px] leading-none text-muted uppercase">{tanggal(item.tanggal_laporan, 'MMM')}</span>
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm text-ink group-hover:text-primary">{item.nama_proyek}</p>
                      <p className="truncate text-[11px] text-muted">{item.periode ?? '-'}</p>
                    </div>
                    {item.status && <ReportStatusBadge status={item.status as ReportStatus} />}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </section>

    </div>
  )
}
