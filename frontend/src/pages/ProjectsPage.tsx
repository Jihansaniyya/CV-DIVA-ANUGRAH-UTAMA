import { StatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Input, Select } from '@/components/ui/Field'
import { ConfirmDialog } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { ProjectFormModal } from '@/pages/projects/ProjectFormModal'
import { qk, useProjects } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { projectService } from '@/services/projectService'
import type { Project } from '@/types'
import { persen, tanggalSingkat } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Eye, Pencil, Plus, Search, SlidersHorizontal, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

export function ProjectsPage() {
  const { punyaPeran } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const adminMode = punyaPeran('ADMIN')

  const [page, setPage] = useState(1)
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const [filterTerbuka, setFilterTerbuka] = useState(false)
  const [formTerbuka, setFormTerbuka] = useState(false)
  const [proyekDiedit, setProyekDiedit] = useState<Project | null>(null)
  const [proyekDihapus, setProyekDihapus] = useState<Project | null>(null)

  const filter = { page, per_page: 10, q: q || undefined, status: status || undefined }
  const { data, isLoading, error, refetch } = useProjects(filter)

  const hapus = useMutation({
    mutationFn: (id: number) => projectService.remove(id),
    onSuccess: async () => {
      toast.sukses('Proyek berhasil dihapus.')
      setProyekDihapus(null)
      await queryClient.invalidateQueries({ queryKey: ['projects'] })
      await queryClient.invalidateQueries({ queryKey: qk.dashboard })
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  const bukaTambah = () => {
    setProyekDiedit(null)
    setFormTerbuka(true)
  }

  const bukaEdit = (project: Project) => {
    setProyekDiedit(project)
    setFormTerbuka(true)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-ink">Data Proyek</h2>
          <p className="text-xs text-muted">Kelola data proyek yang terdaftar dalam sistem.</p>
        </div>
        {adminMode && (
          <Button icon={<Plus className="size-4" />} onClick={bukaTambah}>
            Tambah Proyek
          </Button>
        )}
      </div>

      <Card bodyClassName="pt-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute top-[11px] left-3 size-4 text-muted" aria-hidden />
            <Input
              placeholder="Cari nama proyek, nomor SPK, atau lokasi..."
              className="pl-9"
              value={q}
              onChange={(event) => {
                setQ(event.target.value)
                setPage(1)
              }}
              aria-label="Cari proyek"
            />
          </div>

          <Button
            variant="outline"
            className="sm:hidden"
            icon={<SlidersHorizontal className="size-4" />}
            onClick={() => setFilterTerbuka((nilai) => !nilai)}
          >
            Filter
          </Button>

          <div className={`${filterTerbuka ? 'flex' : 'hidden'} flex-col gap-3 sm:flex sm:w-56 sm:flex-row`}>
            <Select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
              aria-label="Filter status"
              wrapClassName="w-full"
            >
              <option value="">Semua Status</option>
              <option value="BELUM_DIMULAI">Belum Dimulai</option>
              <option value="BERJALAN">Berjalan</option>
              <option value="SELESAI">Selesai</option>
              <option value="TERLAMBAT">Terlambat</option>
            </Select>
          </div>
        </div>

        <div className="mt-4">
          {isLoading ? (
            <LoadingState />
          ) : error ? (
            <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
          ) : data && data.data.length === 0 ? (
            <EmptyState
              judul="Proyek tidak ditemukan"
              pesan="Ubah kata kunci pencarian atau tambahkan proyek baru."
              aksi={adminMode ? <Button onClick={bukaTambah}>Tambah Proyek</Button> : undefined}
            />
          ) : (
            data && (
              <>
                {/* Tampilan tabel untuk layar besar */}
                <TableWrap className="hidden md:block">
                  <Table>
                    <thead>
                      <tr>
                        <Th>No</Th>
                        <Th>Nama Proyek</Th>
                        <Th>Nomor SPK</Th>
                        <Th>Lokasi</Th>
                        <Th>Tanggal Mulai</Th>
                        <Th>QS</Th>
                        <Th>Progres</Th>
                        <Th align="center">Status</Th>
                        <Th align="center">Aksi</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.data.map((project, index) => (
                        <tr key={project.id} className="hover:bg-surface/60">
                          <Td>{(data.meta.from ?? 1) + index}</Td>
                          <Td>
                            <Link to={`/proyek/${project.id}`} className="font-medium text-ink hover:text-primary">
                              {project.nama_proyek}
                            </Link>
                          </Td>
                          <Td className="text-muted">{project.nomor_spk ?? '-'}</Td>
                          <Td className="text-muted">{project.lokasi}</Td>
                          <Td className="text-muted">{tanggalSingkat(project.tanggal_mulai)}</Td>
                          <Td className="text-muted">{project.qs?.name ?? '-'}</Td>
                          <Td className="min-w-40">
                            <ProgressBar nilai={project.progres_aktual ?? 0} pembanding={project.progres_rencana} />
                            <p className="mt-0.5 text-[10px] text-muted">Rencana {persen(project.progres_rencana)}</p>
                          </Td>
                          <Td align="center">
                            <StatusBadge status={project.status} />
                          </Td>
                          <Td align="center">
                            <div className="flex items-center justify-center gap-1">
                              <Link to={`/proyek/${project.id}`} aria-label="Lihat detail" className="rounded-lg p-1.5 text-muted hover:bg-surface hover:text-navy">
                                <Eye className="size-4" aria-hidden />
                              </Link>
                              {adminMode && (
                                <>
                                  <button type="button" onClick={() => bukaEdit(project)} aria-label="Ubah proyek" className="rounded-lg p-1.5 text-muted hover:bg-surface hover:text-navy">
                                    <Pencil className="size-4" aria-hidden />
                                  </button>
                                  <button type="button" onClick={() => setProyekDihapus(project)} aria-label="Hapus proyek" className="rounded-lg p-1.5 text-muted hover:bg-danger-soft hover:text-danger">
                                    <Trash2 className="size-4" aria-hidden />
                                  </button>
                                </>
                              )}
                            </div>
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </TableWrap>

                {/* Tampilan kartu untuk layar kecil */}
                <ul className="flex flex-col gap-3 md:hidden">
                  {data.data.map((project) => (
                    <li key={project.id} className="app-card p-4">
                      <div className="flex items-start justify-between gap-3">
                        <Link to={`/proyek/${project.id}`} className="text-sm font-semibold text-ink">
                          {project.nama_proyek}
                        </Link>
                        <StatusBadge status={project.status} />
                      </div>
                      <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-muted">
                        <div>
                          <dt className="inline">SPK: </dt>
                          <dd className="inline">{project.nomor_spk ?? '-'}</dd>
                        </div>
                        <div>
                          <dt className="inline">Lokasi: </dt>
                          <dd className="inline">{project.lokasi}</dd>
                        </div>
                        <div>
                          <dt className="inline">Mulai: </dt>
                          <dd className="inline">{tanggalSingkat(project.tanggal_mulai)}</dd>
                        </div>
                        <div>
                          <dt className="inline">QS: </dt>
                          <dd className="inline">{project.qs?.name ?? '-'}</dd>
                        </div>
                      </dl>
                      <div className="mt-3">
                        <ProgressBar nilai={project.progres_aktual ?? 0} pembanding={project.progres_rencana} />
                        <p className="mt-1 text-[10px] text-muted">Rencana {persen(project.progres_rencana)}</p>
                      </div>
                      {adminMode && (
                        <div className="mt-3 flex gap-2">
                          <Button variant="outline" size="sm" block onClick={() => bukaEdit(project)}>
                            Ubah
                          </Button>
                          <Button variant="outline" size="sm" block className="text-danger" onClick={() => setProyekDihapus(project)}>
                            Hapus
                          </Button>
                        </div>
                      )}
                    </li>
                  ))}
                </ul>

                <Pagination
                  page={data.meta.current_page}
                  lastPage={data.meta.last_page}
                  total={data.meta.total}
                  from={data.meta.from}
                  to={data.meta.to}
                  onChange={setPage}
                />
              </>
            )
          )}
        </div>
      </Card>

      <ProjectFormModal open={formTerbuka} project={proyekDiedit} onClose={() => setFormTerbuka(false)} />

      <ConfirmDialog
        open={Boolean(proyekDihapus)}
        title="Hapus Proyek"
        pesan={`Proyek "${proyekDihapus?.nama_proyek ?? ''}" beserta pekerjaan, rencana, dan laporannya akan dihapus. Lanjutkan?`}
        loading={hapus.isPending}
        onConfirm={() => proyekDihapus && hapus.mutate(proyekDihapus.id)}
        onClose={() => setProyekDihapus(null)}
      />
    </div>
  )
}
