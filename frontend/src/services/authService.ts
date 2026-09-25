import { api, TOKEN_KEY } from '@/lib/api'
import type { User } from '@/types'

export interface LoginPayload {
  username: string
  password: string
}

export const authService = {
  async login(payload: LoginPayload): Promise<User> {
    const { data } = await api.post<{ token: string; user: User }>('/login', payload)
    localStorage.setItem(TOKEN_KEY, data.token)

    return data.user
  },

  async me(): Promise<User> {
    const { data } = await api.get<{ user: User }>('/me')

    return data.user
  },

  async logout(): Promise<void> {
    try {
      await api.post('/logout')
    } finally {
      localStorage.removeItem(TOKEN_KEY)
    }
  },

  token(): string | null {
    return localStorage.getItem(TOKEN_KEY)
  },
}
