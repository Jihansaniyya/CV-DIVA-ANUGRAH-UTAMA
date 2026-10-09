import { cn } from '@/utils/cn'
import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

type Tone = 'primary' | 'navy' | 'info' | 'success' | 'warning' | 'danger' | 'neutral'

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
  /** Kartu ikut berwarna sesuai `tone`: garis atas, latar tipis, dan angka utama. */
  accent?: boolean
}

const TONE: Record<Tone, string> = {
  primary: 'bg-primary-light text-primary',
  navy: 'bg-navy/8 text-navy',
  info: 'bg-info-soft text-info',
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-[#b45309]',
  danger: 'bg-danger-soft text-danger',
  neutral: 'bg-surface text-muted',
}

/** Kelas ditulis lengkap (bukan dirangkai) agar terbaca oleh Tailwind. */
const ACCENT: Record<Tone, { kartu: string; angka: string }> = {
  primary: { kartu: 'border-t-[3px] border-t-primary bg-primary-light/40', angka: 'text-primary' },
  navy: { kartu: 'border-t-[3px] border-t-navy bg-navy/4', angka: 'text-navy' },
  info: { kartu: 'border-t-[3px] border-t-info bg-info-soft/50', angka: 'text-info' },
  success: { kartu: 'border-t-[3px] border-t-success bg-success-soft/50', angka: 'text-success' },
  warning: { kartu: 'border-t-[3px] border-t-warning bg-warning-soft/50', angka: 'text-[#b45309]' },
  danger: { kartu: 'border-t-[3px] border-t-danger bg-danger-soft/50', angka: 'text-danger' },
  neutral: { kartu: 'border-t-[3px] border-t-muted bg-surface', angka: 'text-ink' },
}

/** Kartu ringkasan: label kecil di atas, angka utama paling menonjol, ikon hanya sebagai penanda. */
export function KpiCard({ label, value, icon: Icon, hint, tone = 'navy', valueClassName, className, compact = false, accent = false }: KpiCardProps) {
  if (compact) {
    return (
      <article className={cn('app-card flex items-center gap-3 px-4 py-3', accent && ACCENT[tone].kartu, className)}>
        <span className={cn('grid size-10 shrink-0 place-items-center rounded-xl', TONE[tone], accent && 'bg-white/80 ring-1 ring-black/5')}>
          <Icon className="size-5" aria-hidden />
        </span>
        <div className="min-w-0">
          <p className="truncate text-xs leading-snug font-medium text-muted">{label}</p>
          <p className={cn('mt-0.5 text-xl leading-tight font-semibold text-ink', accent && ACCENT[tone].angka, valueClassName)}>{value}</p>
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
