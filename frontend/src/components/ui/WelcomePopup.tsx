import { tokenStorage } from '@/lib/api'
import { CircleCheck } from 'lucide-react'
import { useEffect, useState } from 'react'

const DURASI_MS = 3500

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

/** Notifikasi sambutan kecil di atas layar yang tampil sekali setiap login lalu hilang otomatis. */
export function WelcomePopup({ nama }: { nama?: string }) {
  const [kunci] = useState(kunciSambutan)
  const [tampil, setTampil] = useState(() => !sudahDisambut(kunci))

  useEffect(() => {
    if (!tampil || !kunci) return

    try {
      sessionStorage.setItem(kunci, '1')
    } catch {
      // Penyimpanan sesi tidak tersedia; notifikasi tetap ditutup otomatis.
    }

    const timer = window.setTimeout(() => setTampil(false), DURASI_MS)

    return () => window.clearTimeout(timer)
  }, [tampil, kunci])

  if (!tampil || !nama) return null

  return (
    <div className="pointer-events-none fixed inset-x-0 top-4 z-[60] flex justify-center px-4">
      <button
        type="button"
        role="status"
        onClick={() => setTampil(false)}
        title="Tutup"
        className="pointer-events-auto flex max-w-full items-center gap-2 rounded-full bg-white py-2 pr-4 pl-2.5 text-sm text-ink shadow-[0_8px_24px_-8px_rgb(7_26_82/0.3)] ring-1 ring-line motion-safe:animate-sambut"
      >
        <CircleCheck className="size-5 shrink-0 text-success" aria-hidden />
        <span className="truncate">
          Selamat datang, <span className="font-semibold">{nama}</span>
        </span>
      </button>
    </div>
  )
}
