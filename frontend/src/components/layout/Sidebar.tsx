import logo from '@/assets/logo.png'
import { useAuth } from '@/hooks/useAuth'
import type { RoleCode } from '@/types'
import { cn } from '@/utils/cn'
import {
  ClipboardCheck,
  ClipboardList,
  FileText,
  LayoutDashboard,
  LineChart,
  LogOut,
  Settings,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react'
import { NavLink } from 'react-router-dom'

interface MenuItem {
  to: string
  label: string
  icon: LucideIcon
  roles: RoleCode[]
}

/**
 * Menu sidebar per peran sesuai PRD.
 * Admin mengakses Progres, Kurva S, dan Laporan melalui tab pada detail proyek.
 */
const MENU: MenuItem[] = [
  { to: '/dashboard', label: 'Beranda', icon: LayoutDashboard, roles: ['ADMIN', 'QS', 'KONTRAKTOR'] },
  { to: '/proyek', label: 'Proyek', icon: ClipboardList, roles: ['ADMIN', 'QS'] },
  { to: '/kurva-s', label: 'Kurva S', icon: LineChart, roles: ['KONTRAKTOR'] },
  { to: '/progres', label: 'Progres', icon: ClipboardCheck, roles: ['QS', 'KONTRAKTOR'] },
  { to: '/laporan', label: 'Laporan', icon: FileText, roles: ['KONTRAKTOR'] },
  { to: '/pengguna', label: 'Pengguna', icon: Users, roles: ['ADMIN'] },
  { to: '/pengaturan', label: 'Pengaturan', icon: Settings, roles: ['ADMIN'] },
]

interface SidebarProps {
  open: boolean
  onClose: () => void
  onLogout: () => void
}

export function Sidebar({ open, onClose, onLogout }: SidebarProps) {
  const { user } = useAuth()
  const menu = MENU.filter((item) => (user ? item.roles.includes(user.role_code) : false))

  return (
    <>
      {open && <button type="button" aria-label="Tutup menu" className="fixed inset-0 z-40 bg-ink/40 lg:hidden" onClick={onClose} />}

      <aside
        className={cn(
          'no-print fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-navy text-white transition-transform duration-200 lg:translate-x-0',
          open ? 'translate-x-0 shadow-xl' : '-translate-x-full',
        )}
      >
        <div className="flex items-start gap-2 border-b border-white/10 px-4 pt-4 pb-3">
          <div className="min-w-0 flex-1">
            <div className="rounded-lg bg-white px-2 py-1.5">
              <img src={logo} alt="Logo CV Diva Anugrah Utama" className="mx-auto h-14 w-auto max-w-full object-contain" />
            </div>
            <p className="mt-2 text-center text-[11px] tracking-wide text-white/55">Manajemen &amp; Monitoring Proyek</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-white/70 hover:bg-white/10 lg:hidden" aria-label="Tutup menu">
            <X className="size-5" aria-hidden />
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto px-3 py-4" aria-label="Menu utama">
          <p className="mb-2 px-3 text-[10px] font-semibold tracking-widest text-white/40 uppercase">Menu</p>
          <ul className="flex flex-col gap-1">
            {menu.map((item) => (
              <li key={item.to}>
                <NavLink
                  to={item.to}
                  onClick={onClose}
                  className={({ isActive }) =>
                    cn(
                      'relative flex h-10 items-center gap-3 rounded-lg px-3 text-sm transition-colors',
                      isActive
                        ? 'bg-white/10 font-semibold text-white before:absolute before:inset-y-2 before:left-0 before:w-[3px] before:rounded-full before:bg-primary'
                        : 'font-medium text-white/70 hover:bg-white/5 hover:text-white',
                    )
                  }
                >
                  <item.icon className="size-[18px] shrink-0" aria-hidden />
                  {item.label}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <div className="border-t border-white/10 px-3 py-3">
          <button
            type="button"
            onClick={() => {
              onClose()
              onLogout()
            }}
            className="flex h-10 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-white/70 transition-colors hover:bg-white/5 hover:text-white"
          >
            <LogOut className="size-[18px]" aria-hidden />
            Keluar
          </button>
        </div>
      </aside>
    </>
  )
}
