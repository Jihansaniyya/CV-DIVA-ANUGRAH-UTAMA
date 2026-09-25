import logo from '@/assets/logo.png'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Field'
import { useAuth } from '@/hooks/useAuth'
import { errorValidasi, pesanError } from '@/lib/api'
import { Eye, EyeOff, LogIn } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Navigate, useSearchParams } from 'react-router-dom'

export function LoginPage() {
  const { user, siap, login } = useAuth()
  const [params] = useSearchParams()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
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

    if (!username.trim() || !password) {
      setErrors({
        username: username.trim() ? '' : 'Nama pengguna wajib diisi.',
        password: password ? '' : 'Kata sandi wajib diisi.',
      })

      return
    }

    setLoading(true)

    try {
      await login(username.trim(), password)
    } catch (error) {
      const validasi = errorValidasi(error)
      setErrors(validasi)

      if (Object.keys(validasi).length === 0) {
        setPesan(pesanError(error))
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <section className="hidden flex-col justify-between bg-navy p-10 text-white lg:flex">
        <img src={logo} alt="Logo CV Diva Anugrah Utama" className="h-14 w-auto self-start rounded-lg bg-white p-2" />
        <div>
          <h1 className="text-3xl leading-tight font-semibold">Manajemen &amp; Monitoring Proyek Kontraktor</h1>
          <p className="mt-3 max-w-md text-sm text-white/70">
            Kelola data proyek, rencana pekerjaan, target progres, dan Kurva S dalam satu sistem. Pantau realisasi pekerjaan di
            lapangan tanpa harus menunggu kabar manual.
          </p>
        </div>
        <p className="text-xs text-white/50">CV. Diva Anugrah Utama - Jl. Imam Bonjol No. 56 Kel. Tanjung Laut, Kota Bontang</p>
      </section>

      <section className="flex items-center justify-center bg-surface px-4 py-10">
        <div className="app-card w-full max-w-md p-6 sm:p-8">
          <img src={logo} alt="Logo CV Diva Anugrah Utama" className="mx-auto mb-4 h-12 w-auto lg:hidden" />
          <h2 className="text-xl font-semibold text-ink">Masuk ke Akun Anda</h2>
          <p className="mt-1 text-xs text-muted">Silakan login untuk melanjutkan.</p>

          {pesan && (
            <p role="alert" className="mt-4 rounded-lg bg-danger-soft px-3 py-2 text-xs font-medium text-danger">
              {pesan}
            </p>
          )}

          <form className="mt-5 flex flex-col gap-4" onSubmit={kirim} noValidate>
            <Input
              label="Username"
              placeholder="Masukkan username"
              value={username}
              onChange={(event) => setUsername(event.target.value)}
              error={errors.username}
              autoComplete="username"
              required
            />

            <div className="relative">
              <Input
                label="Password"
                type={lihatSandi ? 'text' : 'password'}
                placeholder="Masukkan password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                error={errors.password}
                autoComplete="current-password"
                className="pr-10"
                required
              />
              <button
                type="button"
                onClick={() => setLihatSandi((nilai) => !nilai)}
                className="absolute top-[30px] right-3 text-muted"
                aria-label={lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
              >
                {lihatSandi ? <EyeOff className="size-4" aria-hidden /> : <Eye className="size-4" aria-hidden />}
              </button>
            </div>

            <Button type="submit" block size="lg" loading={loading} icon={<LogIn className="size-4" />}>
              Login
            </Button>
          </form>
        </div>
      </section>
    </div>
  )
}
