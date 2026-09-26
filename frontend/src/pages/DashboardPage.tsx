import { ProgressComparisonChart } from '@/components/charts/ProgressComparisonChart'
import { StatusDonut } from '@/components/charts/StatusDonut'
import { DeviationBadge, StatusBadge, warnaDeviasi } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useDashboard } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import { QsDashboard } from '@/pages/dashboard/QsDashboard'
import type { DashboardData, DashboardProjectRow } from '@/types'
import { cn } from '@/utils/cn'
import { deviasi, hariIni, persen, tanggal, tanggalSingkat } from '@/utils/format'
import {
  AlertTriangle,
  CalendarDays,
  ChevronRight,
  ClipboardList,
  HardHat,
  LineChart,
  Percent,
  Target,
  TrendingUp,
  Users,
} from 'lucide-react'
import { Link } from 'react-router-dom'

const tautan = 'inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:text-primary-dark hover:underline'

/** Ringkasan progres tiap proyek untuk Kontraktor (hanya pemantauan, tanpa pengelolaan proyek). */
function MonitoringProyek({ proyek }: { proyek: DashboardProjectRow[] }) {
  return (
    <Card
      title="Ringkasan Monitoring Proyek"
      description="Progres aktual dari laporan QS yang sudah dikirim dibandingkan rencana Kurva S sampai hari ini."
      flush
    >
      {proyek.length === 0 ? (
        <EmptyState judul="Belum ada proyek" pesan="Proyek yang dibuat Admin akan tampil di sini." />
      ) : (
        <>
        <ul className="divide-y divide-line md:hidden">
          {proyek.map((baris) => (
            <li key={baris.id} className="flex flex-col gap-2.5 px-4 py-3.5">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="line-clamp-2 text-sm leading-snug font-medium text-ink">{baris.nama_proyek}</p>
                  <p className="mt-0.5 truncate text-xs text-muted">{baris.lokasi}</p>
                </div>
                <StatusBadge status={baris.status} />
              </div>
              <ProgressBar nilai={baris.progres_aktual} pembanding={baris.progres_rencana} />
              <div className="flex items-center justify-between gap-3 text-xs">
                <span className="text-muted">
                  Rencana <span className="font-medium text-ink">{persen(baris.progres_rencana)}</span>
                </span>
                <span className="text-muted">
                  Deviasi <span className={cn('font-semibold', warnaDeviasi(baris.deviasi))}>{deviasi(baris.deviasi)}</span>
                </span>
                <Link to={`/kurva-s?project_id=${baris.id}`} className={tautan}>
                  Kurva S
                  <ChevronRight className="size-3.5" aria-hidden />
                </Link>
              </div>
            </li>
          ))}
        </ul>
        <TableWrap flush className="hidden md:block">
          <Table>
            <thead>
              <tr>
                <Th className="w-10">No</Th>
                <Th className="min-w-64">Nama Proyek</Th>
                <Th className="min-w-48">Progres Aktual</Th>
                <Th align="right">Rencana</Th>
                <Th align="center">Deviasi</Th>
                <Th align="center">Status</Th>
                <Th align="right">
                  <span className="sr-only">Aksi</span>
                </Th>
              </tr>
            </thead>
            <tbody>
              {proyek.map((baris, index) => (
                <tr key={baris.id} className="hover:bg-surface/60">
                  <Td className="text-muted">{index + 1}</Td>
                  <Td className="max-w-sm">
                    <p className="truncate font-medium text-ink" title={baris.nama_proyek}>
                      {baris.nama_proyek}
                    </p>
                    <p className="truncate text-xs text-muted" title={baris.lokasi}>
                      {baris.lokasi}
                    </p>
                  </Td>
                  <Td>
                    <ProgressBar nilai={baris.progres_aktual} pembanding={baris.progres_rencana} />
                  </Td>
                  <Td align="right" className="whitespace-nowrap text-muted">
                    {persen(baris.progres_rencana)}
                  </Td>
                  <Td align="center">
                    <DeviationBadge nilai={baris.deviasi} />
                  </Td>
                  <Td align="center">
                    <StatusBadge status={baris.status} />
                  </Td>
                  <Td align="right" className="whitespace-nowrap">
                    <Link to={`/kurva-s?project_id=${baris.id}`} className={tautan}>
                      Kurva S
                      <ChevronRight className="size-3.5" aria-hidden />
                    </Link>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </TableWrap>
        </>
      )}
    </Card>
  )
}

function ProyekTerbaruAdmin({ proyek }: { proyek: DashboardProjectRow[] }) {
  return (
    <Card
      title="Proyek Terbaru"
      action={
        <Link to="/proyek" className={tautan}>
          Lihat Semua
          <ChevronRight className="size-3.5" aria-hidden />
        </Link>
      }
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
                <Th className="min-w-64">Nama Proyek</Th>
                <Th>Lokasi</Th>
                <Th>QS</Th>
                <Th align="right">Rencana</Th>
                <Th className="min-w-44">Realisasi</Th>
                <Th align="center">Deviasi</Th>
                <Th align="center">
                  Status
                </Th>
              </tr>
            </thead>
            <tbody>
              {proyek.map((baris, index) => (
                <tr key={baris.id} className="hover:bg-surface/60">
                  <Td className="text-muted">{index + 1}</Td>
                  <Td className="max-w-sm">
                    <Link to={`/proyek/${baris.id}`} className="block truncate font-medium text-ink hover:text-primary" title={baris.nama_proyek}>
                      {baris.nama_proyek}
                    </Link>
                    <p className="text-xs text-muted">{tanggalSingkat(baris.tanggal_mulai)}</p>
                  </Td>
                  <Td className="text-muted">{baris.lokasi}</Td>
                  <Td className="text-muted">{baris.qs ?? '-'}</Td>
                  <Td align="right">{persen(baris.progres_rencana)}</Td>
                  <Td>
                    <ProgressBar nilai={baris.progres_aktual} pembanding={baris.progres_rencana} />
                  </Td>
                  <Td align="center">
                    <DeviationBadge nilai={baris.deviasi} />
                  </Td>
                  <Td align="center">
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

function LaporanTerbaru({ laporan, tampilLihatSemua }: { laporan: NonNullable<DashboardData['laporan_terbaru']>; tampilLihatSemua: boolean }) {
  return (
    <Card
      title="Laporan Progres Terbaru"
      description="Lima laporan terakhir yang dikirim QS."
      action={
        tampilLihatSemua ? (
          <Link to="/progres" className={tautan}>
            Lihat Semua
            <ChevronRight className="size-3.5" aria-hidden />
          </Link>
        ) : undefined
      }
      flush
    >
      <ul className="divide-y divide-line md:hidden">
        {laporan.map((item) => (
          <li key={item.id}>
            <Link to={`/progres/${item.id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-surface/60">
              <div className="min-w-0 flex-1">
                <p className="text-xs text-muted">
                  {tanggalSingkat(item.tanggal_laporan)} · {item.periode ?? 'Di luar periode'}
                </p>
                <p className="mt-0.5 line-clamp-2 text-sm leading-snug text-ink">{item.nama_proyek}</p>
                <p className="mt-0.5 text-xs text-muted">{item.pelapor ?? '-'}</p>
              </div>
              <span className="text-sm font-semibold text-ink tabular-nums">
                {item.bobot_realisasi !== undefined ? persen(item.bobot_realisasi) : (item.status ?? '-')}
              </span>
              <ChevronRight className="size-4 shrink-0 text-muted" aria-hidden />
            </Link>
          </li>
        ))}
      </ul>
      <TableWrap flush className="hidden md:block">
        <Table>
          <thead>
            <tr>
              <Th>Tanggal</Th>
              <Th className="min-w-64">Proyek</Th>
              <Th>Periode</Th>
              <Th>Pelapor</Th>
              <Th align="right">Bobot Realisasi</Th>
              <Th align="right">
                <span className="sr-only">Aksi</span>
              </Th>
            </tr>
          </thead>
          <tbody>
            {laporan.map((item) => (
              <tr key={item.id} className="hover:bg-surface/60">
                <Td className="whitespace-nowrap">{tanggalSingkat(item.tanggal_laporan)}</Td>
                <Td className="max-w-md">
                  <p className="truncate text-ink" title={item.nama_proyek ?? undefined}>
                    {item.nama_proyek}
                  </p>
                </Td>
                <Td className="whitespace-nowrap text-muted">{item.periode ?? 'Di luar periode'}</Td>
                <Td className="whitespace-nowrap text-muted">{item.pelapor ?? '-'}</Td>
                <Td align="right" className="font-semibold whitespace-nowrap">
                  {item.bobot_realisasi !== undefined ? persen(item.bobot_realisasi) : (item.status ?? '-')}
                </Td>
                <Td align="right" className="whitespace-nowrap">
                  <Link to={`/progres/${item.id}`} className={tautan}>
                    Detail
                    <ChevronRight className="size-3.5" aria-hidden />
                  </Link>
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      </TableWrap>
    </Card>
  )
}

export function DashboardPage() {
  const { data, isLoading, error, refetch } = useDashboard()

  if (isLoading) return <LoadingState pesan="Memuat ringkasan proyek..." />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data) return <EmptyState />

  if (data.peran === 'QS') return <QsDashboard data={data} />

  const proyek = data.proyek ?? data.proyek_ditugaskan ?? data.proyek_terbaru ?? []
  const kontraktor = data.peran === 'KONTRAKTOR'

  return (
    <div className="flex flex-col gap-5">
      <p className="flex items-center gap-1.5 text-xs text-muted">
        <CalendarDays className="size-3.5" aria-hidden />
        Data per {tanggal(hariIni(), 'EEEE, dd MMMM yyyy')}
      </p>

      <section className={cn('grid grid-cols-2 gap-3 sm:gap-4', kontraktor ? 'lg:grid-cols-3 xl:grid-cols-5' : 'xl:grid-cols-4')}>
        {data.peran === 'ADMIN' && (
          <>
            <KpiCard label="Total Proyek" value={data.kpi.total_proyek} icon={ClipboardList} tone="navy" />
            <KpiCard label="Total Pekerjaan" value={data.kpi.total_pekerjaan} icon={HardHat} tone="primary" />
            <KpiCard label="Total Pengguna" value={data.kpi.total_pengguna} icon={Users} tone="success" />
            <KpiCard label="Progres Rata-rata" value={persen(data.kpi.progres_rata_rata)} icon={Percent} tone="warning" />
          </>
        )}

        {kontraktor && (
          <>
            <KpiCard
              label="Total Proyek"
              value={data.kpi.total_proyek}
              icon={ClipboardList}
              tone="neutral"
              hint={<>{data.kpi.proyek_berjalan} sedang berjalan</>}
            />
            <KpiCard label="Progres Aktual" value={persen(data.kpi.progres_aktual)} icon={TrendingUp} tone="primary" hint="Rata-rata realisasi laporan QS" />
            <KpiCard label="Progres Rencana" value={persen(data.kpi.progres_rencana)} icon={Target} tone="navy" hint="Rata-rata target s/d hari ini" />
            <KpiCard
              label="Deviasi"
              value={deviasi(data.kpi.deviasi)}
              valueClassName={warnaDeviasi(data.kpi.deviasi)}
              icon={LineChart}
              tone={data.kpi.deviasi < -0.005 ? 'danger' : 'success'}
              hint={data.kpi.deviasi < -0.005 ? 'Realisasi di bawah rencana' : 'Realisasi sesuai atau di atas rencana'}
            />
            <KpiCard
              label="Proyek Tertinggal"
              value={data.kpi.proyek_terlambat}
              valueClassName={data.kpi.proyek_terlambat > 0 ? 'text-[#b45309]' : undefined}
              icon={AlertTriangle}
              tone="warning"
              hint="Deviasi negatif terhadap rencana"
              className="col-span-2 lg:col-span-1"
            />
          </>
        )}
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        {data.status_proyek && (
          <Card title="Status Proyek" description="Komposisi status seluruh proyek.">
            <StatusDonut
              data={data.status_proyek}
              tengah={String(data.status_proyek.reduce((total, item) => total + item.jumlah, 0))}
              keterangan="Total proyek"
            />
          </Card>
        )}

        {data.grafik_progres && data.grafik_progres.length > 0 && (
          <Card title="Perbandingan Rencana dan Realisasi" description="Progres per proyek sampai hari ini." className="lg:col-span-2">
            <ProgressComparisonChart data={data.grafik_progres} />
          </Card>
        )}
      </section>

      {kontraktor ? <MonitoringProyek proyek={proyek} /> : <ProyekTerbaruAdmin proyek={proyek} />}

      {data.laporan_terbaru && data.laporan_terbaru.length > 0 && (
        <LaporanTerbaru laporan={data.laporan_terbaru} tampilLihatSemua={kontraktor} />
      )}
    </div>
  )
}
