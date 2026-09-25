import { Button } from '@/components/ui/Button'
import { ShieldAlert } from 'lucide-react'
import { Link } from 'react-router-dom'

export function NotAuthorizedPage() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-3 text-center">
      <div className="grid size-14 place-items-center rounded-full bg-danger-soft text-danger">
        <ShieldAlert className="size-7" aria-hidden />
      </div>
      <h2 className="text-lg font-semibold text-ink">Kamu tidak memiliki akses ke halaman ini</h2>
      <p className="max-w-sm text-xs text-muted">Halaman yang kamu tuju hanya dapat diakses oleh peran tertentu.</p>
      <Link to="/dashboard">
        <Button className="mt-2">Kembali ke Dashboard</Button>
      </Link>
    </div>
  )
}
