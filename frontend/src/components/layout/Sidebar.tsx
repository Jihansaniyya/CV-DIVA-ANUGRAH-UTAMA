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

/** Menu sidebar per peran sesuai PRD. */
const MENU: MenuItem[] = [
  { to: '/dashboard', label: 'Beranda', icon: LayoutDashboard, roles: ['ADMIN', 'QS', 'KONTRAKTOR'] },
  { to: '/proyek', label: 'Proyek', icon: ClipboardList, roles: ['ADMIN', 'QS', 'KONTRAKTOR'] },
  { to: '/kurva-s', label: 'Kurva S', icon: LineChart, roles: ['ADMIN', 'KONTRAKTOR'] },
  { to: '/progres', label: 'Progres', icon: ClipboardCheck, roles: ['ADMIN', 'QS', 'KONTRAKTOR'] },
  { to: '/laporan', label: 'Laporan', icon: FileText, roles: ['ADMIN', 'KONTRAKTOR'] },
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
          'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-navy text-white transition-transform duration-200 lg:translate-x-0',
          open ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className="flex items-center gap-2.5 border-b border-white/10 px-4 py-4">
          <img src={logo} alt="Logo CV Diva Anugrah Utama" className="h-9 w-auto rounded bg-white p-1" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-xs leading-tight font-semibold tracking-wide">CV. DIVA ANUGRAH UTAMA</p>
            <p className="truncate text-[10px] text-white/60">Manajemen &amp; Monitoring Proyek</p>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-1 text-white/70 hover:bg-white/10 lg:hidden" aria-label="Tutup menu">
            <X className="size-5" aria-hidden />
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto px-3 py-4">
          <ul className="flex flex-col gap-1">
            {menu.map((item) => (
              <li key={item.to}>
                <NavLink
                  to={item.to}
                  onClick={onClose}
                  className={({ isActive }) =>
                    cn(
                      'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                      isActive ? 'bg-primary text-white' : 'text-white/75 hover:bg-white/10 hover:text-white',
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
            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/75 transition-colors hover:bg-white/10 hover:text-white"
          >
            <LogOut className="size-[18px]" aria-hidden />
            Keluar
          </button>
        </div>
      </aside>
    </>
  )
}
