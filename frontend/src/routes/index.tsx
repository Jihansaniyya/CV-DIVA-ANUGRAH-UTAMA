import { AppLayout } from '@/components/layout/AppLayout'
import { CurveSPage } from '@/pages/CurveSPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { LoginPage } from '@/pages/LoginPage'
import { NotAuthorizedPage } from '@/pages/NotAuthorizedPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { ProgressDetailPage } from '@/pages/ProgressDetailPage'
import { ProgressFormPage } from '@/pages/ProgressFormPage'
import { ProgressPage } from '@/pages/ProgressPage'
import { ProjectDetailPage } from '@/pages/ProjectDetailPage'
import { ProjectsPage } from '@/pages/ProjectsPage'
import { ReportsPage } from '@/pages/ReportsPage'
import { SettingsPage } from '@/pages/SettingsPage'
import { UsersPage } from '@/pages/UsersPage'
import { ProtectedRoute, RoleRoute } from '@/routes/ProtectedRoute'
import { Navigate, Route, Routes } from 'react-router-dom'

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />

          <Route path="/proyek" element={<ProjectsPage />} />
          <Route path="/proyek/:id" element={<ProjectDetailPage />} />

          <Route element={<RoleRoute roles={['ADMIN', 'KONTRAKTOR']} />}>
            <Route path="/kurva-s" element={<CurveSPage />} />
          </Route>

          <Route path="/progres" element={<ProgressPage />} />
          <Route element={<RoleRoute roles={['ADMIN', 'QS']} />}>
            <Route path="/progres/baru" element={<ProgressFormPage />} />
          </Route>
          <Route path="/progres/:id" element={<ProgressDetailPage />} />

          <Route element={<RoleRoute roles={['ADMIN', 'KONTRAKTOR']} />}>
            <Route path="/laporan" element={<ReportsPage />} />
          </Route>

          <Route element={<RoleRoute roles={['ADMIN']} />}>
            <Route path="/pengguna" element={<UsersPage />} />
            <Route path="/pengaturan" element={<SettingsPage />} />
          </Route>

          <Route path="/tidak-berwenang" element={<NotAuthorizedPage />} />
          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
