import { api } from '@/lib/api'
import type {
  CurveData,
  Milestone,
  Paginated,
  Period,
  Project,
  WorkCategory,
  WorkItem,
  WorkPlanMatrix,
} from '@/types'

export interface ProjectFilter {
  q?: string
  status?: string
  lokasi?: string
  tahun_anggaran?: number
  page?: number
  per_page?: number
}

export interface ProjectPayload {
  nama_proyek: string
  nomor_spk?: string | null
  nomor_pekerjaan?: string | null
  nomor_proyek?: string | null
  lokasi: string
  sumber_dana?: string | null
  tahun_anggaran?: number | null
  tanggal_spk?: string | null
  tanggal_mulai: string
  tanggal_selesai: string
  jangka_waktu_hari?: number | null
  kontraktor_pelaksana?: string | null
  konsultan_pengawas?: string | null
  nama_site_engineer?: string | null
  nama_pelaksana_lapangan?: string | null
  qs_user_id?: number | null
  status?: string
  keterangan?: string | null
}

export interface WorkItemPayload {
  work_category_id?: number | null
  unit_id: number
  uraian_pekerjaan: string
  volume: number
  harga_satuan: number | null
  period_mulai_id: number | null
  period_selesai_id: number | null
  urutan?: number | null
  keterangan?: string | null
}

export interface WorkPlanRowPayload {
  work_item_id: number
  period_id: number
  target_volume: number
  catatan?: string | null
}

export const projectService = {
  async list(filter: ProjectFilter = {}): Promise<Paginated<Project>> {
    const { data } = await api.get<Paginated<Project>>('/projects', { params: filter })

    return data
  },

  async detail(id: number): Promise<Project> {
    const { data } = await api.get<{ data: Project }>(`/projects/${id}`)

    return data.data
  },

  async create(payload: ProjectPayload): Promise<Project> {
    const { data } = await api.post<{ data: Project }>('/projects', payload)

    return data.data
  },

  async update(id: number, payload: Partial<ProjectPayload>): Promise<Project> {
    const { data } = await api.put<{ data: Project }>(`/projects/${id}`, payload)

    return data.data
  },

  async remove(id: number): Promise<void> {
    await api.delete(`/projects/${id}`)
  },

  async categories(id: number): Promise<WorkCategory[]> {
    const { data } = await api.get<{ data: WorkCategory[] }>(`/projects/${id}/work-categories`)

    return data.data
  },

  async createCategory(id: number, payload: { kode?: string | null; nama: string; urutan?: number }): Promise<WorkCategory> {
    const { data } = await api.post<{ data: WorkCategory }>(`/projects/${id}/work-categories`, payload)

    return data.data
  },

  async removeCategory(projectId: number, categoryId: number): Promise<void> {
    await api.delete(`/projects/${projectId}/work-categories/${categoryId}`)
  },

  async workItems(id: number): Promise<{ data: WorkItem[]; meta: { total_bobot: number; memakai_harga: boolean; total_harga_pekerjaan: number } }> {
    const { data } = await api.get<{ data: WorkItem[]; meta: { total_bobot: number; memakai_harga: boolean; total_harga_pekerjaan: number } }>(
      `/projects/${id}/work-items`,
    )

    return data
  },

  async createWorkItem(id: number, payload: WorkItemPayload): Promise<WorkItem> {
    const { data } = await api.post<{ data: WorkItem }>(`/projects/${id}/work-items`, payload)

    return data.data
  },

  async updateWorkItem(id: number, workItemId: number, payload: Partial<WorkItemPayload>): Promise<WorkItem> {
    const { data } = await api.put<{ data: WorkItem }>(`/projects/${id}/work-items/${workItemId}`, payload)

    return data.data
  },

  async removeWorkItem(id: number, workItemId: number): Promise<void> {
    await api.delete(`/projects/${id}/work-items/${workItemId}`)
  },

  async periods(id: number): Promise<Period[]> {
    const { data } = await api.get<{ data: Period[] }>(`/projects/${id}/periods`)

    return data.data
  },

  async workPlans(id: number): Promise<WorkPlanMatrix> {
    const { data } = await api.get<{ data: WorkPlanMatrix }>(`/projects/${id}/work-plans`)

    return data.data
  },

  async syncWorkPlans(id: number, rows: WorkPlanRowPayload[]): Promise<WorkPlanMatrix> {
    const { data } = await api.post<{ data: WorkPlanMatrix }>(`/projects/${id}/work-plans`, { rows })

    return data.data
  },

  async curveS(id: number): Promise<CurveData> {
    const { data } = await api.get<{ data: CurveData }>(`/projects/${id}/curve-s`)

    return data.data
  },

  async milestones(id: number): Promise<Milestone[]> {
    const { data } = await api.get<{ data: Milestone[] }>(`/projects/${id}/milestones`)

    return data.data
  },

  async createMilestone(id: number, payload: Partial<Milestone>): Promise<Milestone> {
    const { data } = await api.post<{ data: Milestone }>(`/projects/${id}/milestones`, payload)

    return data.data
  },

  async removeMilestone(id: number, milestoneId: number): Promise<void> {
    await api.delete(`/projects/${id}/milestones/${milestoneId}`)
  },
}
