import { dashboardService } from '@/services/dashboardService'
import { masterService } from '@/services/masterService'
import { progressService, type ProgressFilter } from '@/services/progressService'
import { projectService, type ProjectFilter } from '@/services/projectService'
import { reportService } from '@/services/reportService'
import { userService, type UserFilter } from '@/services/userService'
import { useQuery } from '@tanstack/react-query'

/** Kunci cache TanStack Query terpusat agar invalidasi konsisten. */
export const qk = {
  dashboard: ['dashboard'] as const,
  roles: ['roles'] as const,
  units: ['units'] as const,
  users: (filter: UserFilter) => ['users', filter] as const,
  projects: (filter: ProjectFilter) => ['projects', filter] as const,
  project: (id: number) => ['project', id] as const,
  workItems: (id: number) => ['project', id, 'work-items'] as const,
  categories: (id: number) => ['project', id, 'categories'] as const,
  periods: (id: number) => ['project', id, 'periods'] as const,
  workPlans: (id: number) => ['project', id, 'work-plans'] as const,
  curve: (id: number) => ['project', id, 'curve-s'] as const,
  milestones: (id: number) => ['project', id, 'milestones'] as const,
  progressList: (filter: ProgressFilter) => ['progress', filter] as const,
  progress: (id: number) => ['progress', id] as const,
  reportDaily: (id: number, dari?: string, sampai?: string) => ['report', 'daily', id, dari, sampai] as const,
  reportWeekly: (id: number, periodId?: number) => ['report', 'weekly', id, periodId] as const,
  reportMonthly: (id: number, bulan: number) => ['report', 'monthly', id, bulan] as const,
  reportMilestone: (id: number) => ['report', 'milestone', id] as const,
  documents: (id?: number) => ['report', 'documents', id] as const,
}

export const useDashboard = () => useQuery({ queryKey: qk.dashboard, queryFn: dashboardService.get })

export const useRoles = () => useQuery({ queryKey: qk.roles, queryFn: masterService.roles, staleTime: 30 * 60 * 1000 })

export const useUnits = () => useQuery({ queryKey: qk.units, queryFn: masterService.units, staleTime: 30 * 60 * 1000 })

export const useUsers = (filter: UserFilter) => useQuery({ queryKey: qk.users(filter), queryFn: () => userService.list(filter) })

export const useProjects = (filter: ProjectFilter) =>
  useQuery({ queryKey: qk.projects(filter), queryFn: () => projectService.list(filter) })

export const useProject = (id: number | null) =>
  useQuery({ queryKey: qk.project(id ?? 0), queryFn: () => projectService.detail(id as number), enabled: Boolean(id) })

export const useWorkItems = (id: number | null) =>
  useQuery({ queryKey: qk.workItems(id ?? 0), queryFn: () => projectService.workItems(id as number), enabled: Boolean(id) })

export const useCategories = (id: number | null) =>
  useQuery({ queryKey: qk.categories(id ?? 0), queryFn: () => projectService.categories(id as number), enabled: Boolean(id) })

export const usePeriods = (id: number | null) =>
  useQuery({ queryKey: qk.periods(id ?? 0), queryFn: () => projectService.periods(id as number), enabled: Boolean(id) })

export const useWorkPlans = (id: number | null) =>
  useQuery({ queryKey: qk.workPlans(id ?? 0), queryFn: () => projectService.workPlans(id as number), enabled: Boolean(id) })

export const useCurveS = (id: number | null) =>
  useQuery({ queryKey: qk.curve(id ?? 0), queryFn: () => projectService.curveS(id as number), enabled: Boolean(id) })

export const useMilestones = (id: number | null) =>
  useQuery({ queryKey: qk.milestones(id ?? 0), queryFn: () => projectService.milestones(id as number), enabled: Boolean(id) })

export const useProgressList = (filter: ProgressFilter) =>
  useQuery({ queryKey: qk.progressList(filter), queryFn: () => progressService.list(filter) })

export const useProgress = (id: number | null) =>
  useQuery({ queryKey: qk.progress(id ?? 0), queryFn: () => progressService.detail(id as number), enabled: Boolean(id) })

export const useDailyReport = (id: number | null, dari?: string, sampai?: string) =>
  useQuery({
    queryKey: qk.reportDaily(id ?? 0, dari, sampai),
    queryFn: () => reportService.daily(id as number, dari, sampai),
    enabled: Boolean(id),
  })

export const useWeeklyReport = (id: number | null, periodId?: number) =>
  useQuery({
    queryKey: qk.reportWeekly(id ?? 0, periodId),
    queryFn: () => reportService.weekly(id as number, periodId),
    enabled: Boolean(id),
  })

export const useMonthlyReport = (id: number | null, bulan: number) =>
  useQuery({
    queryKey: qk.reportMonthly(id ?? 0, bulan),
    queryFn: () => reportService.monthly(id as number, bulan),
    enabled: Boolean(id),
  })

export const useMilestoneReport = (id: number | null) =>
  useQuery({
    queryKey: qk.reportMilestone(id ?? 0),
    queryFn: () => reportService.milestone(id as number),
    enabled: Boolean(id),
  })

export const useReportDocuments = (id?: number) =>
  useQuery({ queryKey: qk.documents(id), queryFn: () => reportService.documents(id) })
