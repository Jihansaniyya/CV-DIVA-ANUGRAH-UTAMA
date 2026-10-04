import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { ChevronRight } from 'lucide-react'

interface WelcomeModalProps {
  open: boolean
  nama?: string
  onClose: () => void
}

/**
 * Modal selamat datang yang muncul saat first-time login.
 * Menampilkan pesan sambutan dan ajakan untuk memulai.
 */
export function WelcomeModal({ open, nama = 'Pengguna', onClose }: WelcomeModalProps) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title=""
      showCloseButton={false}
      bodyClassName="p-0"
    >
      <div className="grid grid-cols-2 gap-0 overflow-hidden rounded-2xl">
        {/* Bagian kiri: Teks sambutan */}
        <div className="flex flex-col justify-between bg-white p-6 sm:p-8">
          <div>
            <h2 className="text-2xl font-bold text-ink">
              Selamat Datang, {nama}! 👋
            </h2>
            <p className="mt-3 text-sm text-muted leading-relaxed">
              Kelola proyek konstruksi milikmu dengan lebih mudah dan efisien.
            </p>
          </div>

          <Button
            onClick={onClose}
            className="mt-6 self-start"
            icon={<ChevronRight className="size-4" />}
            iconPosition="right"
          >
            Mulai
          </Button>
        </div>

        {/* Bagian kanan: Ilustrasi */}
        <div className="relative hidden sm:block bg-gradient-to-br from-orange-100 via-yellow-50 to-orange-50 p-6 sm:p-8">
          {/* Dekorasi background */}
          <div className="absolute inset-0 overflow-hidden">
            <div className="absolute -right-12 -top-12 w-40 h-40 bg-yellow-200 rounded-full opacity-20 blur-3xl" />
            <div className="absolute -right-20 -bottom-20 w-60 h-60 bg-orange-200 rounded-full opacity-20 blur-3xl" />
          </div>

          {/* Konten ilustrasi */}
          <div className="relative h-full flex items-center justify-center">
            <svg
              viewBox="0 0 300 300"
              className="w-full h-full max-w-xs"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              {/* Crane */}
              <rect x="140" y="80" width="20" height="80" fill="#FFA500" />
              <circle cx="150" cy="75" r="10" fill="#FFD700" />
              <rect x="160" y="90" width="80" height="12" fill="#FF8C00" />

              {/* Building */}
              <rect x="40" y="140" width="100" height="100" fill="#E8A87C" stroke="#C68A5F" strokeWidth="2" />

              {/* Windows - Building Left */}
              <rect x="55" y="155" width="15" height="15" fill="#87CEEB" />
              <rect x="75" y="155" width="15" height="15" fill="#87CEEB" />
              <rect x="95" y="155" width="15" height="15" fill="#87CEEB" />

              <rect x="55" y="180" width="15" height="15" fill="#87CEEB" />
              <rect x="75" y="180" width="15" height="15" fill="#87CEEB" />
              <rect x="95" y="180" width="15" height="15" fill="#87CEEB" />

              <rect x="55" y="205" width="15" height="15" fill="#87CEEB" />
              <rect x="75" y="205" width="15" height="15" fill="#87CEEB" />
              <rect x="95" y="205" width="15" height="15" fill="#87CEEB" />

              {/* Building Right */}
              <rect x="160" y="120" width="80" height="120" fill="#D2A679" stroke="#B8945F" strokeWidth="2" />

              {/* Windows - Building Right */}
              <rect x="175" y="140" width="12" height="12" fill="#87CEEB" />
              <rect x="200" y="140" width="12" height="12" fill="#87CEEB" />
              <rect x="225" y="140" width="12" height="12" fill="#87CEEB" />

              <rect x="175" y="165" width="12" height="12" fill="#87CEEB" />
              <rect x="200" y="165" width="12" height="12" fill="#87CEEB" />
              <rect x="225" y="165" width="12" height="12" fill="#87CEEB" />

              <rect x="175" y="190" width="12" height="12" fill="#87CEEB" />
              <rect x="200" y="190" width="12" height="12" fill="#87CEEB" />
              <rect x="225" y="190" width="12" height="12" fill="#87CEEB" />

              <rect x="175" y="215" width="12" height="12" fill="#87CEEB" />
              <rect x="200" y="215" width="12" height="12" fill="#87CEEB" />
              <rect x="225" y="215" width="12" height="12" fill="#87CEEB" />

              {/* Ground */}
              <ellipse cx="150" cy="250" rx="80" ry="15" fill="#D4AF37" opacity="0.3" />
            </svg>
          </div>
        </div>
      </div>
    </Modal>
  )
}
