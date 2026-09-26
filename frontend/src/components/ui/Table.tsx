import { cn } from '@/utils/cn'
import type { ReactNode } from 'react'

/**
 * Pembungkus tabel: struktur kolom dipertahankan, digulir horizontal pada layar kecil.
 * `flush` untuk tabel yang menempel ke tepi kartu (body kartu tanpa padding).
 */
export function TableWrap({ children, className, flush = false }: { children: ReactNode; className?: string; flush?: boolean }) {
  if (flush) {
    return (
      <div
        className={cn(
          'app-scroll-x [&_td:first-child]:pl-4 [&_td:last-child]:pr-4 [&_th:first-child]:pl-4 [&_th:last-child]:pr-4 sm:[&_td:first-child]:pl-5 sm:[&_td:last-child]:pr-5 sm:[&_th:first-child]:pl-5 sm:[&_th:last-child]:pr-5 [&_tbody_tr:last-child_td]:border-b-0',
          className,
        )}
      >
        {children}
      </div>
    )
  }

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
        align === 'left' && 'text-left',
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
