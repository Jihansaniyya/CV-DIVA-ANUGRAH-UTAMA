import { ProgressComparisonChart } from '@/components/charts/ProgressComparisonChart'
import { StatusDonut } from '@/components/charts/StatusDonut'
import { DeviationBadge, StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useDashboard } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import { KontraktorDashboard } from '@/pages/dashboard/KontraktorDashboard'
import { QsDashboard } from '@/pages/dashboard/QsDashboard'
import type { DashboardData, DashboardProjectRow } from '@/types'
import { cn } from '@/utils/cn'
import { hariIni, persen, tanggal, tanggalSingkat } from '@/utils/format'
import { CalendarDays, ChevronRight, ClipboardList, HardHat, Percent, Users } from 'lucide-react'
import { Link } from 'react-router-dom'

const tautan = 'inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:text-primary-dark hover:underline'

/** Kartu bagian bawah di layar lebar: mengisi sisa tinggi, isinya digulir di dalam kartu. */
const KARTU_PENUH = 'xl:flex xl:min-h-0 xl:flex-col'
const ISI_GULIR = 'xl:min-h-0 xl:flex-1 xl:overflow-y-auto'

function ProyekTerbaruAdmin({ proyek, className }: { proyek: DashboardProjectRow[]; className?: string }) {
  return (
    <Card
      title="Proyek Terbaru"
      action={
        <Link to="/proyek" className={tautan}>
          Lihat Semua
          <ChevronRight className="size-3.5" aria-hidden />
        </Link>
      }
      className={cn(KARTU_PENUH, className)}
      bodyClassName={ISI_GULIR}
      flush
    >
      {proyek.length === 0 ? (
        <EmptyState judul="Belum ada proyek" pesan="Proyek yang dibuat Admin akan tampil di sini." />
      ) : (
        <TableWrap flush>
          <Table>
            <thead>
              <tr>
                <Th className="w-10">No</Th>
                <Th className="min-w-56">Nama Proyek</Th>
                <Th>QS</Th>
                <Th align="right">Rencana</Th>
                <Th className="min-w-40">Realisasi</Th>
                <Th align="center">Deviasi</Th>
                <Th align="center">Status</Th>
              </tr>
            </thead>
            <tbody>
              {proyek.map((baris, index) => (
                <tr key={baris.id} className="hover:bg-surface/60">
                  <Td className="py-2 text-muted">{index + 1}</Td>
                  <Td className="max-w-xs py-2">
                    <Link to={`/proyek/${baris.id}`} className="block truncate font-medium text-ink hover:text-primary" title={baris.nama_proyek}>
                      {baris.nama_proyek}
                    </Link>
                    <p className="truncate text-xs text-muted" title={baris.lokasi}>
                      {baris.lokasi}
                    </p>
                  </Td>
                  <Td className="py-2 whitespace-nowrap text-muted">{baris.qs ?? '-'}</Td>
                  <Td align="right" className="py-2">
                    {persen(baris.progres_rencana)}
                  </Td>
                  <Td className="py-2">
                    <ProgressBar nilai={baris.progres_aktual} pembanding={baris.progres_rencana} />
                  </Td>
                  <Td align="center" className="py-2">
                    <DeviationBadge nilai={baris.deviasi} />
                  </Td>
                  <Td align="center" className="py-2">
                    <StatusBadge status={baris.status} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </TableWrap>
      )}
    </Card>
  )
}

/** Daftar ringkas laporan terbaru; cukup sempit untuk diletakkan di samping tabel proyek. */
function LaporanTerbaru({ laporan, tampilLihatSemua }: { laporan: NonNullable<DashboardData['laporan_terbaru']>; tampilLihatSemua: boolean }) {
  return (
    <Card
      title="Laporan Progres Terbaru"
      action={
        tampilLihatSemua ? (
          <Link to="/progres" className={tautan}>
            Lihat Semua
            <ChevronRight className="size-3.5" aria-hidden />
          </Link>
        ) : undefined
      }
      className={KARTU_PENUH}
      bodyClassName={ISI_GULIR}
      flush
    >
      <ul className="divide-y divide-line">
        {laporan.map((item) => (
          <li key={item.id}>
            <Link to={`/progres/${item.id}`} className="flex items-center gap-3 px-4 py-2 hover:bg-surface/60 sm:px-5">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm leading-snug text-ink" title={item.nama_proyek ?? undefined}>
                  {item.nama_proyek}
                </p>
                <p className="truncate text-xs text-muted">
                  {tanggalSingkat(item.tanggal_laporan)} · {item.periode ?? 'Di luar periode'} · {item.pelapor ?? '-'}
                </p>
              </div>
              <span className="text-sm font-semibold text-ink tabular-nums">
                {item.bobot_realisasi !== undefined ? persen(item.bobot_realisasi) : (item.status ?? '-')}
              </span>
              <ChevronRight className="size-4 shrink-0 text-muted" aria-hidden />
            </Link>
          </li>
        ))}
      </ul>
    </Card>
  )
}

export function DashboardPage() {
  const { data, isLoading, error, refetch } = useDashboard()

  if (isLoading) return <LoadingState pesan="Memuat ringkasan proyek..." />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data) return <EmptyState />

  if (data.peran === 'QS') return <QsDashboard data={data} />
  if (data.peran === 'KONTRAKTOR') return <KontraktorDashboard data={data} />

  const proyek = data.proyek_terbaru ?? []
  const adaLaporan = Boolean(data.laporan_terbaru && data.laporan_terbaru.length > 0)

  return (
    // Layar lebar: tinggi halaman dikunci setinggi layar (dikurangi navbar 4rem + padding 3rem) agar tidak perlu scroll;
    // bagian bawah mengisi sisa ruang dan menggulir isinya sendiri bila datanya panjang.
    <div className="flex flex-col gap-4 xl:h-[calc(100dvh-7rem)] xl:gap-3">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 xl:shrink-0">
        <h2 className="text-lg font-semibold text-ink">Beranda</h2>
        <p className="flex items-center gap-1.5 text-xs text-muted">
          <CalendarDays className="size-3.5" aria-hidden />
          Data per {tanggal(hariIni(), 'EEEE, dd MMMM yyyy')}
        </p>
      </div>

      <section className="grid grid-cols-2 gap-3 xl:shrink-0 xl:grid-cols-4">
        <KpiCard compact label="Total Proyek" value={data.kpi.total_proyek} icon={ClipboardList} tone="navy" />
        <KpiCard compact label="Total Pekerjaan" value={data.kpi.total_pekerjaan} icon={HardHat} tone="primary" />
        <KpiCard compact label="Total Pengguna" value={data.kpi.total_pengguna} icon={Users} tone="success" />
        <KpiCard compact label="Progres Rata-rata" value={persen(data.kpi.progres_rata_rata)} icon={Percent} tone="warning" />

      </section>

      <section className="grid gap-4 lg:grid-cols-3 xl:shrink-0 xl:gap-3">
        {data.status_proyek && (
          <Card title="Status Proyek" bodyClassName="p-4 sm:p-4">
            <StatusDonut
              compact
              data={data.status_proyek}
              tengah={String(data.status_proyek.reduce((total, item) => total + item.jumlah, 0))}
              keterangan="Total proyek"
            />
          </Card>
        )}

        {data.grafik_progres && data.grafik_progres.length > 0 && (
          <Card title="Perbandingan Rencana dan Realisasi" bodyClassName="px-4 pt-3 pb-2 sm:px-5" className="lg:col-span-2">
            <ProgressComparisonChart data={data.grafik_progres} tinggi={180} />
          </Card>
        )}
      </section>

      <section className={cn('grid gap-4 xl:min-h-0 xl:flex-1 xl:grid-rows-[minmax(0,1fr)] xl:gap-3', adaLaporan && 'xl:grid-cols-3')}>
        <ProyekTerbaruAdmin proyek={proyek} className={cn(adaLaporan && 'xl:col-span-2')} />

        {data.laporan_terbaru && adaLaporan && <LaporanTerbaru laporan={data.laporan_terbaru} tampilLihatSemua={false} />}
      </section>
    </div>
  )
}
