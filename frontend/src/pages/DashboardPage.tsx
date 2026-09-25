import { ProgressComparisonChart } from '@/components/charts/ProgressComparisonChart'
import { StatusDonut } from '@/components/charts/StatusDonut'
import { DeviationBadge, StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useDashboard } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { pesanError } from '@/lib/api'
import type { DashboardProjectRow } from '@/types'
import { angka, persen, tanggalSingkat } from '@/utils/format'
import { AlertTriangle, ClipboardList, FileText, HardHat, Percent, TrendingUp, Users } from 'lucide-react'
import { Link } from 'react-router-dom'

export function DashboardPage() {
  const { user } = useAuth()
  const { data, isLoading, error, refetch } = useDashboard()

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data) return <EmptyState />

  const proyek = data.proyek ?? data.proyek_ditugaskan ?? data.proyek_terbaru ?? []

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h2 className="text-lg font-semibold text-ink">Selamat datang, {user?.name}!</h2>
        <p className="text-xs text-muted">Berikut ringkasan aktivitas proyek Anda hari ini.</p>
      </div>

      <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {data.peran === 'ADMIN' && (
          <>
            <KpiCard label="Total Proyek" value={data.kpi.total_proyek} icon={ClipboardList} tone="navy" />
            <KpiCard label="Total Pekerjaan" value={data.kpi.total_pekerjaan} icon={HardHat} tone="primary" />
            <KpiCard label="Total Pengguna" value={data.kpi.total_pengguna} icon={Users} tone="success" />
            <KpiCard label="Progres Rata-rata" value={persen(data.kpi.progres_rata_rata)} icon={Percent} tone="warning" />
          </>
        )}

        {data.peran === 'QS' && (
          <>
            <KpiCard label="Proyek Ditugaskan" value={data.kpi.total_proyek} icon={ClipboardList} tone="navy" />
            <KpiCard label="Total Pekerjaan" value={data.kpi.total_pekerjaan} icon={HardHat} tone="primary" />
            <KpiCard label="Laporan Draft" value={data.kpi.laporan_draft} icon={FileText} tone="warning" />
            <KpiCard label="Laporan Dikirim" value={data.kpi.laporan_dikirim} icon={FileText} tone="success" />
          </>
        )}

        {data.peran === 'KONTRAKTOR' && (
          <>
            <KpiCard label="Total Proyek" value={data.kpi.total_proyek} icon={ClipboardList} tone="navy" />
            <KpiCard label="Progres Aktual" value={persen(data.kpi.progres_aktual)} icon={TrendingUp} tone="primary" />
            <KpiCard label="Progres Rencana" value={persen(data.kpi.progres_rencana)} icon={Percent} tone="success" />
            <KpiCard
              label="Proyek Tertinggal"
              value={data.kpi.proyek_terlambat}
              icon={AlertTriangle}
              tone="warning"
              hint={<>Rata-rata deviasi {persen(data.kpi.deviasi)}</>}
            />
          </>
        )}
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        {data.status_proyek && (
          <Card title="Status Proyek" className="lg:col-span-1">
            <StatusDonut
              data={data.status_proyek}
              tengah={String(data.status_proyek.reduce((total, item) => total + item.jumlah, 0))}
              keterangan="Total proyek"
            />
          </Card>
        )}

        {data.grafik_progres && data.grafik_progres.length > 0 && (
          <Card title="Perbandingan Rencana dan Realisasi" className="lg:col-span-2">
            <ProgressComparisonChart data={data.grafik_progres} />
          </Card>
        )}

        {data.peran === 'QS' && data.pekerjaan_perlu_laporan && (
          <Card title="Pekerjaan yang Membutuhkan Laporan" className="lg:col-span-2">
            {data.pekerjaan_perlu_laporan.length === 0 ? (
              <EmptyState judul="Semua pekerjaan sudah dilaporkan" />
            ) : (
              <ul className="flex flex-col divide-y divide-line">
                {data.pekerjaan_perlu_laporan.map((item) => (
                  <li key={item.work_item_id} className="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="text-sm font-medium text-ink">{item.uraian_pekerjaan}</p>
                      <span className="text-xs text-muted">
                        Sisa {angka(item.sisa_volume)} {item.satuan}
                      </span>
                    </div>
                    <p className="text-[11px] text-muted">{item.nama_proyek}</p>
                    <ProgressBar nilai={item.persentase} />
                  </li>
                ))}
              </ul>
            )}
          </Card>
        )}
      </section>

      <Card
        title={data.peran === 'QS' ? 'Proyek yang Ditugaskan' : 'Proyek Terbaru'}
        action={
          <Link to="/proyek" className="text-xs font-medium text-primary hover:underline">
            Lihat Semua
          </Link>
        }
        bodyClassName="pt-0"
      >
        {proyek.length === 0 ? (
          <EmptyState judul="Belum ada proyek" pesan="Proyek yang dibuat Admin akan tampil di sini." />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>No</Th>
                  <Th>Nama Proyek</Th>
                  <Th>Lokasi</Th>
                  <Th>QS</Th>
                  <Th align="right">Rencana</Th>
                  <Th>Realisasi</Th>
                  <Th align="center">Deviasi</Th>
                  <Th align="center">Status</Th>
                </tr>
              </thead>
              <tbody>
                {proyek.map((baris: DashboardProjectRow, index: number) => (
                  <tr key={baris.id} className="hover:bg-surface/60">
                    <Td>{index + 1}</Td>
                    <Td>
                      <Link to={`/proyek/${baris.id}`} className="font-medium text-ink hover:text-primary">
                        {baris.nama_proyek}
                      </Link>
                      <p className="text-[11px] text-muted">{tanggalSingkat(baris.tanggal_mulai)}</p>
                    </Td>
                    <Td className="text-muted">{baris.lokasi}</Td>
                    <Td className="text-muted">{baris.qs ?? '-'}</Td>
                    <Td align="right">{persen(baris.progres_rencana)}</Td>
                    <Td className="min-w-40">
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

      {data.laporan_terbaru && data.laporan_terbaru.length > 0 && (
        <Card title="Laporan Progres Terbaru" bodyClassName="pt-0">
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Tanggal</Th>
                  <Th>Proyek</Th>
                  <Th>Periode</Th>
                  <Th>Pelapor</Th>
                  <Th align="right">Bobot Realisasi</Th>
                </tr>
              </thead>
              <tbody>
                {data.laporan_terbaru.map((item) => (
                  <tr key={item.id} className="hover:bg-surface/60">
                    <Td>{tanggalSingkat(item.tanggal_laporan)}</Td>
                    <Td>
                      <Link to={`/progres/${item.id}`} className="text-ink hover:text-primary">
                        {item.nama_proyek}
                      </Link>
                    </Td>
                    <Td className="text-muted">{item.periode ?? '-'}</Td>
                    <Td className="text-muted">{item.pelapor ?? '-'}</Td>
                    <Td align="right">{item.bobot_realisasi !== undefined ? persen(item.bobot_realisasi) : (item.status ?? '-')}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        </Card>
      )}
    </div>
  )
}
