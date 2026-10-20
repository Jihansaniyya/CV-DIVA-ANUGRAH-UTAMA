import { Button } from '@/components/ui/Button'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useId } from 'react'

/** Pilihan jumlah data per halaman; batas atas mengikuti PER_PAGE_MAKS di backend (100). */
const PILIHAN_PER_HALAMAN = [10, 25, 50, 100] as const

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  from: number | null
  to: number | null
  onChange: (page: number) => void
  /** Bila diisi bersama onPerPageChange, tampil pilihan jumlah data per halaman. */
  perPage?: number
  onPerPageChange?: (perPage: number) => void
}

function PilihPerHalaman({ perPage, onChange }: { perPage: number; onChange: (perPage: number) => void }) {
  const id = useId()

  return (
    <div className="flex items-center gap-2 text-xs text-muted">
      <label htmlFor={id}>Tampilkan</label>
      <select
        id={id}
        value={perPage}
        onChange={(event) => onChange(Number(event.target.value))}
        className="h-8 rounded-md border border-line bg-white px-2 text-xs text-ink focus:border-navy-light/45 focus:outline-none"
      >
        {PILIHAN_PER_HALAMAN.map((jumlah) => (
          <option key={jumlah} value={jumlah}>
            {jumlah}
          </option>
        ))}
      </select>
      <span>data per halaman</span>
    </div>
  )
}

export function Pagination({ page, lastPage, total, from, to, onChange, perPage, onPerPageChange }: PaginationProps) {
  const pilihPerHalaman = perPage !== undefined && onPerPageChange ? <PilihPerHalaman perPage={perPage} onChange={onPerPageChange} /> : null

  if (lastPage <= 1) {
    return (
      <div className="flex flex-wrap items-center justify-between gap-3 py-3">
        <p className="px-1 text-xs text-muted">
          Menampilkan {to ?? total} dari {total} data
        </p>
        {pilihPerHalaman}
      </div>
    )
  }

  const halaman = Array.from({ length: lastPage }, (_, index) => index + 1).filter(
    (nomor) => nomor === 1 || nomor === lastPage || Math.abs(nomor - page) <= 1,
  )

  return (
    <nav className="flex flex-wrap items-center justify-between gap-3 py-3" aria-label="Navigasi halaman">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p className="text-xs text-muted">
          Menampilkan {from ?? 0}-{to ?? 0} dari {total} data
        </p>
        {pilihPerHalaman}
      </div>
      <div className="flex items-center gap-1">
        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)} aria-label="Halaman sebelumnya">
          <ChevronLeft className="size-4" aria-hidden />
        </Button>
        {halaman.map((nomor, index) => (
          <span key={nomor} className="flex items-center gap-1">
            {index > 0 && nomor - halaman[index - 1] > 1 && <span className="px-1 text-xs text-muted">...</span>}
            <Button
              variant={nomor === page ? 'primary' : 'outline'}
              size="sm"
              className="min-w-8"
              onClick={() => onChange(nomor)}
              aria-current={nomor === page ? 'page' : undefined}
            >
              {nomor}
            </Button>
          </span>
        ))}
        <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)} aria-label="Halaman berikutnya">
          <ChevronRight className="size-4" aria-hidden />
        </Button>
      </div>
    </nav>
  )
}
