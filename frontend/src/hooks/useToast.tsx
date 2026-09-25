import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'
import { ToastViewport } from '@/components/ui/Toast'

export interface ToastItem {
  id: number
  pesan: string
  tone: 'success' | 'error' | 'info'
}

interface ToastContextValue {
  sukses: (pesan: string) => void
  gagal: (pesan: string) => void
  info: (pesan: string) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([])

  const dismiss = useCallback((id: number) => {
    setItems((current) => current.filter((item) => item.id !== id))
  }, [])

  const tampilkan = useCallback(
    (pesan: string, tone: ToastItem['tone']) => {
      const id = Date.now() + Math.random()
      setItems((current) => [...current, { id, pesan, tone }])
      window.setTimeout(() => dismiss(id), 5000)
    },
    [dismiss],
  )

  const value = useMemo<ToastContextValue>(
    () => ({
      sukses: (pesan) => tampilkan(pesan, 'success'),
      gagal: (pesan) => tampilkan(pesan, 'error'),
      info: (pesan) => tampilkan(pesan, 'info'),
    }),
    [tampilkan],
  )

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastViewport items={items} onDismiss={dismiss} />
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const context = useContext(ToastContext)

  if (!context) {
    throw new Error('useToast harus dipakai di dalam ToastProvider.')
  }

  return context
}
