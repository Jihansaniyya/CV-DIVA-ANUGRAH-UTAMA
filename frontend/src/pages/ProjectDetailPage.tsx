import { DeviationBadge, StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { ErrorState, LoadingState } from '@/components/ui/State'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { useProject } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { pesanError } from '@/lib/api'
import { CurveTab } from '@/pages/projects/tabs/CurveTab'
import { DocumentationTab } from '@/pages/projects/tabs/DocumentationTab'
import { InfoTab } from '@/pages/projects/tabs/InfoTab'
import { ProjectReportTab } from '@/pages/projects/tabs/ProjectReportTab'
import { ProgressTab } from '@/pages/projects/tabs/ProgressTab'
import { WorkItemsTab } from '@/pages/projects/tabs/WorkItemsTab'
import { WorkPlanTab } from '@/pages/projects/tabs/WorkPlanTab'
import { persen } from '@/utils/format'
import { ArrowLeft } from 'lucide-react'
import { Link, useParams, useSearchParams } from 'react-router-dom'

const TABS: TabItem[] = [
  { key: 'informasi', label: 'Informasi' },
  { key: 'pekerjaan', label: 'Pekerjaan' },
  { key: 'rencana', label: 'Rencana' },
  { key: 'progres', label: 'Progres' },
  { key: 'kurva-s', label: 'Kurva S' },
  { key: 'dokumentasi', label: 'Dokumentasi' },
  { key: 'laporan', label: 'Laporan' },
]

const TAB_QS = ['informasi', 'pekerjaan', 'progres', 'dokumentasi']

export function ProjectDetailPage() {
  const { id } = useParams<{ id: string }>()
  const projectId = Number(id)
  const [params, setParams] = useSearchParams()
  const { punyaPeran } = useAuth()
  const modeQs = punyaPeran('QS')
  const tabs = modeQs ? TABS.filter((item) => TAB_QS.includes(item.key)) : TABS
  const tabDiminta = params.get('tab') ?? 'informasi'
  const tab = tabs.some((item) => item.key === tabDiminta) ? tabDiminta : 'informasi'

  const { data: project, isLoading, error, refetch } = useProject(projectId)

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!project) return null

  return (
    <div className="flex flex-col gap-4">
      <Link to="/proyek" className="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-muted hover:text-ink">
        <ArrowLeft className="size-4" aria-hidden />
        Kembali ke daftar proyek
      </Link>

      <Card bodyClassName="p-4 sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-lg font-semibold text-ink">{project.nama_proyek}</h2>
              <StatusBadge status={project.status} />
            </div>
            <p className="mt-1 text-xs text-muted">
              {project.lokasi} - SPK {project.nomor_spk ?? '-'}
            </p>
          </div>

          {modeQs ? (
            <div className="w-full sm:w-72">
              <p className="text-[11px] text-muted">Progres Realisasi</p>
              <ProgressBar nilai={project.progres_aktual ?? 0} />
            </div>
          ) : (
            <div className="w-full sm:w-72">
              <div className="flex items-center justify-between text-[11px] text-muted">
                <span>Realisasi</span>
                <span>Rencana {persen(project.progres_rencana)}</span>
              </div>
              <ProgressBar nilai={project.progres_aktual ?? 0} pembanding={project.progres_rencana} />
              <div className="mt-2 flex items-center gap-2 text-[11px] text-muted">
                Deviasi <DeviationBadge nilai={project.deviasi ?? null} />
              </div>
            </div>
          )}
        </div>
      </Card>

      <Tabs
        items={tabs}
        active={tab}
        onChange={(key) => {
          params.set('tab', key)
          setParams(params, { replace: true })
        }}
      />

      {tab === 'informasi' && <InfoTab project={project} />}
      {tab === 'pekerjaan' && <WorkItemsTab projectId={projectId} />}
      {tab === 'rencana' && <WorkPlanTab projectId={projectId} />}
      {tab === 'progres' && <ProgressTab projectId={projectId} />}
      {tab === 'kurva-s' && <CurveTab projectId={projectId} />}
      {tab === 'dokumentasi' && <DocumentationTab project={project} />}
      {tab === 'laporan' && <ProjectReportTab projectId={projectId} />}
    </div>
  )
}
