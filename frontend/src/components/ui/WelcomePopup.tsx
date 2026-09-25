import { tokenStorage } from '@/lib/api'
import { X } from 'lucide-react'
import { useEffect, useState } from 'react'

const DURASI_MS = 4000

/** Kunci per sesi login memakai ID token (bagian sebelum "|"), bukan token rahasianya. */
function kunciSambutan(): string | null {
  try {
    const idToken = tokenStorage.get()?.split('|')[0]

    return idToken ? `dau.sambutan.${idToken}` : null
  } catch {
    return null
  }
}

function sudahDisambut(kunci: string | null): boolean {
  try {
    return !kunci || sessionStorage.getItem(kunci) !== null
  } catch {
    return true
  }
}

/** Popup sambutan yang tampil sekali setiap login lalu hilang otomatis. */
export function WelcomePopup({ nama, keterangan }: { nama?: string; keterangan?: string }) {
  const [kunci] = useState(kunciSambutan)
  const [tampil, setTampil] = useState(() => !sudahDisambut(kunci))

  useEffect(() => {
    if (!tampil || !kunci) return

    try {
      sessionStorage.setItem(kunci, '1')
    } catch {
      // Penyimpanan sesi tidak tersedia; popup tetap ditutup otomatis.
    }

    const timer = window.setTimeout(() => setTampil(false), DURASI_MS)

    return () => window.clearTimeout(timer)
  }, [tampil, kunci])

  if (!tampil || !nama) return null

  const inisial = nama
    .split(' ')
    .slice(0, 2)
    .map((bagian) => bagian.charAt(0))
    .join('')
    .toUpperCase()

  return (
    <div
      role="status"
      className="fixed top-[4.75rem] right-4 z-[60] w-[calc(100%-2rem)] max-w-xs overflow-hidden rounded-xl border border-line bg-white shadow-lg sm:right-6 motion-safe:animate-muncul"
    >
      <div className="flex items-center gap-3 px-4 py-3.5">
        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-navy text-sm font-semibold text-white">{inisial}</span>
        <div className="min-w-0 flex-1">
          <p className="text-[11px] leading-tight font-medium tracking-wide text-muted uppercase">Selamat datang</p>
          <p className="mt-0.5 truncate text-sm leading-tight font-semibold text-ink">{nama}</p>
          {keterangan && <p className="mt-0.5 truncate text-xs leading-tight text-muted">{keterangan}</p>}
        </div>
        <button
          type="button"
          onClick={() => setTampil(false)}
          aria-label="Tutup"
          className="shrink-0 self-start rounded-md p-1 text-muted transition-colors hover:bg-surface hover:text-ink"
        >
          <X className="size-4" aria-hidden />
        </button>
      </div>
    </div>
  )
}
