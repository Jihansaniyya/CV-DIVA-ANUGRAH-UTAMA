import { cn } from '@/utils/cn'
import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

interface KpiCardProps {
  label: string
  value: ReactNode
  icon: LucideIcon
  hint?: ReactNode
  tone?: 'primary' | 'navy' | 'success' | 'warning'
}

const TONE = {
  primary: 'bg-primary-light text-primary',
  navy: 'bg-[#e8edfb] text-navy',
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-[#b45309]',
}

export function KpiCard({ label, value, icon: Icon, hint, tone = 'navy' }: KpiCardProps) {
  return (
    <article className="app-card flex items-start gap-3 p-4">
      <span className={cn('grid size-10 shrink-0 place-items-center rounded-xl', TONE[tone])}>
        <Icon className="size-5" aria-hidden />
      </span>
      <div className="min-w-0">
        <p className="truncate text-xs text-muted">{label}</p>
        <p className="mt-0.5 text-2xl leading-tight font-semibold text-ink">{value}</p>
        {hint && <div className="mt-1 text-[11px] text-muted">{hint}</div>}
      </div>
    </article>
  )
}
