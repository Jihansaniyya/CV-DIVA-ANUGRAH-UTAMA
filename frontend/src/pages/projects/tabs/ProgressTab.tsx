import { ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useProgressList } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { pesanError } from '@/lib/api'
import { persen, tanggalSingkat } from '@/utils/format'
import { Plus } from 'lucide-react'
import { Link } from 'react-router-dom'

export function ProgressTab({ projectId }: { projectId: number }) {
  const { punyaPeran } = useAuth()
  const { data, isLoading, error, refetch } = useProgressList({ project_id: projectId, per_page: 25 })

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />

  return (
    <Card
      title="Laporan Progres"
      description="Laporan progres harian yang diinput QS untuk proyek ini."
      action={
        punyaPeran('QS', 'ADMIN') ? (
          <Link to={`/progres/baru?project_id=${projectId}`}>
            <Button size="sm" icon={<Plus className="size-4" />}>
              Input Progres
            </Button>
          </Link>
        ) : undefined
      }
      bodyClassName="pt-0"
    >
      {!data || data.data.length === 0 ? (
        <EmptyState judul="Belum ada laporan progres" pesan="Laporan yang diinput QS akan tampil di sini." />
      ) : (
        <TableWrap>
          <Table>
            <thead>
              <tr>
                <Th>Tanggal</Th>
                <Th>Periode</Th>
                <Th>Pelapor</Th>
                <Th>Pekerjaan</Th>
                <Th align="right">Bobot Realisasi</Th>
                <Th align="center">Status</Th>
                <Th align="center">Aksi</Th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((laporan) => (
                <tr key={laporan.id} className="hover:bg-surface/60">
                  <Td className="whitespace-nowrap">{tanggalSingkat(laporan.tanggal_laporan)}</Td>
                  <Td className="text-muted">{laporan.periode ?? '-'}</Td>
                  <Td className="text-muted">{laporan.pelapor ?? '-'}</Td>
                  <Td className="text-muted">{laporan.detail?.length ?? 0} pekerjaan</Td>
                  <Td align="right">{persen(laporan.total_bobot_realisasi)}</Td>
                  <Td align="center">
                    <ReportStatusBadge status={laporan.status} />
                  </Td>
                  <Td align="center">
                    <Link to={`/progres/${laporan.id}`} className="text-xs font-medium text-primary hover:underline">
                      Detail
                    </Link>
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
