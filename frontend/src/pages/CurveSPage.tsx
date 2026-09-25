import { Card } from '@/components/ui/Card'
import { Select } from '@/components/ui/Field'
import { EmptyState, LoadingState } from '@/components/ui/State'
import { CurveTab } from '@/pages/projects/tabs/CurveTab'
import { useProjects } from '@/hooks/queries'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

/** Halaman Kurva S mandiri dengan pemilih proyek. */
export function CurveSPage() {
  const [params, setParams] = useSearchParams()
  const { data, isLoading } = useProjects({ per_page: 100 })
  const [projectId, setProjectId] = useState<number | null>(params.get('project_id') ? Number(params.get('project_id')) : null)

  useEffect(() => {
    if (!projectId && data && data.data.length > 0) {
      setProjectId(data.data[0].id)
    }
  }, [data, projectId])

  if (isLoading) return <LoadingState />
  if (!data || data.data.length === 0) return <EmptyState judul="Belum ada proyek" pesan="Kurva S tampil setelah proyek dibuat." />

  return (
    <div className="flex flex-col gap-4">
      <Card bodyClassName="p-4">
        <Select
          label="Pilih Proyek"
          value={projectId ?? ''}
          onChange={(event) => {
            const nilai = Number(event.target.value)
            setProjectId(nilai)
            params.set('project_id', String(nilai))
            setParams(params, { replace: true })
          }}
          wrapClassName="sm:max-w-md"
        >
          {data.data.map((project) => (
            <option key={project.id} value={project.id}>
              {project.nama_proyek}
            </option>
          ))}
        </Select>
      </Card>

      {projectId && <CurveTab projectId={projectId} />}
    </div>
  )
}
