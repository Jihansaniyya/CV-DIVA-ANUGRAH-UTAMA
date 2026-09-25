import { LoadingState } from '@/components/ui/State'
import { useAuth } from '@/hooks/useAuth'
import type { RoleCode } from '@/types'
import { Navigate, Outlet, useLocation } from 'react-router-dom'

/** Hanya pengguna yang sudah login yang dapat mengakses halaman aplikasi. */
export function ProtectedRoute() {
  const { user, siap } = useAuth()
  const location = useLocation()

  if (!siap) {
    return <LoadingState pesan="Memeriksa sesi..." className="min-h-screen" />
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <Outlet />
}

/** Pembatasan tambahan berdasarkan peran (backend tetap melakukan otorisasi sendiri). */
export function RoleRoute({ roles }: { roles: RoleCode[] }) {
  const { user } = useAuth()

  if (user && !roles.includes(user.role_code)) {
    return <Navigate to="/tidak-berwenang" replace />
  }

  return <Outlet />
}
