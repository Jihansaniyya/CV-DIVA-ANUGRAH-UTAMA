import { cn } from '@/utils/cn'
import { angka } from '@/utils/format'
import { warnaLevel } from '@/utils/level'

interface ProgressBarProps {
  nilai: number
  showLabel?: boolean
  /** Tampilkan angka tanpa tanda %, untuk kolom tabel yang judulnya sudah memuat "(%)". */
  tanpaPersen?: boolean
  className?: string
}

/** Bar progres tipis; warna mengikuti ambang legenda: merah < 40%, kuning 40% - < 80%, hijau >= 80%. */
export function ProgressBar({ nilai, showLabel = true, tanpaPersen = false, className }: ProgressBarProps) {
  const lebar = Math.max(0, Math.min(100, nilai))

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <div className="h-2 min-w-16 flex-1 overflow-hidden rounded-full bg-line">
        <div className={cn('h-full rounded-full transition-all', warnaLevel(lebar))} style={{ width: `${lebar}%` }} />
      </div>
      {showLabel && <span className="w-12 shrink-0 text-right text-xs font-medium tabular-nums">{angka(nilai, 1)}
          {!tanpaPersen && '%'}
        </span>}
    </div>
  )
}
