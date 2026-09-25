import axios, { AxiosError } from 'axios'

export const TOKEN_KEY = 'dau.token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

/** Pesan error berbahasa Indonesia untuk seluruh status yang ditangani aplikasi. */
export function pesanError(error: unknown): string {
  if (!(error instanceof AxiosError)) {
    return 'Terjadi kesalahan yang tidak diketahui.'
  }

  if (!error.response) {
    return 'Tidak dapat terhubung ke server. Periksa koneksi kamu.'
  }

  const dariServer = (error.response.data as { message?: string } | undefined)?.message

  switch (error.response.status) {
    case 401:
      return 'Sesi kamu telah berakhir. Silakan login kembali.'
    case 403:
      return dariServer ?? 'Kamu tidak memiliki akses ke halaman ini.'
    case 404:
      return dariServer ?? 'Data yang kamu cari tidak ditemukan.'
    case 422:
      return dariServer ?? 'Periksa kembali data yang kamu masukkan.'
    case 429:
      return 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.'
    case 500:
    case 502:
    case 503:
      return 'Terjadi kesalahan pada server.'
    default:
      return dariServer ?? 'Permintaan tidak dapat diproses.'
  }
}

/** Error validasi per field dari Laravel (status 422). */
export function errorValidasi(error: unknown): Record<string, string> {
  if (!(error instanceof AxiosError) || error.response?.status !== 422) {
    return {}
  }

  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}

  return Object.fromEntries(Object.entries(errors).map(([field, pesan]) => [field, pesan[0]]))
}

/** Redirect ke login ketika token tidak lagi berlaku. */
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401 && !window.location.pathname.startsWith('/login')) {
      localStorage.removeItem(TOKEN_KEY)
      window.location.assign('/login?expired=1')
    }

    return Promise.reject(error)
  },
)
