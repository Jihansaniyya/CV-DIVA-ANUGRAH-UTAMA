import { Button } from '@/components/ui/Button'
import { FileQuestion } from 'lucide-react'
import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 text-center">
      <div className="grid size-14 place-items-center rounded-full bg-surface text-muted">
        <FileQuestion className="size-7" aria-hidden />
      </div>
      <h2 className="text-lg font-semibold text-ink">Halaman tidak ditemukan</h2>
      <p className="max-w-sm text-xs text-muted">Periksa kembali alamat halaman yang kamu tuju.</p>
      <Link to="/dashboard">
        <Button className="mt-2">Kembali ke Dashboard</Button>
      </Link>
    </div>
  )
}
