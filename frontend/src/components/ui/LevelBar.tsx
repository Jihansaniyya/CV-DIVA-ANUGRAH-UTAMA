import { cn } from '@/utils/cn'
import { angka } from '@/utils/format'

/**
 * Ambang warna progres:
 * - merah  : 0% sampai di bawah 40%
 * - kuning : 40% sampai di bawah 80%
 * - hijau  : 80% sampai 100%
 */
const AMBANG_KUNING = 40
const AMBANG_HIJAU = 80

function nada(nilai: number): { isi: string; teks: string } {
  if (nilai >= AMBANG_HIJAU) return { isi: 'bg-success', teks: 'text-white' }
  if (nilai >= AMBANG_KUNING) return { isi: 'bg-warning', teks: 'text-ink' }

  return { isi: 'bg-danger', teks: 'text-white' }
}

interface LevelBarProps {
  nilai: number
  className?: string
}

/** Bar progres dengan angka persen di dalam bar dan warna sesuai ambang merah/kuning/hijau. */
export function LevelBar({ nilai, className }: LevelBarProps) {
  const lebar = Math.max(0, Math.min(100, nilai))
  const { isi, teks } = nada(lebar)
  const label = `${angka(nilai, 1)}%`
  // Bila bagian berwarna terlalu sempit, angka ditaruh di sisi kanan bagian berwarna agar tetap terbaca.
  const labelDiDalam = lebar >= 30

  return (
    <div className={cn('relative h-4 min-w-24 overflow-hidden rounded-full bg-line', className)}>
      <div
        className={cn('flex h-full items-center justify-end rounded-full transition-all', labelDiDalam && 'pr-1.5', isi)}
        style={{ width: `${lebar}%` }}
      >
        {labelDiDalam && <span className={cn('text-[10px] leading-none font-semibold tabular-nums', teks)}>{label}</span>}
      </div>
      {!labelDiDalam && (
        <span
          className="absolute top-1/2 -translate-y-1/2 text-[10px] leading-none font-semibold text-ink tabular-nums"
          style={{ left: `calc(${lebar}% + 6px)` }}
        >
          {label}
        </span>
      )}
    </div>
  )
}

/** Dua bar bertumpuk: rencana di atas, realisasi di bawah; label berada di atas masing-masing bar. */
export function RencanaRealisasiBar({ rencana, realisasi, className }: { rencana: number; realisasi: number; className?: string }) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <div>
        <p className="mb-0.5 text-[10px] text-muted">Rencana</p>
        <LevelBar nilai={rencana} />
      </div>
      <div>
        <p className="mb-0.5 text-[10px] text-muted">Realisasi</p>
        <LevelBar nilai={realisasi} />
      </div>
    </div>
  )
}

/** Keterangan rentang warna progres. */
export function LegendaLevel({ className }: { className?: string }) {
  const item = [
    { warna: 'bg-danger', label: `Merah: 0% – <${AMBANG_KUNING}%` },
    { warna: 'bg-warning', label: `Kuning: ${AMBANG_KUNING}% – <${AMBANG_HIJAU}%` },
    { warna: 'bg-success', label: `Hijau: ${AMBANG_HIJAU}% – 100%` },
  ]

  return (
    <ul className={cn('flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted', className)}>
      {item.map((baris) => (
        <li key={baris.label} className="flex items-center gap-1.5">
          <span className={cn('size-2.5 rounded-full', baris.warna)} aria-hidden />
          {baris.label}
        </li>
      ))}
    </ul>
  )
}
