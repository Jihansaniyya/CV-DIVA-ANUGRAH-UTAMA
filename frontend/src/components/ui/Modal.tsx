import { Button } from '@/components/ui/Button'
import { cn } from '@/utils/cn'
import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: string
  description?: string
  children: ReactNode
  footer?: ReactNode
  size?: 'sm' | 'md' | 'lg' | 'xl'
}

const SIZE = {
  sm: 'max-w-md',
  md: 'max-w-xl',
  lg: 'max-w-3xl',
  xl: 'max-w-5xl',
}

export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: ModalProps) {
  useEffect(() => {
    if (!open) return

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }

    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 sm:items-center sm:p-4" role="dialog" aria-modal>
      <button type="button" aria-label="Tutup" className="absolute inset-0 cursor-default" onClick={onClose} />
      <div
        className={cn(
          'relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl',
          SIZE[size],
        )}
      >
        <header className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-ink">{title}</h2>
            {description && <p className="mt-0.5 text-xs text-muted">{description}</p>}
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-1 text-muted hover:bg-surface" aria-label="Tutup">
            <X className="size-5" aria-hidden />
          </button>
        </header>
        <div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>
        {footer && <footer className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-3">{footer}</footer>}
      </div>
    </div>
  )
}

interface ConfirmDialogProps {
  open: boolean
  title: string
  pesan: string
  labelKonfirmasi?: string
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
}

export function ConfirmDialog({
  open,
  title,
  pesan,
  labelKonfirmasi = 'Hapus',
  loading,
  onConfirm,
  onClose,
}: ConfirmDialogProps) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={loading}>
            Batal
          </Button>
          <Button variant="danger" onClick={onConfirm} loading={loading}>
            {labelKonfirmasi}
          </Button>
        </>
      }
    >
      <p className="text-sm text-muted">{pesan}</p>
    </Modal>
  )
}
