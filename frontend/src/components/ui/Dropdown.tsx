import { FieldWrap } from '@/components/ui/Field'
import { cn } from '@/utils/cn'
import { Check, ChevronDown } from 'lucide-react'
import { useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react'
import { createPortal } from 'react-dom'

export interface DropdownOption {
  value: string
  label: string
}

interface DropdownProps {
  value: string
  options: DropdownOption[]
  onChange: (value: string) => void
  /** Teks saat belum ada pilihan; juga menjadi opsi kosong bila `allowEmpty` aktif. */
  placeholder?: string
  /** Tampilkan placeholder sebagai opsi yang bisa dipilih untuk mengosongkan pilihan (mis. "Semua proyek"). */
  allowEmpty?: boolean
  label?: string
  error?: string
  hint?: string
  required?: boolean
  disabled?: boolean
  wrapClassName?: string
  className?: string
  'aria-label'?: string
}

/**
 * Pengganti `<select>` untuk teks panjang seperti nama proyek: teks pilihan dibungkus ke baris berikutnya
 * alih-alih terpotong. Daftar dirender lewat portal agar tidak terpotong oleh kartu atau tabel.
 */
export function Dropdown({
  value,
  options,
  onChange,
  placeholder = 'Pilih',
  allowEmpty = false,
  label,
  error,
  hint,
  required,
  disabled,
  wrapClassName,
  className,
  'aria-label': ariaLabel,
}: DropdownProps) {
  const [terbuka, setTerbuka] = useState(false)
  const [aktif, setAktif] = useState(0)
  const [posisi, setPosisi] = useState<{ top: number; left: number; width: number; maxHeight: number } | null>(null)
  const tombolRef = useRef<HTMLButtonElement>(null)
  const daftarRef = useRef<HTMLUListElement>(null)
  const idDaftar = useId()

  const pilihan: DropdownOption[] = allowEmpty ? [{ value: '', label: placeholder }, ...options] : options
  const terpilih = options.find((option) => option.value === value)

  useLayoutEffect(() => {
    if (!terbuka || !tombolRef.current || !daftarRef.current) return

    const tombol = tombolRef.current.getBoundingClientRect()
    const jarak = 4
    const ruangBawah = window.innerHeight - tombol.bottom - jarak - 8
    const ruangAtas = tombol.top - jarak - 8
    const tinggiDaftar = Math.min(daftarRef.current.scrollHeight, 320)
    const keBawah = tinggiDaftar <= ruangBawah || ruangBawah >= ruangAtas
    const maxHeight = Math.min(320, keBawah ? ruangBawah : ruangAtas)
    const top = keBawah ? tombol.bottom + jarak : tombol.top - jarak - Math.min(tinggiDaftar, maxHeight)

    setPosisi({ top, left: tombol.left, width: tombol.width, maxHeight })
  }, [terbuka])

  useEffect(() => {
    if (!terbuka) return

    const tutup = () => setTerbuka(false)
    const klikLuar = (event: MouseEvent) => {
      const target = event.target as Node
      if (tombolRef.current?.contains(target) || daftarRef.current?.contains(target)) return
      tutup()
    }
    // Gulir di dalam daftar sendiri tidak menutup dropdown.
    const gulir = (event: Event) => {
      if (daftarRef.current?.contains(event.target as Node)) return
      tutup()
    }

    document.addEventListener('mousedown', klikLuar)
    window.addEventListener('scroll', gulir, true)
    window.addEventListener('resize', tutup)

    return () => {
      document.removeEventListener('mousedown', klikLuar)
      window.removeEventListener('scroll', gulir, true)
      window.removeEventListener('resize', tutup)
    }
  }, [terbuka])

  useEffect(() => {
    if (!terbuka) return
    daftarRef.current?.querySelector<HTMLElement>(`[data-index="${aktif}"]`)?.scrollIntoView({ block: 'nearest' })
  }, [aktif, terbuka])

  const buka = () => {
    if (disabled) return
    setPosisi(null)
    setAktif(Math.max(0, pilihan.findIndex((option) => option.value === value)))
    setTerbuka(true)
  }

  const pilih = (option: DropdownOption) => {
    onChange(option.value)
    setTerbuka(false)
    tombolRef.current?.focus()
  }

  const tombolKeyboard = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (!terbuka) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault()
        buka()
      }
      return
    }

    if (event.key === 'Escape' || event.key === 'Tab') {
      setTerbuka(false)
    } else if (event.key === 'ArrowDown') {
      event.preventDefault()
      setAktif((indeks) => Math.min(pilihan.length - 1, indeks + 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setAktif((indeks) => Math.max(0, indeks - 1))
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      if (pilihan[aktif]) pilih(pilihan[aktif])
    }
  }

  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName}>
      <button
        ref={tombolRef}
        type="button"
        disabled={disabled}
        onClick={() => (terbuka ? setTerbuka(false) : buka())}
        onKeyDown={tombolKeyboard}
        className={cn(
          'flex min-h-[38px] w-full items-center gap-2 rounded-lg border bg-white px-3 py-2 text-left text-sm transition-colors disabled:bg-surface disabled:text-muted',
          error ? 'border-danger' : terbuka ? 'border-navy-light/45' : 'border-line focus:border-navy-light/45',
          className,
        )}
        role="combobox"
        aria-haspopup="listbox"
        aria-expanded={terbuka}
        aria-controls={idDaftar}
        aria-invalid={Boolean(error)}
        aria-label={ariaLabel ?? label}
      >
        <span className={cn('min-w-0 flex-1 leading-snug break-words', terpilih ? 'text-ink' : 'text-muted/70')}>
          {terpilih?.label ?? placeholder}
        </span>
        <ChevronDown className={cn('size-4 shrink-0 text-muted transition-transform', terbuka && 'rotate-180')} aria-hidden />
      </button>

      {terbuka &&
        createPortal(
          <ul
            ref={daftarRef}
            id={idDaftar}
            role="listbox"
            className="fixed z-50 overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-[0_16px_40px_-12px_rgb(7_26_82/0.25)] motion-safe:animate-muncul"
            style={{
              top: posisi?.top ?? 0,
              left: posisi?.left ?? 0,
              width: posisi?.width,
              maxHeight: posisi?.maxHeight ?? 320,
              visibility: posisi ? 'visible' : 'hidden',
            }}
          >
            {pilihan.length === 0 ? (
              <li className="px-3 py-2 text-sm text-muted">Tidak ada pilihan.</li>
            ) : (
              pilihan.map((option, index) => {
                const dipilih = option.value === value
                return (
                  <li
                    key={option.value || '__kosong'}
                    data-index={index}
                    role="option"
                    aria-selected={dipilih}
                    onMouseEnter={() => setAktif(index)}
                    onClick={() => pilih(option)}
                    className={cn(
                      'flex cursor-pointer items-start gap-2 rounded-lg px-3 py-2 text-sm leading-snug break-words',
                      index === aktif && 'bg-surface',
                      dipilih ? 'font-medium text-navy' : 'text-ink',
                    )}
                  >
                    <span className="min-w-0 flex-1">{option.label}</span>
                    {dipilih && <Check className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden />}
                  </li>
                )
              })
            )}
          </ul>,
          document.body,
        )}
    </FieldWrap>
  )
}
