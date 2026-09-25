import { cn } from '@/utils/cn'
import type { ReactNode } from 'react'

/** Pembungkus tabel: struktur kolom dipertahankan, digulir horizontal pada layar kecil. */
export function TableWrap({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn('app-scroll-x -mx-4 sm:mx-0', className)}>
      <div className="inline-block min-w-full px-4 align-middle sm:px-0">{children}</div>
    </div>
  )
}

export function Table({ children, className }: { children: ReactNode; className?: string }) {
  return <table className={cn('min-w-full border-collapse text-sm', className)}>{children}</table>
}

export function Th({ children, className, align = 'left' }: { children?: ReactNode; className?: string; align?: 'left' | 'center' | 'right' }) {
  return (
    <th
      scope="col"
      className={cn(
        'border-b border-line bg-surface/60 px-3 py-2.5 text-[11px] font-semibold tracking-wide text-muted uppercase whitespace-nowrap',
        align === 'right' && 'text-right',
        align === 'center' && 'text-center',
        className,
      )}
    >
      {children}
    </th>
  )
}

export function Td({
  children,
  className,
  align = 'left',
  colSpan,
}: {
  children?: ReactNode
  className?: string
  align?: 'left' | 'center' | 'right'
  colSpan?: number
}) {
  return (
    <td
      colSpan={colSpan}
      className={cn(
        'border-b border-line px-3 py-2.5 align-middle text-ink',
        align === 'right' && 'text-right tabular-nums',
        align === 'center' && 'text-center',
        className,
      )}
    >
      {children}
    </td>
  )
}
