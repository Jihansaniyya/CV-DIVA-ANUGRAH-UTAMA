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
    <div className="pointer-events-none fixed inset-x-0 top-3 z-[60] flex justify-center px-4 sm:top-5">
      <div
        role="status"
        className="pointer-events-auto flex w-full max-w-[26rem] items-center gap-3.5 rounded-2xl bg-card p-3.5 pr-3 shadow-[0_1px_2px_rgb(7_26_82/0.06),0_16px_40px_-12px_rgb(7_26_82/0.28)] ring-1 ring-line/80 sm:gap-4 sm:p-4 sm:pr-3.5 motion-safe:animate-sambut"
      >
        <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-dark text-sm font-semibold tracking-wide text-white shadow-sm">
          {inisial}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-xs leading-tight text-muted">Selamat datang kembali,</p>
          <p className="mt-1 truncate text-[15px] leading-tight font-semibold text-ink">{nama}</p>
          {keterangan && (
            <span className="mt-1.5 inline-flex max-w-full items-center gap-1.5 rounded-full bg-surface px-2 py-0.5 text-[11px] leading-tight font-medium text-navy">
              <span className="size-1.5 shrink-0 rounded-full bg-success" aria-hidden />
              <span className="truncate">{keterangan}</span>
            </span>
          )}
        </div>
        <button
          type="button"
          onClick={() => setTampil(false)}
          aria-label="Tutup"
          className="grid size-8 shrink-0 place-items-center self-start rounded-lg text-muted transition-colors hover:bg-surface hover:text-ink"
        >
          <X className="size-4" aria-hidden />
        </button>
      </div>
    </div>
  )
}
