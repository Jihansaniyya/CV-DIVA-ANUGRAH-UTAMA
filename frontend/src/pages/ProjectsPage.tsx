import { ActionMenu, type ActionMenuItem } from '@/components/ui/ActionMenu'
import { StatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Dropdown } from '@/components/ui/Dropdown'
import { Select } from '@/components/ui/Field'
import { LegendaLevel, RencanaRealisasiBar } from '@/components/ui/LevelBar'
import { ConfirmDialog } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { ProjectFormModal } from '@/pages/projects/ProjectFormModal'
import { qk, useProjectOptions, useProjects } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { projectService } from '@/services/projectService'
import type { Project } from '@/types'
import { tanggalSingkat } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Eye, Pencil, Plus, SlidersHorizontal, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'

/** Tanggal selesai hanya ditampilkan bila proyek sudah berstatus Selesai. */
function tanggalSelesai(project: Project): string {
  return project.status === 'SELESAI' ? tanggalSingkat(project.tanggal_selesai) : '-'
}

export function ProjectsPage() {
  const { punyaPeran } = useAuth()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()
  const adminMode = punyaPeran('ADMIN')

  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [projectId, setProjectId] = useState('')
  const [status, setStatus] = useState('')
  const [filterTerbuka, setFilterTerbuka] = useState(false)
  const [formTerbuka, setFormTerbuka] = useState(false)
  const [proyekDiedit, setProyekDiedit] = useState<Project | null>(null)
  const [proyekDihapus, setProyekDihapus] = useState<Project | null>(null)

  // Bila satu proyek dipilih, backend hanya mengembalikan proyek itu (tetap mengikuti filter status).
  const filter = { page, per_page: perPage, status: status || undefined, id: projectId ? Number(projectId) : undefined }
  const { data, isLoading, error, refetch } = useProjects(filter)
  // Semua proyek yang boleh diakses (tanpa batas 100), sebagai pilihan dropdown proyek.
  const { data: semuaProyek } = useProjectOptions()
  const opsiProyek = (semuaProyek ?? []).map((project) => ({ value: String(project.id), label: project.nama_proyek }))

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

  // Tabel dan kartu sama untuk semua peran; QS dan Kontraktor hanya mendapat aksi Lihat Detail.
  const aksiProyek = (project: Project): ActionMenuItem[] => [
    { label: 'Lihat Detail', icon: <Eye className="size-4" aria-hidden />, onSelect: () => navigate(`/proyek/${project.id}`) },
    ...(adminMode
      ? [
          { label: 'Ubah', icon: <Pencil className="size-4" aria-hidden />, onSelect: () => bukaEdit(project) },
          { label: 'Hapus', icon: <Trash2 className="size-4" aria-hidden />, onSelect: () => setProyekDihapus(project), danger: true },
        ]
      : []),
  ]

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
          <Dropdown
            value={projectId}
            options={opsiProyek}
            onChange={(nilai) => {
              setProjectId(nilai)
              setPage(1)
            }}
            label="Proyek"
            placeholder="Semua proyek"
            allowEmpty
            wrapClassName="min-w-0 flex-1"
          />

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
              label="Status"
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
              pesan="Ubah pilihan proyek atau status, atau tambahkan proyek baru."
              aksi={adminMode ? <Button onClick={bukaTambah}>Tambah Proyek</Button> : undefined}
            />
          ) : (
            data && (
              <>
                {/* Tampilan tabel untuk layar lebar: lebar kolom tetap agar tabel muat tanpa digulir ke samping */}
                <TableWrap className="hidden xl:block">
                  <Table className="w-full table-fixed">
                    <thead>
                      <tr>
                        <Th className="w-12">No</Th>
                        <Th>Nama Proyek</Th>
                        <Th className="w-[15%]">Lokasi</Th>
                        <Th className="w-36">Tanggal</Th>
                        <Th className="w-[11%]">QS</Th>
                        <Th className="w-40">Progres</Th>
                        <Th align="center" className="w-24">Status</Th>
                        <Th align="center" className="w-14">
                          Aksi
                        </Th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.data.map((project, index) => (
                        <tr key={project.id} className="hover:bg-surface/60">
                          <Td>{(data.meta.from ?? 1) + index}</Td>
                          <Td>
                            {/* Nama proyek bisa sampai 255 karakter: dibungkus maksimal 3 baris, teks lengkap lewat title. */}
                            <Link
                              to={`/proyek/${project.id}`}
                              title={project.nama_proyek}
                              className="line-clamp-3 leading-snug font-medium wrap-anywhere text-ink hover:text-primary"
                            >
                              {project.nama_proyek}
                            </Link>
                          </Td>
                          <Td className="break-words text-muted">{project.lokasi}</Td>
                          <Td className="text-xs text-muted">
                            <p className="whitespace-nowrap">Mulai: {tanggalSingkat(project.tanggal_mulai)}</p>
                            <p className="mt-0.5 whitespace-nowrap">Selesai: {tanggalSelesai(project)}</p>
                          </Td>
                          <Td className="break-words text-muted">{project.qs?.name ?? '-'}</Td>
                          <Td>
                            <RencanaRealisasiBar rencana={project.total_bobot_rencana ?? 0} realisasi={project.progres_aktual ?? 0} />
                          </Td>
                          <Td align="center">
                            <StatusBadge status={project.status} bertumpuk />
                          </Td>
                          <Td align="center">
                            <ActionMenu items={aksiProyek(project)} label={`Aksi untuk ${project.nama_proyek}`} />
                          </Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                </TableWrap>

                {/* Tampilan kartu untuk layar kecil */}
                <ul className="grid gap-3 md:grid-cols-2 xl:hidden">
                  {data.data.map((project) => (
                    <li key={project.id} className="app-card p-4">
                      <div className="flex items-start justify-between gap-2">
                        <Link
                          to={`/proyek/${project.id}`}
                          title={project.nama_proyek}
                          className="line-clamp-3 min-w-0 flex-1 text-sm leading-snug font-semibold wrap-anywhere text-ink"
                        >
                          {project.nama_proyek}
                        </Link>
                        <StatusBadge status={project.status} bertumpuk />
                        <div className="-mt-1 -mr-2 shrink-0">
                          <ActionMenu items={aksiProyek(project)} label={`Aksi untuk ${project.nama_proyek}`} />
                        </div>
                      </div>
                      <dl className="mt-2 flex flex-col gap-y-1 text-[11px] text-muted">
                        <div>
                          <dt className="inline">Lokasi: </dt>
                          <dd className="inline">{project.lokasi}</dd>
                        </div>
                        <div className="grid grid-cols-2 gap-x-3">
                          <div>
                            <dt className="inline">Mulai: </dt>
                            <dd className="inline">{tanggalSingkat(project.tanggal_mulai)}</dd>
                          </div>
                          <div>
                            <dt className="inline">Selesai: </dt>
                            <dd className="inline">{tanggalSelesai(project)}</dd>
                          </div>
                        </div>
                        <div>
                          <dt className="inline">QS: </dt>
                          <dd className="inline">{project.qs?.name ?? '-'}</dd>
                        </div>
                      </dl>
                      <RencanaRealisasiBar
                        className="mt-3"
                        rencana={project.total_bobot_rencana ?? 0}
                        realisasi={project.progres_aktual ?? 0}
                      />
                    </li>
                  ))}
                </ul>

                <LegendaLevel className="mt-3" />

                <Pagination
                  page={data.meta.current_page}
                  lastPage={data.meta.last_page}
                  total={data.meta.total}
                  from={data.meta.from}
                  to={data.meta.to}
                  onChange={setPage}
                  perPage={perPage}
                  onPerPageChange={(jumlah) => {
                    setPerPage(jumlah)
                    setPage(1)
                  }}
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
