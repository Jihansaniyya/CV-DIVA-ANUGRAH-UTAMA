import { ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, Select } from '@/components/ui/Field'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useProgressList, useProjects } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { pesanError } from '@/lib/api'
import { persen, tanggalSingkat } from '@/utils/format'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

export function ProgressPage() {
  const { punyaPeran } = useAuth()
  const [page, setPage] = useState(1)
  const [projectId, setProjectId] = useState('')
  const [status, setStatus] = useState('')
  const [dari, setDari] = useState('')
  const [sampai, setSampai] = useState('')

  const { data: projects } = useProjects({ per_page: 100 })
  const { data, isLoading, error, refetch } = useProgressList({
    page,
    per_page: 10,
    project_id: projectId ? Number(projectId) : undefined,
    status: status || undefined,
    dari: dari || undefined,
    sampai: sampai || undefined,
  })

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-ink">Progres Pekerjaan</h2>
          <p className="text-xs text-muted">Daftar laporan progres harian beserta status pengirimannya.</p>
        </div>
        {punyaPeran('QS', 'ADMIN') && (
          <Link to="/progres/baru">
            <Button icon={<Plus className="size-4" />}>Input Progres</Button>
          </Link>
        )}
      </div>

      <Card bodyClassName="pt-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="Proyek"
            value={projectId}
            onChange={(event) => {
              setProjectId(event.target.value)
              setPage(1)
            }}
          >
            <option value="">Semua Proyek</option>
            {projects?.data.map((project) => (
              <option key={project.id} value={project.id}>
                {project.nama_proyek}
              </option>
            ))}
          </Select>
          <Select
            label="Status"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value)
              setPage(1)
            }}
          >
            <option value="">Semua Status</option>
            <option value="DRAFT">Draft</option>
            <option value="DIKIRIM">Dikirim</option>
          </Select>
          <DatePicker label="Dari Tanggal" value={dari} onChange={(event) => setDari(event.target.value)} />
          <DatePicker label="Sampai Tanggal" value={sampai} onChange={(event) => setSampai(event.target.value)} />
        </div>

        <div className="mt-4">
          {isLoading ? (
            <LoadingState />
          ) : error ? (
            <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
          ) : !data || data.data.length === 0 ? (
            <EmptyState judul="Belum ada laporan progres" pesan="Laporan yang diinput QS akan tampil di sini." />
          ) : (
            <>
              <TableWrap>
                <Table>
                  <thead>
                    <tr>
                      <Th>Tanggal</Th>
                      <Th>Proyek</Th>
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
                        <Td>
                          <Link to={`/proyek/${laporan.project_id}`} className="font-medium text-ink hover:text-primary">
                            {laporan.nama_proyek}
                          </Link>
                        </Td>
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

              <Pagination
                page={data.meta.current_page}
                lastPage={data.meta.last_page}
                total={data.meta.total}
                from={data.meta.from}
                to={data.meta.to}
                onChange={setPage}
              />
            </>
          )}
        </div>
      </Card>
    </div>
  )
}
