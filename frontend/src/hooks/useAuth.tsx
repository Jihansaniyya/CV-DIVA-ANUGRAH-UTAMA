import { authService } from '@/services/authService'
import type { RoleCode, User } from '@/types'
import { useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'

interface AuthContextValue {
  user: User | null
  siap: boolean
  login: (username: string, password: string) => Promise<User>
  logout: () => Promise<void>
  punyaPeran: (...peran: RoleCode[]) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [siap, setSiap] = useState(false)
  const queryClient = useQueryClient()

  useEffect(() => {
    let batal = false

    async function muat() {
      if (!authService.token()) {
        setSiap(true)

        return
      }

      try {
        const data = await authService.me()

        if (!batal) setUser(data)
      } catch {
        if (!batal) setUser(null)
      } finally {
        if (!batal) setSiap(true)
      }
    }

    void muat()

    return () => {
      batal = true
    }
  }, [])

  const login = useCallback(
    async (username: string, password: string) => {
      const data = await authService.login({ username, password })
      setUser(data)
      queryClient.clear()

      return data
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    await authService.logout()
    setUser(null)
    queryClient.clear()
  }, [queryClient])

  const punyaPeran = useCallback((...peran: RoleCode[]) => (user ? peran.includes(user.role_code) : false), [user])

  const value = useMemo<AuthContextValue>(() => ({ user, siap, login, logout, punyaPeran }), [user, siap, login, logout, punyaPeran])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth harus dipakai di dalam AuthProvider.')
  }

  return context
}
