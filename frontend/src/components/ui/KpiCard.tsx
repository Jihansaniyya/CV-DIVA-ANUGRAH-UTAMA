import { cn } from '@/utils/cn'
import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

type Tone = 'primary' | 'navy' | 'success' | 'warning' | 'danger' | 'neutral'

interface KpiCardProps {
  label: string
  value: ReactNode
  icon: LucideIcon
  hint?: ReactNode
  tone?: Tone
  valueClassName?: string
  className?: string
  /** Tata letak mendatar yang lebih pendek (ikon di kiri) untuk dashboard satu layar. */
  compact?: boolean
}

const TONE: Record<Tone, string> = {
  primary: 'bg-primary-light text-primary',
  navy: 'bg-navy/8 text-navy',
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-[#b45309]',
  danger: 'bg-danger-soft text-danger',
  neutral: 'bg-surface text-muted',
}

/** Kartu ringkasan: label kecil di atas, angka utama paling menonjol, ikon hanya sebagai penanda. */
export function KpiCard({ label, value, icon: Icon, hint, tone = 'navy', valueClassName, className, compact = false }: KpiCardProps) {
  if (compact) {
    return (
      <article className={cn('app-card flex items-center gap-3 px-4 py-3', className)}>
        <span className={cn('grid size-10 shrink-0 place-items-center rounded-xl', TONE[tone])}>
          <Icon className="size-5" aria-hidden />
        </span>
        <div className="min-w-0">
          <p className="truncate text-xs leading-snug font-medium text-muted">{label}</p>
          <p className={cn('mt-0.5 text-xl leading-tight font-semibold text-ink', valueClassName)}>{value}</p>
          {hint && <div className="truncate text-[11px] leading-snug text-muted">{hint}</div>}
        </div>
      </article>
    )
  }

  return (
    <article className={cn('app-card flex flex-col justify-between gap-3 p-4', className)}>
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs leading-snug font-medium text-muted">{label}</p>
        <span className={cn('grid size-8 shrink-0 place-items-center rounded-lg', TONE[tone])}>
          <Icon className="size-4" aria-hidden />
        </span>
      </div>
      <div className="min-w-0">
        <p className={cn('text-2xl leading-none font-semibold text-ink', valueClassName)}>{value}</p>
        {hint && <div className="mt-2 text-[11px] leading-snug text-muted">{hint}</div>}
      </div>
    </article>
  )
}
