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
import type { ProgressReport } from '@/types'
import { cn } from '@/utils/cn'
import { persen, tanggalSingkat } from '@/utils/format'
import { ChevronRight, Eye, Plus, RotateCcw } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

function bobot(laporan: ProgressReport): string {
  return laporan.total_bobot_realisasi > 0 && laporan.total_bobot_realisasi < 0.01 ? '< 0,01%' : persen(laporan.total_bobot_realisasi)
}

export function ProgressPage() {
  const { punyaPeran } = useAuth()
  const tampilPelapor = !punyaPeran('QS')
  const kontraktor = punyaPeran('KONTRAKTOR')
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

  const adaFilter = Boolean(projectId || status || dari || sampai)

  const ubah = (setter: (nilai: string) => void) => (nilai: string) => {
    setter(nilai)
    setPage(1)
  }

  const resetFilter = () => {
    setProjectId('')
    setStatus('')
    setDari('')
    setSampai('')
    setPage(1)
  }

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
          <div
            className={cn(
              'grid flex-1 grid-cols-2 gap-3',
              kontraktor ? 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]',
            )}
          >
            <Select label="Proyek" value={projectId} onChange={(event) => ubah(setProjectId)(event.target.value)} wrapClassName="col-span-2 lg:col-span-1">
              <option value="">Semua proyek</option>
              {projects?.data.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.nama_proyek}
                </option>
              ))}
            </Select>
            {!kontraktor && (
              <Select label="Status" value={status} onChange={(event) => ubah(setStatus)(event.target.value)} wrapClassName="col-span-2 sm:col-span-1">
                <option value="">Semua status</option>
                <option value="DRAFT">Draf</option>
                <option value="DIKIRIM">Dikirim</option>
              </Select>
            )}
            <DatePicker label="Dari tanggal" value={dari} max={sampai || undefined} onChange={(event) => ubah(setDari)(event.target.value)} />
            <DatePicker label="Sampai tanggal" value={sampai} min={dari || undefined} onChange={(event) => ubah(setSampai)(event.target.value)} />
          </div>
          {adaFilter && (
            <Button variant="ghost" icon={<RotateCcw className="size-4" />} onClick={resetFilter} className="self-start lg:self-end">
              Reset filter
            </Button>
          )}
        </div>
      </Card>

      <Card
        title="Daftar Laporan Progres"
        description={
          kontraktor
            ? 'Hanya laporan yang sudah dikirim QS. Buka detail untuk melihat foto, waktu, dan kendala pekerjaan.'
            : 'Progres harian beserta status pengirimannya.'
        }
        action={
          punyaPeran('QS', 'ADMIN') ? (
            <Link to="/progres/baru">
              <Button size="sm" icon={<Plus className="size-4" />}>
                Tambah Progres
              </Button>
            </Link>
          ) : data ? (
            <span className="text-xs text-muted">{data.meta.total} laporan</span>
          ) : undefined
        }
        flush
      >
        {isLoading ? (
          <LoadingState pesan="Memuat laporan progres..." />
        ) : error ? (
          <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
        ) : !data || data.data.length === 0 ? (
          adaFilter ? (
            <EmptyState
              judul="Tidak ada data"
              pesan="Belum ada data progres pada filter yang dipilih."
              aksi={
                <Button variant="outline" size="sm" icon={<RotateCcw className="size-4" />} onClick={resetFilter}>
                  Reset filter
                </Button>
              }
            />
          ) : (
            <EmptyState judul="Belum ada progres" pesan="Progres yang dikirim QS akan tampil di sini." />
          )
        ) : (
          <>
            <ul className="divide-y divide-line md:hidden">
              {data.data.map((laporan) => (
                <li key={laporan.id}>
                  <Link to={`/progres/${laporan.id}`} className="flex items-center gap-3 px-4 py-3.5 hover:bg-surface/60">
                    <div className="min-w-0 flex-1">
                      <p className="text-xs text-muted">
                        {tanggalSingkat(laporan.tanggal_laporan)} · {laporan.periode ?? 'Di luar periode'}
                      </p>
                      <p className="mt-0.5 line-clamp-2 text-sm leading-snug font-medium text-ink">{laporan.nama_proyek}</p>
                      <p className="mt-0.5 text-xs text-muted">
                        {tampilPelapor && <>{laporan.pelapor ?? '-'} · </>}
                        {laporan.detail?.length ?? 0} pekerjaan
                      </p>
                    </div>
                    <div className="flex flex-col items-end gap-1.5">
                      <span className="text-sm font-semibold text-ink tabular-nums">{bobot(laporan)}</span>
                      <ReportStatusBadge status={laporan.status} />
                    </div>
                    <ChevronRight className="size-4 shrink-0 text-muted" aria-hidden />
                  </Link>
                </li>
              ))}
            </ul>

            <TableWrap flush className="hidden md:block">
              <Table>
                <thead>
                  <tr>
                    <Th className="w-28">Tanggal</Th>
                    <Th className="min-w-64">Proyek</Th>
                    <Th>Periode</Th>
                    {tampilPelapor && <Th>Pelapor</Th>}
                    <Th align="center">Pekerjaan</Th>
                    <Th align="right">Bobot Realisasi</Th>
                    <Th align="center">Status</Th>
                    <Th align="right">
                      <span className="sr-only">Aksi</span>
                    </Th>
                  </tr>
                </thead>
                <tbody>
                  {data.data.map((laporan) => (
                    <tr key={laporan.id} className="hover:bg-surface/60">
                      <Td className="whitespace-nowrap">{tanggalSingkat(laporan.tanggal_laporan)}</Td>
                      <Td className="max-w-md">
                        {kontraktor ? (
                          <p title={laporan.nama_proyek ?? undefined} className="line-clamp-2 leading-snug font-medium text-ink">
                            {laporan.nama_proyek}
                          </p>
                        ) : (
                          <Link
                            to={`/proyek/${laporan.project_id}`}
                            title={laporan.nama_proyek ?? undefined}
                            className="line-clamp-2 leading-snug font-medium text-ink hover:text-primary"
                          >
                            {laporan.nama_proyek}
                          </Link>
                        )}
                      </Td>
                      <Td className="whitespace-nowrap text-muted">{laporan.periode ?? 'Di luar periode'}</Td>
                      {tampilPelapor && <Td className="whitespace-nowrap text-muted">{laporan.pelapor ?? '-'}</Td>}
                      <Td align="center" className="text-muted">
                        {laporan.detail?.length ?? 0}
                      </Td>
                      <Td align="right" className="font-semibold whitespace-nowrap">
                        {bobot(laporan)}
                      </Td>
                      <Td align="center">
                        <ReportStatusBadge status={laporan.status} />
                      </Td>
                      <Td align="right">
                        <Link
                          to={`/progres/${laporan.id}`}
                          className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-white px-3 text-xs font-medium text-ink transition-colors hover:border-primary/40 hover:text-primary"
                        >
                          <Eye className="size-3.5" aria-hidden />
                          Detail
                        </Link>
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </TableWrap>

            <div className="border-t border-line px-4 sm:px-5">
              <Pagination
                page={data.meta.current_page}
                lastPage={data.meta.last_page}
                total={data.meta.total}
                from={data.meta.from}
                to={data.meta.to}
                onChange={setPage}
              />
            </div>
          </>
        )}
      </Card>
    </div>
  )
}
