import { api } from '@/lib/api'
import type { Paginated, User } from '@/types'

export interface UserFilter {
  q?: string
  role?: string
  status?: string
  page?: number
  per_page?: number
}

export interface UserPayload {
  name: string
  username: string
  email?: string | null
  phone?: string | null
  password?: string
  password_confirmation?: string
  role_id: number
  is_active: boolean
}

export const userService = {
  async list(filter: UserFilter = {}): Promise<Paginated<User>> {
    const { data } = await api.get<Paginated<User>>('/users', { params: filter })

    return data
  },

  async create(payload: UserPayload): Promise<User> {
    const { data } = await api.post<{ data: User }>('/users', payload)

    return data.data
  },

  async update(id: number, payload: Partial<UserPayload>): Promise<User> {
    const { data } = await api.put<{ data: User }>(`/users/${id}`, payload)

    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`/users/${id}`)
  },

  async toggleActive(id: number): Promise<User> {
    const { data } = await api.patch<{ data: User }>(`/users/${id}/toggle-active`)

    return data.data
  },
}
