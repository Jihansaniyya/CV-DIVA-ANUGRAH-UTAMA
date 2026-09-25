export type RoleCode = 'ADMIN' | 'QS' | 'KONTRAKTOR'

export type ProjectStatus = 'BELUM_DIMULAI' | 'BERJALAN' | 'SELESAI' | 'TERLAMBAT'

export type ReportStatus = 'DRAFT' | 'DIKIRIM'

export type ReportType = 'HARIAN' | 'MINGGUAN' | 'BULANAN' | 'MILESTONE'

export type ExportFormat = 'EXCEL' | 'WORD'

export interface Role {
  id: number
  code: RoleCode
  name: string
}

export interface User {
  id: number
  name: string
  username: string
  email: string | null
  phone: string | null
  is_active: boolean
  role?: Role
  role_code: RoleCode
  last_login_at: string | null
  created_at: string | null
}

export interface Unit {
  id: number
  code: string
  name: string
}

export interface Project {
  id: number
  nama_proyek: string
  nomor_spk: string | null
  nomor_pekerjaan: string | null
  nomor_proyek: string | null
  lokasi: string
  sumber_dana: string | null
  tahun_anggaran: number | string | null
  tanggal_spk: string | null
  tanggal_mulai: string
  tanggal_selesai: string
  jangka_waktu_hari: number | null
  kontraktor_pelaksana: string | null
  konsultan_pengawas: string | null
  nama_site_engineer: string | null
  nama_pelaksana_lapangan: string | null
  qs_user_id: number | null
  qs?: User
  status: ProjectStatus
  status_label: string
  keterangan: string | null
  jumlah_pekerjaan?: number
  jumlah_periode?: number
  progres_aktual?: number
  progres_rencana?: number
  deviasi?: number
  created_at: string | null
}

export interface WorkCategory {
  id: number
  project_id: number
  kode: string | null
  nama: string
  urutan: number
}

export interface WorkItem {
  id: number
  project_id: number
  work_category_id: number | null
  kategori?: WorkCategory
  unit_id: number
  satuan: string | null
  uraian_pekerjaan: string
  volume: number
  harga_satuan: number | null
  harga_pekerjaan: number | null
  bobot: number
  period_mulai_id: number | null
  period_selesai_id: number | null
  periode_mulai: string | null
  periode_selesai: string | null
  urutan: number
  keterangan: string | null
  volume_realisasi?: number
  persentase_realisasi?: number
}

export interface Period {
  id: number
  project_id: number
  urutan: number
  nama_periode: string
  bulan_ke: number
  minggu_ke: number
  minggu_ke_bulan: number
  tanggal_mulai: string
  tanggal_selesai: string
}

export interface Milestone {
  id: number
  project_id: number
  period_id: number | null
  periode: string | null
  nama: string
  deskripsi: string | null
  tanggal_target: string
  target_persentase: number
  status: 'BELUM_TERCAPAI' | 'TERCAPAI' | 'TERLAMBAT'
}

export interface WorkPlanCell {
  period_id: number
  /** Periode berada di antara Periode Mulai dan Periode Selesai pekerjaan. */
  aktif: boolean
  target_volume: number
  target_persentase: number
  target_bobot: number
}

export interface WorkPlanRow {
  work_item_id: number
  uraian_pekerjaan: string
  kategori: string | null
  satuan: string | null
  volume: number
  bobot: number
  period_mulai_id: number | null
  period_selesai_id: number | null
  periode_mulai: string | null
  periode_selesai: string | null
  periode: WorkPlanCell[]
  total_target_volume: number
  total_target_bobot: number
  sisa_volume: number
}

export interface WorkPlanMatrix {
  periode: Period[]
  baris: WorkPlanRow[]
  total_per_periode: { period_id: number; nama_periode: string; rencana: number; kumulatif: number }[]
  total_bobot_pekerjaan: number
  total_bobot_rencana: number
}

export interface CurvePoint {
  period_id: number
  urutan: number
  nama_periode: string
  bulan_ke: number
  minggu_ke: number
  tanggal_mulai: string
  tanggal_selesai: string
  rencana: number
  rencana_kumulatif: number
  aktual: number | null
  aktual_kumulatif: number | null
  deviasi: number | null
}

export interface CurveData {
  titik: CurvePoint[]
  milestones: {
    id: number
    nama: string
    period_id: number | null
    tanggal_target: string
    target_persentase: number
    status: string
  }[]
  ringkasan: {
    total_bobot_rencana: number
    progres_rencana: number
    progres_aktual: number
    deviasi: number
    jumlah_periode: number
  }
  proyek?: { id: number; nama_proyek: string }
}

export interface ProgressDetail {
  id: number
  work_item_id: number
  uraian_pekerjaan: string | null
  satuan: string | null
  volume_rencana: number
  volume_realisasi: number
  persentase_realisasi: number
  bobot_realisasi: number
  keterangan: string | null
}

export interface ProgressPhoto {
  id: number
  progress_report_id: number
  url: string
  caption: string | null
  original_name: string | null
  file_size: number | null
  diunggah_pada: string | null
}

export interface ProgressMaterial {
  id: number
  nama_material: string
  jumlah: number
  unit_id: number | null
  satuan: string | null
  keterangan: string | null
}

export interface ProgressIssue {
  id: number
  work_item_id: number | null
  pekerjaan: string | null
  jenis_kendala: string
  deskripsi: string
  alasan_keterlambatan: string | null
  tindak_lanjut: string | null
  status: string
}

export interface ProgressReport {
  id: number
  project_id: number
  nama_proyek: string | null
  user_id: number
  pelapor: string | null
  period_id: number | null
  periode: string | null
  tanggal_laporan: string
  keterangan: string | null
  lokasi: string | null
  cuaca: string | null
  status: ReportStatus
  status_label: string
  dikirim_pada: string | null
  total_bobot_realisasi: number
  detail?: ProgressDetail[]
  foto?: ProgressPhoto[]
  material?: ProgressMaterial[]
  kendala?: ProgressIssue[]
  created_at: string | null
}

export interface ReportDocument {
  id: number
  project_id: number
  nama_proyek: string | null
  tipe_laporan: ReportType
  format: ExportFormat
  periode_mulai: string | null
  periode_selesai: string | null
  file_name: string
  url: string
  digenerate_pada: string | null
  dibuat_oleh: string | null
}

export interface Paginated<T> {
  data: T[]
  links?: unknown
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

/* ---------- Laporan ---------- */

export interface ReportHeaderData {
  nama_proyek: string
  pekerjaan: string
  lokasi: string
  sumber_dana: string | null
  tahun_anggaran: number | string | null
  nomor_spk: string | null
  nomor_pekerjaan: string | null
  tanggal_spk: string | null
  tanggal_mulai: string
  tanggal_selesai: string
  jangka_waktu_hari: number | null
  kontraktor_pelaksana: string | null
  konsultan_pengawas: string | null
  nama_site_engineer: string | null
  nama_pelaksana_lapangan: string | null
}

export interface ReportVolumeBobot {
  volume: number
  bobot: number
}

export interface WeeklyReportItem {
  no: number
  no_global: number
  work_item_id: number
  uraian: string
  satuan: string | null
  volume: number
  harga_satuan: number | null
  harga_pekerjaan: number | null
  bobot: number
  realisasi_lalu: ReportVolumeBobot
  realisasi_ini: ReportVolumeBobot
  realisasi_sd: ReportVolumeBobot
  keterangan_persen: number
}

export interface MonthlyReportItem {
  no: number
  no_global: number
  work_item_id: number
  uraian: string
  satuan: string | null
  volume: number
  harga_satuan: number | null
  harga_pekerjaan: number | null
  bobot: number
  jadwal: { period_id: number; bobot: number; volume: number }[]
  realisasi_bulan_lalu: ReportVolumeBobot
  realisasi_bulan_ini: ReportVolumeBobot
  realisasi_sd_bulan_ini: ReportVolumeBobot
  keterangan_persen: number
}

export interface ReportCategory<T> {
  id: number | null
  kode: string
  nama: string
  items: T[]
  subtotal: Record<string, number | { bobot: number } | { period_id: number; bobot: number }[]>
}

export interface WeeklyReport {
  header: ReportHeaderData
  periode: {
    period_id: number
    bulan_ke: number
    bulan_ke_romawi: string
    minggu_ke: number
    minggu_ke_romawi: string
    nama_periode: string
    tanggal_mulai: string
    tanggal_selesai: string
  }
  kategori: ReportCategory<WeeklyReportItem>[]
  total: Record<string, number | { bobot: number }>
  rekap: {
    realisasi_sd_minggu_ini: number
    rencana_kumulatif_sd_minggu_ini: number
    deviasi: number
  }
}

export interface MonthlyReport {
  header: ReportHeaderData
  periode: {
    bulan_ke: number
    bulan_ke_romawi: string
    tanggal_mulai: string
    tanggal_selesai: string
    jumlah_bulan: number
  }
  kolom_periode: {
    period_id: number
    bulan_ke: number
    bulan_ke_romawi: string
    minggu_ke: number
    minggu_ke_romawi: string
    nama_periode: string
  }[]
  kategori: ReportCategory<MonthlyReportItem>[]
  total: Record<string, number | { bobot: number } | { period_id: number; bobot: number }[]>
  rekap_periode: {
    period_id: number
    urutan: number
    nama_periode: string
    bulan_ke: number
    minggu_ke: number
    minggu_ke_romawi: string
    tanggal_mulai: string
    tanggal_selesai: string
    rencana_mingguan: number
    rencana_kumulatif: number
    realisasi_mingguan: number | null
    realisasi_kumulatif: number | null
    deviasi: number | null
  }[]
  rekap: {
    realisasi_bulan_lalu: number
    realisasi_bulan_ini: number
    realisasi_sd_bulan_ini: number
    rencana_sd_bulan_ini: number
  }
}

export interface DailyReport {
  header: ReportHeaderData
  periode: { dari: string; sampai: string }
  laporan: {
    id: number
    tanggal_laporan: string
    periode: string | null
    pelapor: string | null
    status: string
    lokasi: string | null
    cuaca: string | null
    keterangan: string | null
    dikirim_pada: string | null
    detail: {
      uraian_pekerjaan: string | null
      satuan: string | null
      volume_rencana: number
      volume_realisasi: number
      persentase_realisasi: number
      bobot_realisasi: number
      keterangan: string | null
    }[]
    material: { nama_material: string; jumlah: number; satuan: string | null; keterangan: string | null }[]
    kendala: {
      jenis_kendala: string
      pekerjaan: string | null
      deskripsi: string
      alasan_keterlambatan: string | null
      tindak_lanjut: string | null
      status: string
    }[]
    foto: { url: string; caption: string | null; diunggah_pada: string | null }[]
  }[]
  ringkasan: { jumlah_laporan: number; jumlah_dikirim: number; bobot_realisasi: number }
}

export interface MilestoneReport {
  header: ReportHeaderData
  milestone: {
    id: number
    nama: string
    deskripsi: string | null
    periode: string | null
    tanggal_target: string
    target_persentase: number
    realisasi_persentase: number | null
    deviasi: number | null
    status: string
  }[]
  kurva: CurveData
}

/* ---------- Dashboard ---------- */

export interface DashboardProjectRow {
  id: number
  nama_proyek: string
  lokasi: string
  nomor_spk: string | null
  tanggal_mulai: string
  tanggal_selesai: string
  qs: string | null
  status: ProjectStatus
  progres_aktual: number
  progres_rencana: number
  deviasi: number
}

export interface DashboardData {
  peran: RoleCode
  kpi: Record<string, number>
  status_proyek?: { status: ProjectStatus; label: string; jumlah: number }[]
  proyek_terbaru?: DashboardProjectRow[]
  proyek?: DashboardProjectRow[]
  proyek_ditugaskan?: DashboardProjectRow[]
  grafik_progres?: { nama_proyek: string; rencana: number; aktual: number }[]
  pekerjaan_perlu_laporan?: {
    work_item_id: number
    project_id: number
    nama_proyek: string | null
    uraian_pekerjaan: string
    satuan: string | null
    volume: number
    volume_realisasi: number
    sisa_volume: number
    persentase: number
  }[]
  laporan_terbaru?: {
    id: number
    nama_proyek: string | null
    pelapor?: string | null
    periode: string | null
    tanggal_laporan: string
    status?: string
    bobot_realisasi?: number
  }[]
}
