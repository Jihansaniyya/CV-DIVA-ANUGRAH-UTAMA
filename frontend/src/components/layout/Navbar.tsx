import { useAuth } from '@/hooks/useAuth'
import { cn } from '@/utils/cn'
import { LABEL_PERAN } from '@/utils/peran'
import { ChevronDown, LogOut, Menu } from 'lucide-react'
import { useState } from 'react'

interface NavbarProps {
  onOpenMenu: () => void
  onLogout: () => void
  judul: string
  deskripsi?: string
}

export function Navbar({ onOpenMenu, onLogout, judul, deskripsi }: NavbarProps) {
  const { user } = useAuth()
  const [menuAkun, setMenuAkun] = useState(false)

  const inisial = (user?.name ?? '')
    .split(' ')
    .slice(0, 2)
    .map((bagian) => bagian.charAt(0))
    .join('')
    .toUpperCase()

  const peran = LABEL_PERAN[user?.role_code ?? ''] ?? '-'

  return (
    <header className="no-print sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-header-line bg-white px-4 shadow-[0_1px_3px_rgb(7_26_82/0.04)] sm:h-16 sm:gap-4 sm:px-6">
      {/* Aksen latar seperti referensi: rona biru lebar, pita biru diagonal, celah putih, lalu garis merah yang meruncing ke bawah. */}
      <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 hidden overflow-hidden md:block">
        <svg viewBox="0 0 640 64" preserveAspectRatio="none" className="absolute inset-y-0 left-[calc(48%-400px)] h-full w-[640px]">
          <defs>
            <linearGradient id="aksen-header-rona" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="500" y2="0">
              <stop offset="0" stopColor="var(--color-header-shade)" stopOpacity="0" />
              <stop offset="0.45" stopColor="var(--color-header-shade)" stopOpacity="0.55" />
              <stop offset="1" stopColor="var(--color-header-shade)" stopOpacity="1" />
            </linearGradient>
            <linearGradient id="aksen-header-kanan" gradientUnits="userSpaceOnUse" x1="494" y1="0" x2="640" y2="0">
              <stop offset="0" stopColor="var(--color-header-end)" stopOpacity="1" />
              <stop offset="1" stopColor="var(--color-header-end)" stopOpacity="0" />
            </linearGradient>
            <linearGradient id="aksen-header-merah" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="var(--color-primary)" stopOpacity="0.85" />
              <stop offset="1" stopColor="var(--color-primary)" stopOpacity="0.6" />
            </linearGradient>
          </defs>
          <polygon points="0,0 520,0 460,64 0,64" fill="url(#aksen-header-rona)" />
          <polygon points="520,0 540,0 480,64 460,64" fill="var(--color-header-band)" />
          <polygon points="540,0 560,0 492,64 480,64" fill="var(--color-header)" />
          <polygon points="568,0 640,0 640,64 494,64" fill="url(#aksen-header-kanan)" />
          <polygon points="560,0 568,0 494,64 492,64" fill="url(#aksen-header-merah)" />
        </svg>
      </div>

      <button
        type="button"
        onClick={onOpenMenu}
        className="-ml-1 grid size-9 shrink-0 place-items-center rounded-xl bg-surface text-navy transition-colors hover:bg-canvas lg:hidden"
        aria-label="Buka menu"
      >
        <Menu className="size-5" aria-hidden />
      </button>

      <div className="min-w-0 flex-1">
        <h1 className="truncate text-base leading-tight font-semibold text-navy sm:text-[17px]">{judul}</h1>
        {deskripsi && <p className="mt-px truncate text-xs leading-tight text-muted">{deskripsi}</p>}
      </div>

      <div className="relative shrink-0">
        <button
          type="button"
          onClick={() => setMenuAkun((nilai) => !nilai)}
          className="flex items-center gap-2.5 rounded-xl py-1 pr-1.5 pl-1 transition-colors hover:bg-surface/80 sm:pl-3"
          aria-haspopup="menu"
          aria-expanded={menuAkun}
          aria-label={`Akun ${user?.name ?? ''}`}
        >
          <span className="hidden max-w-[12rem] text-right sm:block">
            <span className="block truncate text-sm leading-tight font-semibold text-navy">{user?.name}</span>
            <span className="mt-0.5 block truncate text-xs leading-tight text-muted">{peran}</span>
          </span>
          <span className="grid size-9 shrink-0 place-items-center rounded-full bg-navy text-xs font-semibold tracking-wide text-white ring-2 ring-white shadow-[0_2px_6px_rgb(7_26_82/0.2)]">
            {inisial || 'DA'}
          </span>
          <ChevronDown
            className={cn('hidden size-4 shrink-0 text-navy transition-transform sm:block', menuAkun && 'rotate-180')}
            aria-hidden
          />
        </button>

        {menuAkun && (
          <div
            className="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-xl border border-line bg-white shadow-[0_16px_40px_-12px_rgb(7_26_82/0.25)] motion-safe:animate-muncul"
            role="menu"
          >
            <div className="flex items-center gap-3 border-b border-line px-4 py-3">
              <span className="grid size-10 shrink-0 place-items-center rounded-full bg-navy text-sm font-semibold text-white">{inisial || 'DA'}</span>
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-navy">{user?.name}</p>
                <p className="truncate text-xs text-muted">{peran}</p>
                <p className="truncate text-xs text-muted">{user?.email}</p>
              </div>
            </div>
            <div className="p-1.5">
              <button
                type="button"
                role="menuitem"
                className="flex h-10 w-full items-center gap-2.5 rounded-lg px-3 text-sm font-medium text-ink transition-colors hover:bg-primary-light hover:text-primary"
                onClick={() => {
                  setMenuAkun(false)
                  onLogout()
                }}
              >
                <LogOut className="size-4" aria-hidden />
                Keluar
              </button>
            </div>
          </div>
        )}
      </div>
    </header>
  )
}
