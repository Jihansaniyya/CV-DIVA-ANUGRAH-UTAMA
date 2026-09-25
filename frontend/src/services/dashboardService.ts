import { api } from '@/lib/api'
import type { DashboardData } from '@/types'

export const dashboardService = {
  async get(): Promise<DashboardData> {
    const { data } = await api.get<{ data: DashboardData }>('/dashboard')

    return data.data
  },
}
