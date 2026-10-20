import { DaftarPekerjaan } from '@/components/progress/DaftarPekerjaan'
import { ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useProgressList } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { pesanError } from '@/lib/api'
import type { Project } from '@/types'
import { persen, tanggalSingkat } from '@/utils/format'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

export function ProgressTab({ projectId, project }: { projectId: number; project: Project }) {
  const { punyaPeran } = useAuth()
  const [page, setPage] = useState(1)
  const { data, isLoading, error, refetch } = useProgressList({ project_id: projectId, page, per_page: 25 })

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />

  // Progres baru hanya dapat ditambahkan bila total bobot rencana sudah 100% (backend ikut memvalidasi).
  const rencanaBelumLengkap = project.rencana_lengkap === false

  return (
    <Card
      title="Progres Harian"
      description={
        rencanaBelumLengkap
          ? `Rencana pekerjaan baru dijadwalkan ${persen(project.total_bobot_rencana ?? 0)} dari total bobot pekerjaan. Progres dapat ditambahkan setelah rencana mencapai 100%.`
          : 'Progres harian yang diinput QS untuk proyek ini.'
      }
      action={
        punyaPeran('QS') ? (
          rencanaBelumLengkap ? (
            <Button size="sm" icon={<Plus className="size-4" />} disabled title="Rencana pekerjaan belum mencapai 100%">
              Tambah Progres
            </Button>
          ) : (
            <Link to={`/progres/baru?project_id=${projectId}`}>
              <Button size="sm" icon={<Plus className="size-4" />}>
                Tambah Progres
              </Button>
            </Link>
          )
        ) : undefined
      }
      bodyClassName="pt-0"
    >
      {!data || data.data.length === 0 ? (
        <EmptyState judul="Belum ada progres" pesan="Progres yang diinput QS akan tampil di sini." />
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
                  <Td className="max-w-56 min-w-40 text-xs text-ink">
                    <DaftarPekerjaan detail={laporan.detail} />
                  </Td>
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
      {data && data.data.length > 0 && (
        <Pagination
          page={data.meta.current_page}
          lastPage={data.meta.last_page}
          total={data.meta.total}
          from={data.meta.from}
          to={data.meta.to}
          onChange={setPage}
        />
      )}
    </Card>
  )
}
