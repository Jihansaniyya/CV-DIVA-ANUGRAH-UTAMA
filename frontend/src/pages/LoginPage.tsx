import logo from '@/assets/logo.png'
import { ConstructionScene } from '@/components/login/ConstructionScene'
import { Button } from '@/components/ui/Button'
import { useAuth } from '@/hooks/useAuth'
import { errorValidasi, pesanError } from '@/lib/api'
import { cn } from '@/utils/cn'
import { ArrowRight, Eye, EyeOff, Lock, Mail } from 'lucide-react'
import { useState, type FormEvent, type InputHTMLAttributes, type ReactNode } from 'react'
import { Navigate, useSearchParams } from 'react-router-dom'

interface IconFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  id: string
  label: string
  icon: ReactNode
  error?: string
  trailing?: ReactNode
}

/** Field login dengan kotak ikon di sisi kiri; label tetap tersedia untuk pembaca layar. */
function IconField({ id, label, icon, error, trailing, ...props }: IconFieldProps) {
  return (
    <div>
      <label htmlFor={id} className="sr-only">
        {label}
      </label>
      <div
        className={cn(
          'flex overflow-hidden rounded-lg border bg-white transition-colors focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15',
          error ? 'border-danger' : 'border-line',
        )}
      >
        <span className="grid w-12 shrink-0 place-items-center border-r border-line bg-surface text-ink/70">{icon}</span>
        <input
          id={id}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${id}-error` : undefined}
          className="h-11 min-w-0 flex-1 bg-transparent px-3.5 text-sm text-ink placeholder:text-muted/70 focus-visible:outline-none"
          {...props}
        />
        {trailing}
      </div>
      {error && (
        <p id={`${id}-error`} className="mt-1.5 text-[11px] font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  )
}

export function LoginPage() {
  const { user, siap, login } = useAuth()
  const [params] = useSearchParams()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [ingatSaya, setIngatSaya] = useState(false)
  const [lihatSandi, setLihatSandi] = useState(false)
  const [loading, setLoading] = useState(false)
  const [pesan, setPesan] = useState<string | null>(params.get('expired') ? 'Sesi kamu telah berakhir. Silakan login kembali.' : null)
  const [errors, setErrors] = useState<Record<string, string>>({})

  if (siap && user) {
    return <Navigate to="/dashboard" replace />
  }

  const kirim = async (event: FormEvent) => {
    event.preventDefault()
    setErrors({})
    setPesan(null)

    const validasi: Record<string, string> = {}
    if (!email.trim()) validasi.email = 'Email wajib diisi.'
    if (!password) validasi.password = 'Password wajib diisi.'

    if (Object.keys(validasi).length > 0) {
      setErrors(validasi)

      return
    }

    setLoading(true)

    try {
      await login(email.trim(), password, ingatSaya)
    } catch (error) {
      const dariServer = errorValidasi(error)
      setErrors(dariServer)

      if (Object.keys(dariServer).length === 0) {
        setPesan(pesanError(error))
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div
      className="relative min-h-screen overflow-hidden"
      style={{
        background:
          'linear-gradient(180deg, color-mix(in srgb, var(--color-navy) 11%, white) 0%, color-mix(in srgb, var(--color-navy) 4%, white) 55%, var(--color-surface) 100%)',
      }}
    >
      {/* ---------- Dekorasi latar ---------- */}
      <div aria-hidden className="pointer-events-none absolute inset-0">
        {/* Bidang diagonal terang di sisi kanan */}
        <div className="absolute -top-1/4 right-[-12%] h-[150%] w-[38%] rotate-[28deg] bg-white/45" />
        <div className="absolute -top-1/4 right-[14%] h-[150%] w-[10%] rotate-[28deg] bg-white/30" />

        {/* Ilustrasi konstruksi, memudar ke kanan dan ke atas */}
        <ConstructionScene
          className="absolute bottom-0 left-0 h-[46%] w-full opacity-30 sm:h-[52%] lg:aspect-[1000/640] lg:h-[calc(100%-24rem)] lg:min-h-80 lg:w-auto lg:max-w-[68%] lg:opacity-85"
          style={{
            maskImage: 'linear-gradient(to right, #000 55%, transparent 98%), linear-gradient(to top, #000 55%, transparent 100%)',
            maskComposite: 'intersect',
            WebkitMaskImage: 'linear-gradient(to right, #000 55%, transparent 98%), linear-gradient(to top, #000 55%, transparent 100%)',
            WebkitMaskComposite: 'source-in',
          }}
        />

        {/* Aksen diagonal navy-merah di pojok kiri bawah */}
        <svg viewBox="0 0 800 300" preserveAspectRatio="none" className="absolute bottom-0 left-0 h-20 w-[85%] sm:h-28 lg:h-52 lg:w-[56%]">
          <path d="M0 18 C 260 104 540 212 800 292 L800 300 L0 300 Z" fill="var(--color-primary)" />
          <path d="M0 92 C 250 160 520 246 800 300 L0 300 Z" fill="white" />
          <path d="M0 104 C 250 170 520 252 772 300 L0 300 Z" fill="var(--color-navy)" />
        </svg>

        {/* Pola titik di kanan bawah */}
        <div
          className="absolute right-8 bottom-10 hidden size-28 opacity-40 lg:block"
          style={{ backgroundImage: 'radial-gradient(var(--color-muted) 1.3px, transparent 1.3px)', backgroundSize: '18px 18px' }}
        />
      </div>

      {/* ---------- Konten ---------- */}
      <div className="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:gap-12 lg:px-14 lg:py-12">
        <div className="hidden lg:flex lg:flex-1 lg:flex-col lg:self-stretch">
          <img src={logo} alt="Logo CV Diva Anugrah Utama" className="-ml-2 h-24 w-auto self-start xl:h-28" />
          <div className="mt-10 max-w-sm pl-10 xl:mt-16">
            <span aria-hidden className="block h-1 w-12 rounded-full bg-primary" />
            <h1 className="mt-5 text-3xl leading-tight font-semibold text-navy">
              Kelola Proyek
              <br />
              Lebih Efisien
            </h1>
            <p className="mt-4 text-sm leading-relaxed text-ink/70">
              Sistem manajemen proyek untuk mendukung pengawasan, koordinasi, dan pelaporan yang lebih terstruktur.
            </p>
          </div>
        </div>

        <section className="my-auto w-full max-w-md self-center rounded-2xl border border-white/70 bg-white p-6 shadow-[0_20px_50px_-20px_rgb(7_26_82/0.35)] sm:p-10 lg:mr-6 lg:self-auto">
          <div className="text-center">
            <img src={logo} alt="Logo CV Diva Anugrah Utama" className="mx-auto h-20 w-auto sm:h-24" />
            <p className="-mt-1 text-sm text-ink/80">Manajemen &amp; Monitoring Proyek</p>
          </div>

          <hr className="my-6 border-line" />

          <div className="text-center">
            <h2 className="text-2xl font-semibold text-navy">Selamat Datang</h2>
            <p className="mt-1 text-sm text-muted">Silakan masuk untuk melanjutkan</p>
          </div>

          {pesan && (
            <p role="alert" className="mt-5 rounded-lg bg-danger-soft px-3 py-2 text-xs font-medium text-danger">
              {pesan}
            </p>
          )}

          <form className="mt-7 flex flex-col gap-4" onSubmit={kirim} noValidate>
            <IconField
              id="login-email"
              label="Email"
              type="email"
              placeholder="Email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              error={errors.email}
              autoComplete="email"
              icon={<Mail className="size-[18px]" aria-hidden />}
              required
            />

            <IconField
              id="login-password"
              label="Password"
              type={lihatSandi ? 'text' : 'password'}
              placeholder="Password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              error={errors.password}
              autoComplete="current-password"
              icon={<Lock className="size-[18px]" aria-hidden />}
              required
              trailing={
                <button
                  type="button"
                  onClick={() => setLihatSandi((nilai) => !nilai)}
                  className="grid w-11 shrink-0 place-items-center text-muted hover:text-ink"
                  aria-label={lihatSandi ? 'Sembunyikan password' : 'Tampilkan password'}
                >
                  {lihatSandi ? <EyeOff className="size-[18px]" aria-hidden /> : <Eye className="size-[18px]" aria-hidden />}
                </button>
              }
            />

            <label className="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-ink/80">
              <input
                type="checkbox"
                checked={ingatSaya}
                onChange={(event) => setIngatSaya(event.target.checked)}
                className="size-4 min-h-0 cursor-pointer rounded border-line accent-primary"
              />
              Ingat saya
            </label>

            <Button type="submit" block size="lg" loading={loading} className="mt-2 gap-2.5">
              Login
              {!loading && <ArrowRight className="size-[18px]" aria-hidden />}
            </Button>
          </form>
        </section>
      </div>
    </div>
  )
}
