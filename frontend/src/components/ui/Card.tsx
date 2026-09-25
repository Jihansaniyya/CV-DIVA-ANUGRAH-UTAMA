import { cn } from '@/utils/cn'
import type { ReactNode } from 'react'

interface CardProps {
  title?: ReactNode
  description?: ReactNode
  action?: ReactNode
  children: ReactNode
  className?: string
  bodyClassName?: string
}

export function Card({ title, description, action, children, className, bodyClassName }: CardProps) {
  return (
    <section className={cn('app-card', className)}>
      {(title || action) && (
        <header className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
          <div>
            {title && <h2 className="text-base font-semibold text-ink">{title}</h2>}
            {description && <p className="mt-0.5 text-xs text-muted">{description}</p>}
          </div>
          {action}
        </header>
      )}
      <div className={cn('p-4 sm:p-5', bodyClassName)}>{children}</div>
    </section>
  )
}
