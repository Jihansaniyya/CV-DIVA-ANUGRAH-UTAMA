import { api } from '@/lib/api'
import type { FinalReport, MonthlyReport, ReportDocument, WeeklyReport } from '@/types'

export const reportService = {
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

  async final(projectId: number): Promise<FinalReport> {
    const { data } = await api.get<{ data: FinalReport }>('/reports/final', {
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

  async exportFinalExcel(projectId: number): Promise<ReportDocument> {
    const { data } = await api.post<{ data: ReportDocument }>('/reports/export/final', { project_id: projectId })

    return data.data
  },
}

export interface ExportParams {
  tipe: 'MINGGUAN' | 'BULANAN'
  project_id: number
  period_id?: number
  bulan_ke?: number
}
