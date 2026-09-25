import { Button } from '@/components/ui/Button'
import { ChevronLeft, ChevronRight } from 'lucide-react'

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  from: number | null
  to: number | null
  onChange: (page: number) => void
}

export function Pagination({ page, lastPage, total, from, to, onChange }: PaginationProps) {
  if (lastPage <= 1) {
    return (
      <p className="px-1 py-3 text-xs text-muted">
        Menampilkan {total} data
      </p>
    )
  }

  const halaman = Array.from({ length: lastPage }, (_, index) => index + 1).filter(
    (nomor) => nomor === 1 || nomor === lastPage || Math.abs(nomor - page) <= 1,
  )

  return (
    <nav className="flex flex-wrap items-center justify-between gap-3 py-3" aria-label="Navigasi halaman">
      <p className="text-xs text-muted">
        Menampilkan {from ?? 0}-{to ?? 0} dari {total} data
      </p>
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
