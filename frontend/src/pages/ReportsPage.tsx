import { DailyReportTable } from '@/components/reports/DailyReportTable'
import { MonthlyReportTable } from '@/components/reports/MonthlyReportTable'
import { ReportCurveSection } from '@/components/reports/ReportCurveSection'
import { ReportHeader } from '@/components/reports/ReportHeader'
import { WeeklyReportTable } from '@/components/reports/WeeklyReportTable'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, Select } from '@/components/ui/Field'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import {
  useDailyReport,
  useMilestoneReport,
  useMonthlyReport,
  usePeriods,
  useProjects,
  useWeeklyReport,
} from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { reportService, type ExportParams } from '@/services/reportService'
import { angka, rentangTanggal, romawi, tanggalSingkat } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FileSearch, FileSpreadsheet, FileText, Printer } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

type Jenis = 'harian' | 'mingguan' | 'bulanan' | 'milestone'

const JENIS: { key: Jenis; label: string }[] = [
  { key: 'harian', label: 'Laporan Harian' },
  { key: 'mingguan', label: 'Laporan Mingguan' },
  { key: 'bulanan', label: 'Laporan Bulanan' },
  { key: 'milestone', label: 'Laporan Milestone' },
]

// Laporan Kontraktor: mingguan & bulanan, milestone ditampilkan di dalam kedua laporan tersebut.
const JENIS_KONTRAKTOR: Jenis[] = ['mingguan', 'bulanan']

export function ReportsPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const { punyaPeran } = useAuth()
  const [params, setParams] = useSearchParams()

  const daftarJenis = punyaPeran('KONTRAKTOR') ? JENIS.filter((item) => JENIS_KONTRAKTOR.includes(item.key)) : JENIS
  const jenisAwal = daftarJenis.find((item) => item.key === params.get('jenis'))?.key ?? 'mingguan'

  const [jenis, setJenis] = useState<Jenis>(jenisAwal)
  const [projectId, setProjectId] = useState<number | null>(params.get('project_id') ? Number(params.get('project_id')) : null)
  const [periodId, setPeriodId] = useState<number | undefined>(undefined)
  const [bulanKe, setBulanKe] = useState(1)
  const [dari, setDari] = useState('')
  const [sampai, setSampai] = useState('')

  const { data: projects, isLoading: memuatProyek } = useProjects({ per_page: 100 })
  const { data: periods } = usePeriods(projectId)

  useEffect(() => {
    if (!projectId && projects && projects.data.length > 0) {
      setProjectId(projects.data[0].id)
    }
  }, [projects, projectId])

  useEffect(() => {
    if (periods && periods.length > 0 && !periods.some((period) => period.id === periodId)) {
      setPeriodId(periods[periods.length - 1].id)
    }
  }, [periods, periodId])

  const harian = useDailyReport(jenis === 'harian' ? projectId : null, dari || undefined, sampai || undefined)
  const mingguan = useWeeklyReport(jenis === 'mingguan' ? projectId : null, periodId)
  const bulanan = useMonthlyReport(jenis === 'bulanan' ? projectId : null, bulanKe)
  const milestone = useMilestoneReport(jenis === 'milestone' ? projectId : null)

  const aktif = jenis === 'harian' ? harian : jenis === 'mingguan' ? mingguan : jenis === 'bulanan' ? bulanan : milestone

  const paramExport = (): ExportParams | null => {
    if (!projectId || jenis === 'milestone') return null

    if (jenis === 'mingguan') return { tipe: 'MINGGUAN', project_id: projectId, period_id: periodId }
    if (jenis === 'bulanan') return { tipe: 'BULANAN', project_id: projectId, bulan_ke: bulanKe }

    return { tipe: 'HARIAN', project_id: projectId, dari: dari || undefined, sampai: sampai || undefined }
  }

  const ekspor = useMutation({
    mutationFn: async ({ format }: { format: 'EXCEL' | 'WORD' }) => {
      const payload = paramExport()

      if (!payload) {
        throw new Error('Jenis laporan ini belum mendukung export.')
      }

      return format === 'EXCEL' ? reportService.exportExcel(payload) : reportService.exportWord(payload)
    },
    onSuccess: async (dokumen) => {
      toast.sukses('Laporan berhasil dibuat. Unduhan akan dimulai.')
      window.open(dokumen.url, '_blank', 'noopener')
      await queryClient.invalidateQueries({ queryKey: ['report', 'documents'] })
    },
    onError: (error) => toast.gagal(pesanError(error)),
  })

  const bulanTersedia = periods ? Array.from(new Set(periods.map((period) => period.bulan_ke))) : [1]

  const labelJenis = daftarJenis.find((item) => item.key === jenis)?.label ?? 'Laporan'
  const proyekTerpilih = projects?.data.find((project) => project.id === projectId)
  const periodeMinggu = periods?.find((period) => period.id === periodId)
  const periodeBulan = periods?.filter((period) => period.bulan_ke === bulanKe) ?? []

  const labelPeriode =
    jenis === 'mingguan'
      ? periodeMinggu
        ? `${periodeMinggu.nama_periode} · ${rentangTanggal(periodeMinggu.tanggal_mulai, periodeMinggu.tanggal_selesai)}`
        : '-'
      : jenis === 'bulanan'
        ? `Bulan ${romawi(bulanKe)}${periodeBulan.length > 0 ? ` · ${rentangTanggal(periodeBulan[0].tanggal_mulai, periodeBulan[periodeBulan.length - 1].tanggal_selesai)}` : ''}`
        : jenis === 'harian'
          ? dari || sampai
            ? rentangTanggal(dari || proyekTerpilih?.tanggal_mulai, sampai || proyekTerpilih?.tanggal_selesai)
            : 'Seluruh masa pelaksanaan'
          : 'Seluruh periode'

  const siap = !aktif.isLoading && !aktif.error && Boolean(aktif.data)
  const bisaEkspor = siap && jenis !== 'milestone'

  if (memuatProyek) return <LoadingState pesan="Memuat daftar proyek..." />
  if (!projects || projects.data.length === 0) {
    return <EmptyState judul="Belum ada proyek" pesan="Laporan tersedia setelah proyek dan progres tersedia." />
  }

  return (
    <div className="flex flex-col gap-4">
      <Card title="Buat Laporan" description="Pilih proyek, jenis laporan, dan periode. Pratinjau diperbarui otomatis." className="no-print" flush>
        <div className="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.4fr)]">
          <Select
            label="Proyek"
            value={projectId ?? ''}
            onChange={(event) => {
              const nilai = Number(event.target.value)
              setProjectId(nilai)
              params.set('project_id', String(nilai))
              setParams(params, { replace: true })
            }}
            wrapClassName="sm:col-span-2 lg:col-span-1"
          >
            {projects.data.map((project) => (
              <option key={project.id} value={project.id}>
                {project.nama_proyek}
              </option>
            ))}
          </Select>

          <Select
            label="Jenis Laporan"
            value={jenis}
            onChange={(event) => {
              const nilai = event.target.value as Jenis
              setJenis(nilai)
              params.set('jenis', nilai)
              setParams(params, { replace: true })
            }}
          >
            {daftarJenis.map((item) => (
              <option key={item.key} value={item.key}>
                {item.label}
              </option>
            ))}
          </Select>

          {jenis === 'mingguan' && (
            <Select label="Periode" value={periodId ?? ''} onChange={(event) => setPeriodId(Number(event.target.value))} disabled={!periods?.length}>
              {!periods?.length && <option value="">Belum ada periode</option>}
              {periods?.map((period) => (
                <option key={period.id} value={period.id}>
                  {period.nama_periode} ({tanggalSingkat(period.tanggal_mulai)} - {tanggalSingkat(period.tanggal_selesai)})
                </option>
              ))}
            </Select>
          )}

          {jenis === 'bulanan' && (
            <Select label="Periode" value={bulanKe} onChange={(event) => setBulanKe(Number(event.target.value))}>
              {bulanTersedia.map((bulan) => (
                <option key={bulan} value={bulan}>
                  Bulan {romawi(bulan)}
                </option>
              ))}
            </Select>
          )}

          {jenis === 'harian' && (
            <div className="grid grid-cols-2 gap-3">
              <DatePicker label="Dari tanggal" value={dari} max={sampai || undefined} onChange={(event) => setDari(event.target.value)} />
              <DatePicker label="Sampai tanggal" value={sampai} min={dari || undefined} onChange={(event) => setSampai(event.target.value)} />
            </div>
          )}
        </div>

        <div className="flex flex-col gap-3 border-t border-line bg-surface/50 px-4 py-3 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
          <p className="min-w-0 text-xs text-muted">
            <span className="font-medium text-ink">{labelJenis}</span> · {labelPeriode}
          </p>
          <div className="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
            <Button variant="ghost" icon={<Printer className="size-4" />} disabled={!siap} onClick={() => window.print()} className="order-3 sm:order-1">
              Cetak
            </Button>
            <Button
              variant="outline"
              icon={<FileText className="size-4" />}
              loading={ekspor.isPending && ekspor.variables?.format === 'WORD'}
              disabled={!bisaEkspor || ekspor.isPending}
              onClick={() => ekspor.mutate({ format: 'WORD' })}
              className="order-2"
            >
              Export Word
            </Button>
            <Button
              icon={<FileSpreadsheet className="size-4" />}
              loading={ekspor.isPending && ekspor.variables?.format === 'EXCEL'}
              disabled={!bisaEkspor || ekspor.isPending}
              onClick={() => ekspor.mutate({ format: 'EXCEL' })}
              className="order-1 col-span-2 sm:order-3"
            >
              Export Excel
            </Button>
          </div>
        </div>
      </Card>

      <section className="overflow-hidden rounded-[0.875rem] border border-line bg-canvas" aria-label="Pratinjau laporan">
        <header className="no-print flex flex-wrap items-center justify-between gap-2 border-b border-line bg-white px-4 py-2.5 sm:px-5">
          <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted uppercase">
            <FileSearch className="size-4" aria-hidden />
            Pratinjau Dokumen
          </p>
          <p className="text-[11px] text-muted md:hidden">Geser tabel ke samping untuk melihat semua kolom.</p>
        </header>

        <div className="p-2 sm:p-4 lg:p-6">
          <div className="print-area mx-auto max-w-[1600px] rounded-sm bg-white shadow-sm ring-1 ring-black/5">
            {aktif.isLoading ? (
              <LoadingState pesan="Menyusun laporan..." className="py-24" />
            ) : aktif.error ? (
              <div className="py-12">
                <ErrorState pesan={pesanError(aktif.error)} onRetry={() => void aktif.refetch()} />
              </div>
            ) : !aktif.data ? (
              <div className="py-12">
                <EmptyState
                  judul="Laporan belum dapat ditampilkan"
                  pesan={jenis === 'mingguan' && !periods?.length ? 'Proyek ini belum memiliki periode pelaksanaan.' : 'Pilih proyek dan periode laporan.'}
                />
              </div>
            ) : (
              <>
                {jenis === 'harian' && harian.data && <DailyReportTable data={harian.data} />}
                {jenis === 'mingguan' && mingguan.data && <WeeklyReportTable data={mingguan.data} />}
                {jenis === 'bulanan' && bulanan.data && <MonthlyReportTable data={bulanan.data} />}
                {jenis === 'milestone' && milestone.data && (
                  <div className="bg-white p-4 sm:p-6">
                    <ReportHeader
                      judul="LAPORAN MILESTONE"
                      header={milestone.data.header}
                      kanan={[
                        { label: 'Progres Rencana', nilai: `${angka(milestone.data.kurva.ringkasan.progres_rencana, 2)}%` },
                        { label: 'Progres Realisasi', nilai: `${angka(milestone.data.kurva.ringkasan.progres_aktual, 2)}%` },
                        { label: 'Deviasi', nilai: `${angka(milestone.data.kurva.ringkasan.deviasi, 2)}%` },
                        { label: 'Kontraktor Pelaksana', nilai: milestone.data.header.kontraktor_pelaksana ?? '-' },
                      ]}
                    />

                    <ReportCurveSection kurva={milestone.data.kurva} milestone={milestone.data.milestone} />
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </section>
    </div>
  )
}
