export type RoleCode = 'ADMIN' | 'QS' | 'KONTRAKTOR'

export type ProjectStatus = 'BELUM_DIMULAI' | 'BERJALAN' | 'SELESAI' | 'TERLAMBAT'

export type ReportStatus = 'DRAFT' | 'DIKIRIM'

export type ReportType = 'MINGGUAN' | 'BULANAN' | 'AKHIR'

export type ExportFormat = 'EXCEL' | 'WORD'

export interface Role {
  id: number
  code: RoleCode
  name: string
}

export interface User {
  id: number
  name: string
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

export interface ProgressIssue {
  id: number
  work_item_id: number | null
  pekerjaan: string | null
  jenis_kendala: string
  deskripsi: string
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
    realisasi_minggu_lalu: number
    realisasi_minggu_ini: number
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
    deviasi: number
  }
}

export interface FinalReportItem {
  no: number
  no_global: number
  work_item_id: number
  uraian: string
  satuan: string | null
  volume: number
  harga_satuan: number | null
  harga_pekerjaan: number | null
  bobot: number
  rencana_sd: ReportVolumeBobot
  persen_rencana: number
  realisasi_bulan_lalu: ReportVolumeBobot
  realisasi_bulan_ini: ReportVolumeBobot
  realisasi_sd_bulan_ini: ReportVolumeBobot
  keterangan_persen: number
  sisa_volume: number
  selisih_volume: number
  selisih_bobot: number
  selesai: boolean
}

export interface FinalReportWork {
  uraian: string
  satuan: string | null
  volume: number
  bobot: number
}

export interface FinalReportIssue {
  tanggal_laporan: string
  nama_periode: string | null
  jenis_kendala: string | null
  pekerjaan: string | null
  deskripsi: string
  tindak_lanjut: string | null
  status: string
}

export interface FinalReportPhoto {
  id: number
  file_path: string
  url: string
  caption: string | null
  diunggah_pada: string | null
  tanggal_laporan: string
  nama_periode: string | null
  lokasi: string | null
  uraian_pekerjaan: string | null
  keterangan: string | null
}

/** Satu baris rekap progres; realisasi `null` berarti periode belum berjalan. */
export interface FinalReportProgressRow {
  rencana: number
  rencana_kumulatif: number
  realisasi: number | null
  realisasi_kumulatif: number | null
  deviasi: number | null
}

export interface FinalReport {
  header: ReportHeaderData
  status_proyek: { kode: ProjectStatus; label: string }
  keterangan_proyek: string | null
  kurva_s: { titik: CurvePoint[]; total_bobot_rencana: number }
  /** `null` bila belum ada laporan progres yang dikirim. */
  laporan_terakhir: {
    tanggal_laporan: string
    period_id: number
    nama_periode: string
    minggu_ke: number
    minggu_ke_romawi: string
    bulan_ke: number
    bulan_ke_romawi: string
    tanggal_mulai: string
    tanggal_selesai: string
    bulan_tanggal_mulai: string
    bulan_tanggal_selesai: string
    jumlah_laporan: number
  } | null
  kategori: ReportCategory<FinalReportItem>[]
  total: Record<string, number | { bobot: number }> | null
  rekap_bulanan: ({
    bulan_ke: number
    bulan_ke_romawi: string
    tanggal_mulai: string
    tanggal_selesai: string
    jumlah_minggu: number
    jumlah_laporan: number
    pekerjaan: FinalReportWork[]
    kendala: FinalReportIssue[]
  } & FinalReportProgressRow)[]
  rekap_mingguan: (CurvePoint & {
    minggu_ke_romawi: string
    jumlah_laporan: number
    pekerjaan: FinalReportWork[]
    kendala: FinalReportIssue[]
    catatan: string[]
  })[]
  dokumentasi: FinalReportPhoto[]
  kendala: FinalReportIssue[]
  ringkasan: {
    realisasi_periode_terakhir: number
    realisasi_bulan_lalu: number
    realisasi_bulan_terakhir: number
    realisasi_kumulatif: number
    rencana_kumulatif: number
    deviasi: number
    total_rencana: number
    sisa_progres: number
    jumlah_pekerjaan: number
    jumlah_pekerjaan_selesai: number
    jumlah_kendala: number
    jumlah_kendala_terbuka: number
    jumlah_foto: number
  } | null
  /** Kalimat kesimpulan yang disusun backend dari angka laporan. */
  kesimpulan: string[]
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
  /** Hanya pada dashboard Kontraktor: proyek belum selesai dengan realisasi di bawah rencana. */
  tertinggal?: boolean
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
