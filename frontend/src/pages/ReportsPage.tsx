import { FinalReportTable } from '@/components/reports/FinalReportTable'
import { MonthlyReportTable } from '@/components/reports/MonthlyReportTable'
import { WeeklyReportTable } from '@/components/reports/WeeklyReportTable'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Dropdown } from '@/components/ui/Dropdown'
import { Select } from '@/components/ui/Field'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { useFinalReport, useMonthlyReport, usePeriods, useProjects, useWeeklyReport } from '@/hooks/queries'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { reportService, type ExportParams } from '@/services/reportService'
import { rentangTanggal, romawi, tanggal, tanggalSingkat } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FileSearch, FileSpreadsheet } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

type Jenis = 'mingguan' | 'bulanan' | 'akhir'

/** Jenis laporan Kontraktor (satu-satunya peran yang membuka halaman ini). */
const JENIS: { key: Jenis; label: string }[] = [
  { key: 'mingguan', label: 'Laporan Mingguan' },
  { key: 'bulanan', label: 'Laporan Bulanan' },
  { key: 'akhir', label: 'Laporan Akhir' },
]

export function ReportsPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()

  const jenisAwal = JENIS.find((item) => item.key === params.get('jenis'))?.key ?? 'mingguan'

  const [jenis, setJenis] = useState<Jenis>(jenisAwal)
  const [projectId, setProjectId] = useState<number | null>(params.get('project_id') ? Number(params.get('project_id')) : null)
  const [periodId, setPeriodId] = useState<number | undefined>(undefined)
  const [bulanKe, setBulanKe] = useState(1)

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

  const mingguan = useWeeklyReport(jenis === 'mingguan' ? projectId : null, periodId)
  const bulanan = useMonthlyReport(jenis === 'bulanan' ? projectId : null, bulanKe)
  const akhir = useFinalReport(jenis === 'akhir' ? projectId : null)

  const aktif = { mingguan, bulanan, akhir }[jenis]

  const paramExport = (): ExportParams | null => {
    if (!projectId || jenis === 'akhir') return null

    return jenis === 'mingguan'
      ? { tipe: 'MINGGUAN', project_id: projectId, period_id: periodId }
      : { tipe: 'BULANAN', project_id: projectId, bulan_ke: bulanKe }
  }

  const ekspor = useMutation({
    mutationFn: async () => {
      if (jenis === 'akhir' && projectId) return reportService.exportFinalExcel(projectId)

      const payload = paramExport()

      if (!payload) {
        throw new Error('Jenis laporan ini belum mendukung export.')
      }

      return reportService.exportExcel(payload)
    },
    onSuccess: async (dokumen) => {
      toast.sukses('Laporan berhasil dibuat. Unduhan akan dimulai.')
      window.open(dokumen.url, '_blank', 'noopener')
      await queryClient.invalidateQueries({ queryKey: ['report', 'documents'] })
    },
    onError: (error) => toast.gagal(pesanError(error)),
  })

  const bulanTersedia = periods ? Array.from(new Set(periods.map((period) => period.bulan_ke))) : [1]

  const labelJenis = JENIS.find((item) => item.key === jenis)?.label ?? 'Laporan'
  const periodeMinggu = periods?.find((period) => period.id === periodId)
  const periodeBulan = periods?.filter((period) => period.bulan_ke === bulanKe) ?? []

  const labelPeriode =
    jenis === 'mingguan'
      ? periodeMinggu
        ? `${periodeMinggu.nama_periode} · ${rentangTanggal(periodeMinggu.tanggal_mulai, periodeMinggu.tanggal_selesai)}`
        : '-'
      : jenis === 'bulanan'
        ? `Bulan ${romawi(bulanKe)}${periodeBulan.length > 0 ? ` · ${rentangTanggal(periodeBulan[0].tanggal_mulai, periodeBulan[periodeBulan.length - 1].tanggal_selesai)}` : ''}`
        : akhir.data?.laporan_terakhir
          ? `s/d laporan progres ${tanggal(akhir.data.laporan_terakhir.tanggal_laporan)} (${akhir.data.laporan_terakhir.nama_periode})`
          : 'Belum ada laporan progres'

  // Laporan akhir baru dapat direkap setelah ada laporan progres yang dikirim.
  const akhirKosong = jenis === 'akhir' && akhir.data?.laporan_terakhir === null
  const siap = !aktif.isLoading && !aktif.error && Boolean(aktif.data) && !akhirKosong
  const bisaEkspor = siap

  if (memuatProyek) return <LoadingState pesan="Memuat daftar proyek..." />
  if (!projects || projects.data.length === 0) {
    return <EmptyState judul="Belum ada proyek" pesan="Laporan tersedia setelah proyek dan progres tersedia." />
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="no-print">
        <h2 className="text-lg font-semibold text-ink">Laporan</h2>
        <p className="text-xs text-muted">Pembuatan laporan progres mingguan, bulanan, dan laporan akhir.</p>
      </div>
      <Card title="Buat Laporan" description="Pilih proyek, jenis laporan, dan periode. Pratinjau diperbarui otomatis." className="no-print" flush>
        <div className="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.4fr)]">
          <Dropdown
            label="Proyek"
            value={projectId ? String(projectId) : ''}
            options={projects.data.map((project) => ({ value: String(project.id), label: project.nama_proyek }))}
            onChange={(nilai) => {
              setProjectId(Number(nilai))
              params.set('project_id', nilai)
              setParams(params, { replace: true })
            }}
            placeholder="Pilih proyek"
            wrapClassName="sm:col-span-2 lg:col-span-1"
          />

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
            {JENIS.map((item) => (
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
        </div>

        <div className="flex flex-col gap-3 border-t border-line bg-surface/50 px-4 py-3 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
          <p className="min-w-0 text-xs text-muted">
            <span className="font-medium text-ink">{labelJenis}</span> · {labelPeriode}
          </p>
          <div className="flex sm:justify-end">
            <Button
              icon={<FileSpreadsheet className="size-4" />}
              loading={ekspor.isPending}
              disabled={!bisaEkspor || ekspor.isPending}
              onClick={() => ekspor.mutate()}
              className="w-full sm:w-auto"
            >
              {jenis === 'akhir' ? 'Unduh Excel' : 'Unduh Excel'}
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
            ) : akhirKosong ? (
              <div className="py-12">
                <EmptyState
                  judul="Laporan Akhir belum dapat dibuat"
                  pesan="Belum tersedia data laporan. Laporan Akhir merekap laporan progres yang sudah dikirim QS, sehingga dapat dibuat setelah ada laporan progres pertama."
                />
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
                {jenis === 'mingguan' && mingguan.data && <WeeklyReportTable data={mingguan.data} />}
                {jenis === 'bulanan' && bulanan.data && <MonthlyReportTable data={bulanan.data} />}
                {jenis === 'akhir' && akhir.data && <FinalReportTable data={akhir.data} />}
              </>
            )}
          </div>
        </div>
      </section>
    </div>
  )
}
