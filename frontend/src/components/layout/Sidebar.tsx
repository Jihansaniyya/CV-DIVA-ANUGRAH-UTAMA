import logo from '@/assets/logo.png'
import { useAuth } from '@/hooks/useAuth'
import type { RoleCode } from '@/types'
import { cn } from '@/utils/cn'
import {
  ChevronRight,
  ClipboardCheck,
  ClipboardList,
  FileText,
  LayoutDashboard,
  LayoutGrid,
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
      {open && (
        <button
          type="button"
          aria-label="Tutup menu"
          className="fixed inset-0 z-40 bg-navy-dark/50 backdrop-blur-[1px] lg:hidden"
          onClick={onClose}
        />
      )}

      <aside
        className={cn(
          'no-print fixed inset-y-0 left-0 z-50 flex w-64 flex-col overflow-hidden bg-gradient-to-b from-navy to-navy-dark text-white transition-transform duration-200 lg:translate-x-0',
          open ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
        )}
      >
        {/* Area logo putih dengan garis diagonal merah di tepi bawah. */}
        <div className="relative shrink-0 pb-2.5">
          <span aria-hidden className="absolute inset-0 bg-primary [clip-path:polygon(0_0,100%_0,100%_calc(100%-10px),0_100%)]" />
          <div className="relative flex items-center justify-center bg-white px-5 pt-2.5 pb-4 [clip-path:polygon(0_0,100%_0,100%_calc(100%-12px),0_calc(100%-2px))]">
            {/* logo.png punya ruang putih bawaan di atas/bawah; margin negatif memadatkannya tanpa memotong isi logo. */}
            <img src={logo} alt="Logo CV Diva Anugrah Utama" className="-my-3 h-auto w-full min-w-0 object-contain" />
            <button
              type="button"
              onClick={onClose}
              className="absolute top-2 right-2 grid size-8 place-items-center rounded-lg text-muted hover:bg-surface hover:text-ink lg:hidden"
              aria-label="Tutup menu"
            >
              <X className="size-4" aria-hidden />
            </button>
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-3 px-6 pt-2.5 pb-4">
          <span className="grid size-9 shrink-0 place-items-center rounded-lg border border-white/15 bg-white/5">
            <LayoutGrid className="size-[18px] text-white/85" aria-hidden />
          </span>
          <p className="text-[13px] leading-snug font-medium text-white/90">
            Manajemen &amp;
            <br />
            Monitoring Proyek
          </p>
        </div>

        <nav className="flex-1 overflow-y-auto px-4 pb-4" aria-label="Menu utama">
          <p className="mb-2 px-3 text-[10px] font-semibold tracking-[0.14em] text-white/45 uppercase">Menu</p>
          <ul className="flex flex-col gap-1.5">
            {menu.map((item) => (
              <li key={item.to}>
                <NavLink
                  to={item.to}
                  onClick={onClose}
                  className={({ isActive }) =>
                    cn(
                      'group flex h-11 items-center gap-3 rounded-xl px-3.5 text-sm transition-colors',
                      isActive
                        ? 'bg-primary font-semibold text-white shadow-[0_6px_14px_-8px_rgb(215_25_32/0.9)]'
                        : 'font-medium text-white/75 hover:bg-white/[0.07] hover:text-white',
                    )
                  }
                >
                  {({ isActive }) => (
                    <>
                      <item.icon className="size-[19px] shrink-0" aria-hidden />
                      <span className="min-w-0 flex-1 truncate">{item.label}</span>
                      {isActive && <ChevronRight className="size-4 shrink-0 text-white/90" aria-hidden />}
                    </>
                  )}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>

        <div className="relative shrink-0 border-t border-white/10 px-4 py-4">
          {/* Aksen diagonal halus di pojok bawah, senada dengan area logo. */}
          <span
            aria-hidden
            className="pointer-events-none absolute right-0 bottom-0 h-20 w-28 bg-primary/80 [clip-path:polygon(100%_0,100%_100%,0_100%)]"
          />
          <span
            aria-hidden
            className="pointer-events-none absolute right-0 bottom-0 h-24 w-36 bg-white/[0.06] [clip-path:polygon(100%_0,100%_100%,0_100%)]"
          />
          <button
            type="button"
            onClick={() => {
              onClose()
              onLogout()
            }}
            className="relative flex h-11 w-full items-center gap-3 rounded-xl px-3.5 text-sm font-medium text-white/75 transition-colors hover:bg-white/[0.07] hover:text-white"
          >
            <LogOut className="size-[19px]" aria-hidden />
            Keluar
          </button>
        </div>
      </aside>
    </>
  )
}
