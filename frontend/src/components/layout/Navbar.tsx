import { useAuth } from '@/hooks/useAuth'
import { LABEL_PERAN } from '@/utils/peran'
import { Menu } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'

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

  return (
    <header className="no-print sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-white px-4 sm:px-6">
      <button type="button" onClick={onOpenMenu} className="-ml-1.5 rounded-lg p-2 text-muted hover:bg-surface lg:hidden" aria-label="Buka menu">
        <Menu className="size-5" aria-hidden />
      </button>

      <div className="min-w-0 flex-1">
        <h1 className="truncate text-base leading-tight font-semibold text-ink">{judul}</h1>
        {deskripsi && <p className="mt-0.5 truncate text-xs text-muted">{deskripsi}</p>}
      </div>

      <div className="relative sm:border-l sm:border-line sm:pl-3">
        <button
          type="button"
          onClick={() => setMenuAkun((nilai) => !nilai)}
          className="flex items-center gap-2.5 rounded-lg px-1.5 py-1 hover:bg-surface"
          aria-haspopup="menu"
          aria-expanded={menuAkun}
          aria-label={`Akun ${user?.name ?? ''}`}
        >
          <span className="hidden text-right sm:block">
            <span className="block text-sm leading-tight font-medium text-ink">{user?.name}</span>
            <span className="block text-xs leading-tight text-muted">{LABEL_PERAN[user?.role_code ?? ''] ?? '-'}</span>
          </span>
          <span className="grid size-9 place-items-center rounded-full bg-navy text-xs font-semibold text-white">{inisial || 'DA'}</span>
        </button>

        {menuAkun && (
          <div className="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-line bg-white p-2 shadow-lg" role="menu">
            <div className="border-b border-line px-3 pt-1 pb-2">
              <p className="text-sm font-medium text-ink">{user?.name}</p>
              <p className="text-[11px] text-muted">{LABEL_PERAN[user?.role_code ?? ''] ?? '-'} · {user?.email}</p>
            </div>
            <Button
              variant="ghost"
              block
              className="mt-2 justify-start"
              onClick={() => {
                setMenuAkun(false)
                onLogout()
              }}
            >
              Keluar
            </Button>
          </div>
        )}
      </div>
    </header>
  )
}
