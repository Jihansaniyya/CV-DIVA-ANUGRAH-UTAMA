import { CurveSChart } from '@/components/charts/CurveSChart'
import { DeviationBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useCurveS } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import { angka, persen, rentangTanggal } from '@/utils/format'
import { Percent, Target, TrendingUp } from 'lucide-react'

export function CurveTab({ projectId }: { projectId: number }) {
  const { data, isLoading, error, refetch } = useCurveS(projectId)

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data || data.titik.length === 0) {
    return <EmptyState judul="Kurva S belum dapat dibentuk" pesan="Tentukan periode dan rencana pekerjaan terlebih dahulu." />
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-3">
        <KpiCard label="Progres Rencana" value={persen(data.ringkasan.progres_rencana)} icon={Target} tone="navy" />
        <KpiCard label="Progres Realisasi" value={persen(data.ringkasan.progres_aktual)} icon={TrendingUp} tone="primary" />
        <KpiCard
          label="Deviasi"
          value={<DeviationBadge nilai={data.ringkasan.deviasi} />}
          icon={Percent}
          tone={data.ringkasan.deviasi < 0 ? 'warning' : 'success'}
          hint={data.ringkasan.deviasi < 0 ? 'Realisasi tertinggal dari rencana' : 'Realisasi sesuai atau mendahului rencana'}
        />
      </div>

      <Card title="Kurva S Proyek" description="Perbandingan rencana kumulatif dan realisasi kumulatif per periode.">
        <CurveSChart data={data} tinggi={340} />
      </Card>

      <Card title="Rincian per Periode" bodyClassName="pt-0">
        <TableWrap>
          <Table>
            <thead>
              <tr>
                <Th>Periode</Th>
                <Th>Tanggal</Th>
                <Th align="right">Rencana (%)</Th>
                <Th align="right">Rencana Kumulatif (%)</Th>
                <Th align="right">Realisasi (%)</Th>
                <Th align="right">Realisasi Kumulatif (%)</Th>
                <Th align="center">Deviasi</Th>
              </tr>
            </thead>
            <tbody>
              {data.titik.map((titik) => (
                <tr key={titik.period_id} className="hover:bg-surface/60">
                  <Td className="font-medium">{titik.nama_periode}</Td>
                  <Td className="text-muted whitespace-nowrap">{rentangTanggal(titik.tanggal_mulai, titik.tanggal_selesai)}</Td>
                  <Td align="right">{angka(titik.rencana, 3)}</Td>
                  <Td align="right">{angka(titik.rencana_kumulatif, 3)}</Td>
                  <Td align="right">{titik.aktual === null ? '-' : angka(titik.aktual, 3)}</Td>
                  <Td align="right">{titik.aktual_kumulatif === null ? '-' : angka(titik.aktual_kumulatif, 3)}</Td>
                  <Td align="center">
                    <DeviationBadge nilai={titik.deviasi} />
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </TableWrap>
      </Card>
    </div>
  )
}
