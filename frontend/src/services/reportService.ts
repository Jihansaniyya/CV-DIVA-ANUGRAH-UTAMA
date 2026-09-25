import { api } from '@/lib/api'
import type { DailyReport, MilestoneReport, MonthlyReport, ReportDocument, WeeklyReport } from '@/types'

export const reportService = {
  async daily(projectId: number, dari?: string, sampai?: string): Promise<DailyReport> {
    const { data } = await api.get<{ data: DailyReport }>('/reports/daily', {
      params: { project_id: projectId, dari, sampai },
    })

    return data.data
  },

  async weekly(projectId: number, periodId?: number): Promise<WeeklyReport> {
    const { data } = await api.get<{ data: WeeklyReport }>('/reports/weekly', {
      params: { project_id: projectId, period_id: periodId },
    })

    return data.data
  },

  async monthly(projectId: number, bulanKe: number): Promise<MonthlyReport> {
    const { data } = await api.get<{ data: MonthlyReport }>('/reports/monthly', {
      params: { project_id: projectId, bulan_ke: bulanKe },
    })

    return data.data
  },

  async milestone(projectId: number): Promise<MilestoneReport> {
    const { data } = await api.get<{ data: MilestoneReport }>('/reports/milestone', {
      params: { project_id: projectId },
    })

    return data.data
  },

  async documents(projectId?: number): Promise<ReportDocument[]> {
    const { data } = await api.get<{ data: ReportDocument[] }>('/reports/documents', {
      params: { project_id: projectId },
    })

    return data.data
  },

  async exportExcel(params: ExportParams): Promise<ReportDocument> {
    const { data } = await api.post<{ data: ReportDocument }>('/reports/export/excel', params)

    return data.data
  },

  async exportWord(params: ExportParams): Promise<ReportDocument> {
    const { data } = await api.post<{ data: ReportDocument }>('/reports/export/word', params)

    return data.data
  },
}

export interface ExportParams {
  tipe: 'HARIAN' | 'MINGGUAN' | 'BULANAN'
  project_id: number
  period_id?: number
  bulan_ke?: number
  dari?: string
  sampai?: string
}
