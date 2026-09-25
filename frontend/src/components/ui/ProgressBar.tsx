import { cn } from '@/utils/cn'
import { angka } from '@/utils/format'

interface ProgressBarProps {
  nilai: number
  pembanding?: number | null
  showLabel?: boolean
  className?: string
}

/** Warna bar mengikuti capaian: hijau selesai, kuning berjalan, merah tertinggal. */
function warna(nilai: number, pembanding?: number | null): string {
  if (nilai >= 99.5) return 'bg-success'
  if (pembanding != null && nilai < pembanding - 5) return 'bg-danger'
  if (nilai <= 0) return 'bg-line'

  return 'bg-warning'
}

export function ProgressBar({ nilai, pembanding, showLabel = true, className }: ProgressBarProps) {
  const lebar = Math.max(0, Math.min(100, nilai))

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <div className="h-2 min-w-16 flex-1 overflow-hidden rounded-full bg-line">
        <div className={cn('h-full rounded-full transition-all', warna(nilai, pembanding))} style={{ width: `${lebar}%` }} />
      </div>
      {showLabel && <span className="w-12 shrink-0 text-right text-xs font-medium tabular-nums">{angka(nilai, 1)}%</span>}
    </div>
  )
}
