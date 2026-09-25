import { Navbar } from '@/components/layout/Navbar'
import { Sidebar } from '@/components/layout/Sidebar'
import { useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'

const JUDUL: Record<string, string> = {
  '/dashboard': 'Dashboard',
  '/proyek': 'Data Proyek',
  '/kurva-s': 'Kurva S',
  '/progres': 'Progres Pekerjaan',
  '/laporan': 'Laporan',
  '/pengguna': 'Data Pengguna',
  '/pengaturan': 'Pengaturan',
}

function judulHalaman(pathname: string): string {
  const cocok = Object.keys(JUDUL).find((awalan) => pathname === awalan || pathname.startsWith(`${awalan}/`))

  return cocok ? JUDUL[cocok] : 'CV Diva Anugrah Utama'
}

export function AppLayout() {
  const [menuTerbuka, setMenuTerbuka] = useState(false)
  const { pathname } = useLocation()

  return (
    <div className="min-h-screen bg-surface">
      <Sidebar open={menuTerbuka} onClose={() => setMenuTerbuka(false)} />

      <div className="lg:pl-64">
        <Navbar onOpenMenu={() => setMenuTerbuka(true)} judul={judulHalaman(pathname)} />
        <main className="mx-auto w-full max-w-[1400px] px-4 py-5 sm:px-6 sm:py-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
