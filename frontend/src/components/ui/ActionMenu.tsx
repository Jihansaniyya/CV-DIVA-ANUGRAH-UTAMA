import { cn } from '@/utils/cn'
import { MoreVertical } from 'lucide-react'
import { useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

export interface ActionMenuItem {
  label: string
  icon?: ReactNode
  onSelect: () => void
  danger?: boolean
}

interface ActionMenuProps {
  items: ActionMenuItem[]
  label?: string
}

/**
 * Tombol titik tiga yang membuka daftar aksi. Menu dirender lewat portal dengan posisi `fixed`
 * agar tidak terpotong oleh pembungkus tabel yang bisa digulir horizontal.
 */
export function ActionMenu({ items, label = 'Buka menu aksi' }: ActionMenuProps) {
  const [terbuka, setTerbuka] = useState(false)
  const [posisi, setPosisi] = useState<{ top: number; left: number } | null>(null)
  const tombolRef = useRef<HTMLButtonElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)

  useLayoutEffect(() => {
    if (!terbuka || !tombolRef.current || !menuRef.current) return

    const tombol = tombolRef.current.getBoundingClientRect()
    const menu = menuRef.current.getBoundingClientRect()
    const jarak = 4
    const muatDiBawah = tombol.bottom + jarak + menu.height <= window.innerHeight - 8
    const top = muatDiBawah ? tombol.bottom + jarak : Math.max(8, tombol.top - jarak - menu.height)
    const left = Math.min(Math.max(8, tombol.right - menu.width), window.innerWidth - menu.width - 8)

    setPosisi({ top, left })
  }, [terbuka])

  useEffect(() => {
    if (!terbuka) return

    const tutup = () => setTerbuka(false)
    const klikLuar = (event: MouseEvent) => {
      const target = event.target as Node
      if (tombolRef.current?.contains(target) || menuRef.current?.contains(target)) return
      tutup()
    }
    const tombolKeyboard = (event: KeyboardEvent) => {
      if (event.key === 'Escape') tutup()
    }

    document.addEventListener('mousedown', klikLuar)
    document.addEventListener('keydown', tombolKeyboard)
    window.addEventListener('scroll', tutup, true)
    window.addEventListener('resize', tutup)

    return () => {
      document.removeEventListener('mousedown', klikLuar)
      document.removeEventListener('keydown', tombolKeyboard)
      window.removeEventListener('scroll', tutup, true)
      window.removeEventListener('resize', tutup)
    }
  }, [terbuka])

  return (
    <>
      <button
        ref={tombolRef}
        type="button"
        onClick={() => {
          setPosisi(null)
          setTerbuka((nilai) => !nilai)
        }}
        className={cn(
          'grid size-8 place-items-center rounded-lg text-muted transition-colors hover:bg-surface hover:text-navy',
          terbuka && 'bg-surface text-navy',
        )}
        aria-haspopup="menu"
        aria-expanded={terbuka}
        aria-label={label}
      >
        <MoreVertical className="size-4" aria-hidden />
      </button>

      {terbuka &&
        createPortal(
          <div
            ref={menuRef}
            role="menu"
            className="fixed z-50 min-w-40 overflow-hidden rounded-xl border border-line bg-white p-1.5 shadow-[0_16px_40px_-12px_rgb(7_26_82/0.25)] motion-safe:animate-muncul"
            style={{ top: posisi?.top ?? 0, left: posisi?.left ?? 0, visibility: posisi ? 'visible' : 'hidden' }}
          >
            {items.map((item) => (
              <button
                key={item.label}
                type="button"
                role="menuitem"
                onClick={() => {
                  setTerbuka(false)
                  item.onSelect()
                }}
                className={cn(
                  'flex h-9 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm font-medium transition-colors',
                  item.danger ? 'text-danger hover:bg-danger-soft' : 'text-ink hover:bg-surface hover:text-navy',
                )}
              >
                {item.icon}
                {item.label}
              </button>
            ))}
          </div>,
          document.body,
        )}
    </>
  )
}
