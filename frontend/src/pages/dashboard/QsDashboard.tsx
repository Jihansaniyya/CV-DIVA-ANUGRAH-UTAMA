import { ReportStatusBadge, StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState } from '@/components/ui/State'
import type { DashboardData, ReportStatus } from '@/types'
import { angka, hariIni, rentangTanggal, tanggal } from '@/utils/format'
import { CalendarDays, ChevronRight, ClipboardList, FilePen, HardHat, MapPin, Plus, SendHorizontal } from 'lucide-react'
import { Link } from 'react-router-dom'

const tautanUtama =
  'inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-dark'

const tautanKecil = 'inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline'

export function QsDashboard({ data }: { data: DashboardData }) {
  const proyek = data.proyek_ditugaskan ?? []
  const pekerjaan = data.pekerjaan_perlu_laporan ?? []
  const progresTerbaru = data.laporan_terbaru ?? []

  return (
    <div className="flex flex-col gap-5">

      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="flex items-center gap-1.5 text-xs text-muted">
            <CalendarDays className="size-3.5" aria-hidden />
            {tanggal(hariIni(), 'EEEE, dd MMMM yyyy')}
          </p>
          <h2 className="mt-0.5 text-lg font-semibold text-ink">Ringkasan Pekerjaan</h2>
        </div>
        <Link to="/progres/baru" className={tautanUtama}>
          <Plus className="size-4" aria-hidden />
          Tambah Progres
        </Link>
      </div>

      <section className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <KpiCard label="Proyek Ditangani" value={data.kpi.total_proyek ?? 0} icon={ClipboardList} tone="navy" />
        <KpiCard label="Total Pekerjaan" value={data.kpi.total_pekerjaan ?? 0} icon={HardHat} tone="primary" />
        <KpiCard label="Progres Draf" value={data.kpi.laporan_draft ?? 0} icon={FilePen} tone="warning" hint="Belum dikirim" />
        <KpiCard label="Progres Terkirim" value={data.kpi.laporan_dikirim ?? 0} icon={SendHorizontal} tone="success" hint="Tercatat sebagai realisasi" />
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        <Card
          title="Pekerjaan Perlu Diperbarui"
          description="Pekerjaan dengan realisasi terendah pada proyek yang Anda tangani."
          className="lg:col-span-2"
          bodyClassName="p-0"
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
                    className="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink transition-colors hover:border-primary hover:text-primary"
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
          bodyClassName="p-0"
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

      <section className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-base font-semibold text-ink">Proyek yang Ditangani</h3>
          <Link to="/proyek" className={tautanKecil}>
            Lihat semua
          </Link>
        </div>

        {proyek.length === 0 ? (
          <Card>
            <EmptyState judul="Belum ada proyek" pesan="Hubungi Admin untuk penugasan proyek." />
          </Card>
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {proyek.map((item) => (
              <li key={item.id}>
                <Link
                  to={`/proyek/${item.id}?tab=pekerjaan`}
                  className="app-card group flex h-full flex-col gap-3 p-4 transition-colors hover:border-primary/40"
                >
                  <div className="flex items-start justify-between gap-2">
                    <p className="line-clamp-2 text-sm font-semibold text-ink group-hover:text-primary">{item.nama_proyek}</p>
                    <StatusBadge status={item.status} />
                  </div>
                  <div className="flex flex-col gap-1 text-[11px] text-muted">
                    <span className="flex items-center gap-1.5">
                      <MapPin className="size-3.5 shrink-0" aria-hidden />
                      <span className="truncate">{item.lokasi}</span>
                    </span>
                    <span className="flex items-center gap-1.5">
                      <CalendarDays className="size-3.5 shrink-0" aria-hidden />
                      {rentangTanggal(item.tanggal_mulai, item.tanggal_selesai)}
                    </span>
                  </div>
                  <div className="mt-auto">
                    <p className="mb-1 text-[11px] text-muted">Progres realisasi</p>
                    <ProgressBar nilai={item.progres_aktual} />
                  </div>
                  <span className="flex items-center gap-1 border-t border-line pt-3 text-xs font-medium text-primary">
                    Lihat pekerjaan
                    <ChevronRight className="size-3.5" aria-hidden />
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
