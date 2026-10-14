import axios, { AxiosError } from 'axios'

export const TOKEN_KEY = 'dau.token'

/**
 * Token disimpan di localStorage ("Ingat Saya" dicentang, bertahan lintas sesi
 * browser) atau sessionStorage (tidak dicentang, hilang saat tab ditutup).
 * Kedua tempat dicek saat membaca supaya urutan login tidak masalah.
 */
export const tokenStorage = {
  get(): string | null {
    return localStorage.getItem(TOKEN_KEY) ?? sessionStorage.getItem(TOKEN_KEY)
  },
  set(token: string, ingatSaya: boolean): void {
    if (ingatSaya) {
      localStorage.setItem(TOKEN_KEY, token)
    } else {
      sessionStorage.setItem(TOKEN_KEY, token)
    }
  },
  clear(): void {
    localStorage.removeItem(TOKEN_KEY)
    sessionStorage.removeItem(TOKEN_KEY)
  },
}

/** Batas waktu permintaan biasa dan permintaan yang mengunggah foto. */
export const API_TIMEOUT_MS = 30_000
export const UNGGAH_TIMEOUT_MS = 120_000

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' },
  timeout: API_TIMEOUT_MS,
})

/**
 * Permintaan terputus sebelum respons diterima (timeout atau koneksi hilang). Untuk operasi simpan,
 * server mungkin sudah memprosesnya, sehingga statusnya belum dapat dipastikan.
 */
export function statusTidakPasti(error: unknown): boolean {
  return error instanceof AxiosError && !error.response && error.code !== AxiosError.ERR_CANCELED
}

api.interceptors.request.use((config) => {
  const token = tokenStorage.get()

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
    return error.code === AxiosError.ECONNABORTED || error.code === AxiosError.ETIMEDOUT
      ? 'Server tidak merespons dalam batas waktu. Periksa koneksi kamu lalu coba lagi.'
      : 'Tidak dapat terhubung ke server. Periksa koneksi kamu.'
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
    case 413:
      return dariServer ?? 'Ukuran total unggahan melebihi batas server. Kurangi jumlah atau ukuran foto.'
    case 429:
      return 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.'
    case 503:
      return dariServer ?? 'Server sedang tidak tersedia. Coba lagi beberapa saat.'
    case 500:
    case 502:
      return 'Terjadi kendala pada server. Coba lagi beberapa saat atau hubungi Admin.'
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
      tokenStorage.clear()
      window.location.assign('/login?expired=1')
    }

    return Promise.reject(error)
  },
)
