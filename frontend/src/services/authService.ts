import { api, tokenStorage } from '@/lib/api'
import type { User } from '@/types'

export interface LoginPayload {
  email: string
  password: string
  ingatSaya: boolean
}

export const authService = {
  async login({ email, password, ingatSaya }: LoginPayload): Promise<User> {
    const { data } = await api.post<{ token: string; user: User }>('/login', { email, password })
    tokenStorage.set(data.token, ingatSaya)

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
      tokenStorage.clear()
    }
  },

  token(): string | null {
    return tokenStorage.get()
  },
}
