import { api } from '@/lib/api'
import type { Paginated, ProgressReport } from '@/types'

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
  jenis_kendala: string
  deskripsi: string
  alasan_keterlambatan?: string | null
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

  if (payload.keterangan) form.append('keterangan', payload.keterangan)
  if (payload.lokasi) form.append('lokasi', payload.lokasi)
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

  async detail(id: number): Promise<ProgressReport> {
    const { data } = await api.get<{ data: ProgressReport }>(`/progress/${id}`)

    return data.data
  },

  async create(payload: ProgressPayload): Promise<ProgressReport> {
    const { data } = await api.post<{ data: ProgressReport }>('/progress', buatFormData(payload))

    return data.data
  },

  async update(id: number, payload: ProgressPayload): Promise<ProgressReport> {
    const { data } = await api.post<{ data: ProgressReport }>(`/progress/${id}`, buatFormData(payload))

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
