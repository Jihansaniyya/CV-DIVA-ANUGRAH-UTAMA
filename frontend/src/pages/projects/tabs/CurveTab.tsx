import { CurveSChart } from '@/components/charts/CurveSChart'
import { Badge, DeviationBadge, warnaDeviasi } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { KpiCard } from '@/components/ui/KpiCard'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useCurveS } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { pesanError } from '@/lib/api'
import { cn } from '@/utils/cn'
import { angka, deviasi, persen, rentangTanggal } from '@/utils/format'
import { LineChart, Target, TrendingUp } from 'lucide-react'
import { Link } from 'react-router-dom'

export function CurveTab({ projectId }: { projectId: number }) {
  const { data, isLoading, error, refetch } = useCurveS(projectId)
  const { punyaPeran } = useAuth()

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

  // Sejalan dengan aturan progres: Kurva S baru terbentuk setelah bobot rencana pekerjaan 100%.
  if (!ringkasan.rencana_lengkap) {
    return (
      <Card>
        <EmptyState
          judul="Kurva S belum terbentuk"
          pesan={`Rencana pekerjaan baru dijadwalkan ${persen(ringkasan.total_bobot_rencana)} dari total bobot pekerjaan. Kurva S terbentuk setelah Admin melengkapi rencana hingga 100%.`}
          aksi={
            punyaPeran('ADMIN') ? (
              <Link to={`/proyek/${projectId}?tab=rencana`}>
                <Button size="sm">Lengkapi Rencana</Button>
              </Link>
            ) : undefined
          }
        />
      </Card>
    )
  }
  const periodeBerjalan = [...data.titik].reverse().find((titik) => titik.aktual_kumulatif !== null)

  return (
    // `print-area` membuat tab ini ikut tercetak; grafik Kurva S menempati halaman sendiri, komponen di bawahnya mulai di halaman berikutnya.
    <div className="print-area flex flex-col gap-4 print:block print:space-y-4">
      <Card
        className="print:break-after-page print:break-inside-avoid"
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
        <CurveSChart data={data} tinggi={340} />
      </Card>

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
