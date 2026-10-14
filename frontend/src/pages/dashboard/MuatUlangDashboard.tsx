import { useDashboard } from '@/hooks/queries'
import { cn } from '@/utils/cn'
import { format } from 'date-fns'
import { RefreshCw } from 'lucide-react'

/**
 * Beranda tidak diperbarui otomatis (bukan real-time). Data diambil ulang setiap halaman dibuka dan
 * setelah progres disimpan; tombol ini untuk mengambil data terbaru secara manual.
 */
export function MuatUlangDashboard() {
  const { dataUpdatedAt, isFetching, refetch } = useDashboard()

  return (
    <button
      type="button"
      onClick={() => void refetch()}
      disabled={isFetching}
      className="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:border-primary hover:text-primary disabled:opacity-60"
      title="Ambil data terbaru"
    >
      <RefreshCw className={cn('size-3.5', isFetching && 'motion-safe:animate-spin')} aria-hidden />
      {isFetching ? 'Memuat...' : dataUpdatedAt ? `Diperbarui ${format(dataUpdatedAt, 'HH.mm')}` : 'Muat ulang'}
    </button>
  )
}
