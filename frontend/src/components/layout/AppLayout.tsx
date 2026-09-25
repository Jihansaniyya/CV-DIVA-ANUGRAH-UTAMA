import { Navbar } from '@/components/layout/Navbar'
import { Sidebar } from '@/components/layout/Sidebar'
import { ConfirmDialog } from '@/components/ui/Modal'
import { WelcomePopup } from '@/components/ui/WelcomePopup'
import { useAuth } from '@/hooks/useAuth'
import { LABEL_PERAN } from '@/utils/peran'
import { useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'

const JUDUL: Record<string, string> = {
  '/dashboard': 'Beranda',
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
  const [konfirmasiKeluar, setKonfirmasiKeluar] = useState(false)
  const [sedangKeluar, setSedangKeluar] = useState(false)
  const { pathname } = useLocation()
  const { user, logout } = useAuth()

  const keluar = async () => {
    setSedangKeluar(true)

    try {
      await logout()
    } finally {
      setSedangKeluar(false)
      setKonfirmasiKeluar(false)
    }
  }

  return (
    <div className="min-h-screen bg-surface">
      <Sidebar open={menuTerbuka} onClose={() => setMenuTerbuka(false)} onLogout={() => setKonfirmasiKeluar(true)} />

      <div className="lg:pl-64">
        <Navbar onOpenMenu={() => setMenuTerbuka(true)} onLogout={() => setKonfirmasiKeluar(true)} judul={judulHalaman(pathname)} />
        <main className="mx-auto w-full max-w-[1400px] px-4 py-5 sm:px-6 sm:py-6">
          <Outlet />
        </main>
      </div>

      <WelcomePopup nama={user?.name} keterangan={LABEL_PERAN[user?.role_code ?? '']} />

      <ConfirmDialog
        open={konfirmasiKeluar}
        title="Keluar dari aplikasi?"
        pesan={`Anda akan keluar dari akun ${user?.name ?? ''}. Anda perlu login kembali untuk melanjutkan.`}
        labelKonfirmasi="Ya, Keluar"
        loading={sedangKeluar}
        onConfirm={() => void keluar()}
        onClose={() => setKonfirmasiKeluar(false)}
      />
    </div>
  )
}
