import { api } from '@/lib/api'
import type { Role, Unit } from '@/types'

export const masterService = {
  async roles(): Promise<Role[]> {
    const { data } = await api.get<{ data: Role[] }>('/roles')

    return data.data
  },

  async units(): Promise<Unit[]> {
    const { data } = await api.get<{ data: Unit[] }>('/units')

    return data.data
  },

  async createUnit(payload: { code: string; name: string }): Promise<Unit> {
    const { data } = await api.post<{ message: string; data: Unit }>('/units', payload)

    return data.data
  },
}
