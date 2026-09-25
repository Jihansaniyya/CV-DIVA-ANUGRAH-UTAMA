import { cn } from '@/utils/cn'
import { CheckCircle2, Info, X, XCircle } from 'lucide-react'
import type { ToastItem } from '@/hooks/useToast'

const TONE = {
  success: { cls: 'border-success/30 bg-success-soft text-[#14532d]', Icon: CheckCircle2 },
  error: { cls: 'border-danger/30 bg-danger-soft text-[#7f1d1d]', Icon: XCircle },
  info: { cls: 'border-line bg-white text-ink', Icon: Info },
}

export function ToastViewport({ items, onDismiss }: { items: ToastItem[]; onDismiss: (id: number) => void }) {
  if (items.length === 0) return null

  return (
    <div className="pointer-events-none fixed inset-x-3 bottom-3 z-[60] flex flex-col gap-2 sm:inset-x-auto sm:top-4 sm:right-4 sm:bottom-auto sm:w-96">
      {items.map((item) => {
        const { cls, Icon } = TONE[item.tone]

        return (
          <div
            key={item.id}
            role="status"
            className={cn('pointer-events-auto flex items-start gap-2.5 rounded-xl border px-4 py-3 shadow-lg', cls)}
          >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
            <p className="flex-1 text-sm">{item.pesan}</p>
            <button type="button" onClick={() => onDismiss(item.id)} aria-label="Tutup notifikasi" className="shrink-0 opacity-60 hover:opacity-100">
              <X className="size-4" aria-hidden />
            </button>
          </div>
        )
      })}
    </div>
  )
}
