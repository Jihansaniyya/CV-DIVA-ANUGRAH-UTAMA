import { api, UNGGAH_TIMEOUT_MS } from '@/lib/api'
import type { Paginated, ProgressReport, UploadLimits } from '@/types'

export interface ProgressFilter {
  project_id?: number
  period_id?: number
  status?: string
  dari?: string
  sampai?: string
  page?: number
  per_page?: number
}

export interface ProgressDetailInput {
  work_item_id: number
  volume_realisasi: number
  keterangan?: string | null
}

export interface ProgressIssueInput {
  work_item_id?: number | null
  /** Null hanya untuk draf yang jenis kendalanya belum dipilih; backend mengisi LAINNYA. */
  jenis_kendala?: string | null
  /** Kendala sekaligus alasan keterlambatan dalam satu narasi. */
  deskripsi: string
  tindak_lanjut?: string | null
  status?: string
}

export interface ProgressPayload {
  project_id: number
  tanggal_laporan: string
  keterangan?: string | null
  lokasi?: string | null
  cuaca?: string | null
  status?: 'DRAFT' | 'DIKIRIM'
  /** Identitas unik isian form; pengiriman ulang dengan nilai sama tidak membuat laporan ganda. */
  client_request_id?: string
  details: ProgressDetailInput[]
  issues?: ProgressIssueInput[]
  photos?: File[]
  photo_captions?: string[]
}

/** Laporan progres dikirim sebagai multipart/form-data karena memuat foto. */
function buatFormData(payload: ProgressPayload): FormData {
  const form = new FormData()

  form.append('project_id', String(payload.project_id))
  form.append('tanggal_laporan', payload.tanggal_laporan)
  form.append('status', payload.status ?? 'DRAFT')

  if (payload.client_request_id) form.append('client_request_id', payload.client_request_id)
  // Selalu dikirim (boleh kosong) agar isian yang dikosongkan pada draf ikut tersimpan.
  form.append('keterangan', payload.keterangan ?? '')
  form.append('lokasi', payload.lokasi ?? '')
  if (payload.cuaca) form.append('cuaca', payload.cuaca)

  form.append('details', JSON.stringify(payload.details))
  form.append('issues', JSON.stringify(payload.issues ?? []))
  form.append('photo_captions', JSON.stringify(payload.photo_captions ?? []))

  payload.photos?.forEach((file) => form.append('photos[]', file))

  return form
}

export const progressService = {
  async list(filter: ProgressFilter = {}): Promise<Paginated<ProgressReport>> {
    const { data } = await api.get<Paginated<ProgressReport>>('/progress', { params: filter })

    return data
  },

  /**
   * Seluruh laporan yang cocok dengan filter, diambil per 100 data (batas per halaman backend).
   * Dipakai kalender dokumentasi yang memang perlu semua tanggal; hanya metadata foto yang dimuat.
   */
  async listAll(filter: Omit<ProgressFilter, 'page' | 'per_page'> = {}): Promise<ProgressReport[]> {
    const hasil: ProgressReport[] = []

    for (let page = 1; ; page++) {
      const halaman = await progressService.list({ ...filter, page, per_page: 100 })
      hasil.push(...halaman.data)

      if (page >= halaman.meta.last_page) return hasil
    }
  },

  async uploadLimits(): Promise<UploadLimits> {
    const { data } = await api.get<{ data: UploadLimits }>('/progress/upload-limits')

    return data.data
  },

  async detail(id: number): Promise<ProgressReport> {
    const { data } = await api.get<{ data: ProgressReport }>(`/progress/${id}`)

    return data.data
  },

  async create(payload: ProgressPayload): Promise<ProgressReport> {
    // Unggah foto lewat jaringan seluler bisa lama; batas waktu lebih longgar dari permintaan biasa.
    const { data } = await api.post<{ data: ProgressReport }>('/progress', buatFormData(payload), { timeout: UNGGAH_TIMEOUT_MS })

    return data.data
  },

  async update(id: number, payload: ProgressPayload): Promise<ProgressReport> {
    const { data } = await api.post<{ data: ProgressReport }>(`/progress/${id}`, buatFormData(payload), { timeout: UNGGAH_TIMEOUT_MS })

    return data.data
  },

  async submit(id: number): Promise<ProgressReport> {
    const { data } = await api.patch<{ data: ProgressReport }>(`/progress/${id}/submit`)

    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`/progress/${id}`)
  },

  async removePhoto(photoId: number): Promise<void> {
    await api.delete(`/progress-photos/${photoId}`)
  },
}
