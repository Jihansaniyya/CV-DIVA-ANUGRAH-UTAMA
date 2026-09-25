import { useAuth } from '@/hooks/useAuth'
import { Menu } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'

const LABEL_PERAN: Record<string, string> = {
  ADMIN: 'Admin',
  QS: 'Quantity Surveyor',
  KONTRAKTOR: 'Kontraktor',
}

export function Navbar({ onOpenMenu, judul }: { onOpenMenu: () => void; judul: string }) {
  const { user, logout } = useAuth()
  const [menuAkun, setMenuAkun] = useState(false)

  const inisial = (user?.name ?? '')
    .split(' ')
    .slice(0, 2)
    .map((bagian) => bagian.charAt(0))
    .join('')
    .toUpperCase()

  return (
    <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-line bg-white px-4 py-3 sm:px-6">
      <button type="button" onClick={onOpenMenu} className="rounded-lg p-2 text-muted hover:bg-surface lg:hidden" aria-label="Buka menu">
        <Menu className="size-5" aria-hidden />
      </button>

      <h1 className="flex-1 truncate text-base font-semibold text-ink sm:text-lg">{judul}</h1>

      <div className="relative">
        <button
          type="button"
          onClick={() => setMenuAkun((nilai) => !nilai)}
          className="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-surface"
          aria-haspopup="menu"
          aria-expanded={menuAkun}
        >
          <span className="grid size-8 place-items-center rounded-full bg-navy text-xs font-semibold text-white">{inisial || 'DA'}</span>
          <span className="hidden text-left sm:block">
            <span className="block text-xs leading-tight font-medium text-ink">{user?.name}</span>
            <span className="block text-[11px] leading-tight text-muted">{LABEL_PERAN[user?.role_code ?? ''] ?? '-'}</span>
          </span>
        </button>

        {menuAkun && (
          <div className="absolute right-0 z-40 mt-2 w-56 rounded-xl border border-line bg-white p-2 shadow-lg" role="menu">
            <div className="border-b border-line px-3 pt-1 pb-2">
              <p className="text-sm font-medium text-ink">{user?.name}</p>
              <p className="text-[11px] text-muted">@{user?.username}</p>
            </div>
            <Button variant="ghost" block className="mt-2 justify-start" onClick={() => void logout()}>
              Keluar
            </Button>
          </div>
        )}
      </div>
    </header>
  )
}
