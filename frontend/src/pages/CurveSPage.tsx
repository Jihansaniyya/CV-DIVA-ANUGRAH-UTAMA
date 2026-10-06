import { StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { Dropdown } from '@/components/ui/Dropdown'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { useProjects } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import { CurveTab } from '@/pages/projects/tabs/CurveTab'
import { rentangTanggal } from '@/utils/format'
import { CalendarRange, MapPin } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

/** Halaman Kurva S mandiri dengan pemilih proyek (hanya pemantauan). */
export function CurveSPage() {
  const [params, setParams] = useSearchParams()
  const { data, isLoading, error, refetch } = useProjects({ per_page: 100 })
  const [projectId, setProjectId] = useState<number | null>(params.get('project_id') ? Number(params.get('project_id')) : null)

  useEffect(() => {
    if (!projectId && data && data.data.length > 0) {
      setProjectId(data.data[0].id)
    }
  }, [data, projectId])

  if (isLoading) return <LoadingState pesan="Memuat daftar proyek..." />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data || data.data.length === 0) return <EmptyState judul="Belum ada proyek" pesan="Kurva S tampil setelah proyek dibuat." />

  const proyek = data.data.find((item) => item.id === projectId)

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h2 className="text-lg font-semibold text-ink">Kurva S</h2>
        <p className="text-xs text-muted">Perbandingan progres rencana dan realisasi.</p>
      </div>
      <Card>
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between lg:gap-6">
          <Dropdown
            label="Proyek"
            value={projectId ? String(projectId) : ''}
            options={data.data.map((project) => ({ value: String(project.id), label: project.nama_proyek }))}
            onChange={(nilai) => {
              setProjectId(Number(nilai))
              params.set('project_id', nilai)
              setParams(params, { replace: true })
            }}
            placeholder="Pilih proyek"
            wrapClassName="w-full lg:max-w-xl"
          />

          {proyek && (
            <dl className="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-muted lg:justify-end lg:pb-2">
              <div className="flex items-center gap-1.5">
                <dt>
                  <MapPin className="size-3.5" aria-label="Lokasi" />
                </dt>
                <dd>{proyek.lokasi}</dd>
              </div>
              <div className="flex items-center gap-1.5">
                <dt>
                  <CalendarRange className="size-3.5" aria-label="Jadwal" />
                </dt>
                <dd>{rentangTanggal(proyek.tanggal_mulai, proyek.tanggal_selesai)}</dd>
              </div>
              <div>
                <dt className="sr-only">Status</dt>
                <dd>
                  <StatusBadge status={proyek.status} />
                </dd>
              </div>
            </dl>
          )}
        </div>
      </Card>

      {projectId && <CurveTab projectId={projectId} />}
    </div>
  )
}
