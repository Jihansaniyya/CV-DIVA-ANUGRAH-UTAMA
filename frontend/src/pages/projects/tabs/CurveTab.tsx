import { CurveSChart } from '@/components/charts/CurveSChart'
import { Badge, DeviationBadge, warnaDeviasi } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useCurveS } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import { cn } from '@/utils/cn'
import { angka, deviasi, persen, rentangTanggal, tanggalSingkat } from '@/utils/format'
import { Flag, LineChart, Target, TrendingUp } from 'lucide-react'

const STATUS_MILESTONE: Record<string, { label: string; tone: 'success' | 'danger' | 'neutral' }> = {
  TERCAPAI: { label: 'Tercapai', tone: 'success' },
  TERLAMBAT: { label: 'Terlambat', tone: 'danger' },
  BELUM_TERCAPAI: { label: 'Belum tercapai', tone: 'neutral' },
}

export function CurveTab({ projectId }: { projectId: number }) {
  const { data, isLoading, error, refetch } = useCurveS(projectId)

  if (isLoading) return <LoadingState pesan="Memuat Kurva S..." />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data || data.titik.length === 0) {
    return (
      <Card>
        <EmptyState judul="Kurva S belum dapat dibentuk" pesan="Kurva S tampil setelah periode dan rencana pekerjaan proyek disusun." />
      </Card>
    )
  }

  const { ringkasan } = data
  const periodeBerjalan = [...data.titik].reverse().find((titik) => titik.aktual_kumulatif !== null)
  const titikPerPeriode = new Map(data.titik.map((titik) => [titik.period_id, titik]))

  return (
    <div className="flex flex-col gap-4">
      <section className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
        <KpiCard label="Progres Rencana" value={persen(ringkasan.progres_rencana)} icon={Target} tone="navy" hint="Rencana kumulatif s/d hari ini" />
        <KpiCard label="Progres Realisasi" value={persen(ringkasan.progres_aktual)} icon={TrendingUp} tone="primary" hint="Akumulasi laporan QS terkirim" />
        <KpiCard
          label="Deviasi"
          value={deviasi(ringkasan.deviasi)}
          valueClassName={warnaDeviasi(ringkasan.deviasi)}
          icon={LineChart}
          tone={ringkasan.deviasi < -0.005 ? 'danger' : 'success'}
          hint={ringkasan.deviasi < -0.005 ? 'Realisasi tertinggal dari rencana' : 'Realisasi sesuai atau mendahului rencana'}
          className="col-span-2 sm:col-span-1"
        />
      </section>

      <Card
        title="Grafik Kurva S"
        description="Rencana kumulatif dibandingkan realisasi kumulatif per periode."
        action={
          periodeBerjalan && (
            <span className="text-xs text-muted">
              Periode berjalan: <span className="font-medium text-ink">{periodeBerjalan.nama_periode}</span>
            </span>
          )
        }
      >
        <CurveSChart data={data} tinggi={380} />

        {data.milestones.length > 0 && (
          <div className="mt-5 border-t border-line pt-4">
            <h3 className="mb-2.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted uppercase">
              <Flag className="size-3.5" aria-hidden />
              Milestone
            </h3>
            <ul className="grid gap-2 md:grid-cols-2">
              {data.milestones.map((item) => {
                const titik = item.period_id ? titikPerPeriode.get(item.period_id) : undefined
                const status = STATUS_MILESTONE[item.status] ?? STATUS_MILESTONE.BELUM_TERCAPAI

                return (
                  <li key={item.id} className="flex items-start gap-3 rounded-lg border border-line px-3 py-2.5">
                    <span className="mt-1.5 size-2.5 shrink-0 rounded-full bg-warning" aria-hidden />
                    <div className="min-w-0 flex-1">
                      <p className="text-sm leading-snug font-medium text-ink">{item.nama}</p>
                      <p className="mt-0.5 text-xs text-muted">
                        Target {persen(item.target_persentase)} · {titik?.nama_periode ?? 'Tanpa periode'} · {tanggalSingkat(item.tanggal_target)}
                        {titik?.aktual_kumulatif != null && <> · Realisasi {persen(titik.aktual_kumulatif)}</>}
                      </p>
                    </div>
                    <Badge tone={status.tone}>{status.label}</Badge>
                  </li>
                )
              })}
            </ul>
          </div>
        )}
      </Card>

      <Card title="Rincian per Periode" description="Nilai dalam persen bobot terhadap total proyek." flush>
        <TableWrap flush>
          <Table>
            <thead>
              <tr>
                <Th>Periode</Th>
                <Th>Tanggal</Th>
                <Th align="right">Rencana</Th>
                <Th align="right">Rencana Kumulatif</Th>
                <Th align="right">Realisasi</Th>
                <Th align="right">Realisasi Kumulatif</Th>
                <Th align="center">Deviasi</Th>
              </tr>
            </thead>
            <tbody>
              {data.titik.map((titik) => {
                const berjalan = titik.period_id === periodeBerjalan?.period_id

                return (
                  <tr key={titik.period_id} className={cn('hover:bg-surface/60', berjalan && 'bg-primary-light/40')}>
                    <Td className="font-medium whitespace-nowrap">
                      {titik.nama_periode}
                      {berjalan && (
                        <Badge tone="info" className="ml-2">
                          Berjalan
                        </Badge>
                      )}
                    </Td>
                    <Td className="whitespace-nowrap text-muted">{rentangTanggal(titik.tanggal_mulai, titik.tanggal_selesai)}</Td>
                    <Td align="right" className="text-muted">
                      {angka(titik.rencana, 2)}
                    </Td>
                    <Td align="right" className="font-medium">
                      {angka(titik.rencana_kumulatif, 2)}
                    </Td>
                    <Td align="right" className="text-muted">
                      {titik.aktual === null ? '-' : angka(titik.aktual, 2)}
                    </Td>
                    <Td align="right" className="font-medium">
                      {titik.aktual_kumulatif === null ? '-' : angka(titik.aktual_kumulatif, 2)}
                    </Td>
                    <Td align="center">
                      {titik.deviasi === null ? <span className="text-xs text-muted">-</span> : <DeviationBadge nilai={titik.deviasi} />}
                    </Td>
                  </tr>
                )
              })}
            </tbody>
          </Table>
        </TableWrap>
      </Card>
    </div>
  )
}
