import { Button } from '@/components/ui/Button'
import { cn } from '@/utils/cn'
import { AlertTriangle, Inbox, Loader2 } from 'lucide-react'
import type { ReactNode } from 'react'

export function LoadingState({ pesan = 'Memuat data...', className }: { pesan?: string; className?: string }) {
  return (
    <div className={cn('flex flex-col items-center justify-center gap-3 py-12 text-muted', className)} role="status">
      <Loader2 className="size-7 animate-spin text-primary" aria-hidden />
      <p className="text-sm">{pesan}</p>
    </div>
  )
}

export function EmptyState({
  judul = 'Belum ada data',
  pesan,
  aksi,
  icon,
}: {
  judul?: string
  pesan?: string
  aksi?: ReactNode
  icon?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
      <div className="mb-1 grid size-12 place-items-center rounded-full bg-surface text-muted">
        {icon ?? <Inbox className="size-6" aria-hidden />}
      </div>
      <p className="text-sm font-semibold text-ink">{judul}</p>
      {pesan && <p className="max-w-sm text-xs text-muted">{pesan}</p>}
      {aksi && <div className="mt-3">{aksi}</div>}
    </div>
  )
}

export function ErrorState({ pesan, onRetry }: { pesan: string; onRetry?: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center" role="alert">
      <div className="mb-1 grid size-12 place-items-center rounded-full bg-danger-soft text-danger">
        <AlertTriangle className="size-6" aria-hidden />
      </div>
      <p className="text-sm font-semibold text-ink">Gagal memuat data</p>
      <p className="max-w-sm text-xs text-muted">{pesan}</p>
      {onRetry && (
        <Button variant="outline" size="sm" className="mt-3" onClick={onRetry}>
          Coba lagi
        </Button>
      )}
    </div>
  )
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-md bg-line/70', className)} />
}
