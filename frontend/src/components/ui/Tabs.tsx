import { cn } from '@/utils/cn'

export interface TabItem {
  key: string
  label: string
}

interface TabsProps {
  items: TabItem[]
  active: string
  onChange: (key: string) => void
  className?: string
}

export function Tabs({ items, active, onChange, className }: TabsProps) {
  return (
    <div className={cn('app-scroll-x border-b border-line', className)} role="tablist">
      <div className="flex min-w-max gap-1">
        {items.map((item) => (
          <button
            key={item.key}
            type="button"
            role="tab"
            aria-selected={active === item.key}
            onClick={() => onChange(item.key)}
            className={cn(
              'border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors sm:px-4',
              active === item.key ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink',
            )}
          >
            {item.label}
          </button>
        ))}
      </div>
    </div>
  )
}
