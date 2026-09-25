import { CurveSChart } from '@/components/charts/CurveSChart'
import { DailyReportTable } from '@/components/reports/DailyReportTable'
import { MonthlyReportTable } from '@/components/reports/MonthlyReportTable'
import { ReportHeader } from '@/components/reports/ReportHeader'
import { WeeklyReportTable } from '@/components/reports/WeeklyReportTable'
import { Badge, DeviationBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, Select } from '@/components/ui/Field'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import {
  useDailyReport,
  useMilestoneReport,
  useMonthlyReport,
  usePeriods,
  useProjects,
  useWeeklyReport,
} from '@/hooks/queries'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { reportService, type ExportParams } from '@/services/reportService'
import { angka, tanggalSingkat } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FileSpreadsheet, FileText, Printer } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

type Jenis = 'harian' | 'mingguan' | 'bulanan' | 'milestone'

const JENIS: { key: Jenis; label: string }[] = [
  { key: 'harian', label: 'Laporan Harian' },
  { key: 'mingguan', label: 'Laporan Mingguan' },
  { key: 'bulanan', label: 'Laporan Bulanan' },
  { key: 'milestone', label: 'Laporan Milestone' },
]

export function ReportsPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()

  const [jenis, setJenis] = useState<Jenis>((params.get('jenis') as Jenis) ?? 'mingguan')
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

  if (memuatProyek) return <LoadingState />
  if (!projects || projects.data.length === 0) {
    return <EmptyState judul="Belum ada proyek" pesan="Laporan tersedia setelah proyek dan progres tersedia." />
  }

  return (
    <div className="flex flex-col gap-4">
      <Card title="Filter Laporan" description="Pilih proyek, jenis laporan, dan periode yang ingin ditampilkan." className="no-print">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="Proyek"
            value={projectId ?? ''}
            onChange={(event) => {
              const nilai = Number(event.target.value)
              setProjectId(nilai)
              params.set('project_id', String(nilai))
              setParams(params, { replace: true })
            }}
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
            {JENIS.map((item) => (
              <option key={item.key} value={item.key}>
                {item.label}
              </option>
            ))}
          </Select>

          {jenis === 'mingguan' && (
            <Select label="Periode" value={periodId ?? ''} onChange={(event) => setPeriodId(Number(event.target.value))}>
              {periods?.map((period) => (
                <option key={period.id} value={period.id}>
                  {period.nama_periode} ({tanggalSingkat(period.tanggal_mulai)} - {tanggalSingkat(period.tanggal_selesai)})
                </option>
              ))}
            </Select>
          )}

          {jenis === 'bulanan' && (
            <Select label="Bulan Ke" value={bulanKe} onChange={(event) => setBulanKe(Number(event.target.value))}>
              {bulanTersedia.map((bulan) => (
                <option key={bulan} value={bulan}>
                  Bulan {bulan}
                </option>
              ))}
            </Select>
          )}

          {jenis === 'harian' && (
            <>
              <DatePicker label="Dari Tanggal" value={dari} onChange={(event) => setDari(event.target.value)} />
              <DatePicker label="Sampai Tanggal" value={sampai} onChange={(event) => setSampai(event.target.value)} />
            </>
          )}
        </div>

        <div className="mt-4 flex flex-wrap gap-2">
          <Button
            variant="outline"
            icon={<FileSpreadsheet className="size-4" />}
            loading={ekspor.isPending && ekspor.variables?.format === 'EXCEL'}
            disabled={jenis === 'milestone'}
            onClick={() => ekspor.mutate({ format: 'EXCEL' })}
          >
            Export Excel
          </Button>
          <Button
            variant="outline"
            icon={<FileText className="size-4" />}
            loading={ekspor.isPending && ekspor.variables?.format === 'WORD'}
            disabled={jenis === 'milestone'}
            onClick={() => ekspor.mutate({ format: 'WORD' })}
          >
            Export Word
          </Button>
          <Button variant="ghost" icon={<Printer className="size-4" />} onClick={() => window.print()}>
            Cetak
          </Button>
        </div>
      </Card>

      <Card bodyClassName="p-0">
        {aktif.isLoading ? (
          <LoadingState />
        ) : aktif.error ? (
          <ErrorState pesan={pesanError(aktif.error)} onRetry={() => void aktif.refetch()} />
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

                <CurveSChart data={milestone.data.kurva} tinggi={320} />

                <div className="mt-5">
                  {milestone.data.milestone.length === 0 ? (
                    <EmptyState judul="Belum ada milestone" pesan="Tambahkan milestone pada proyek untuk menandai tahapan penting." />
                  ) : (
                    <TableWrap>
                      <Table>
                        <thead>
                          <tr>
                            <Th>Milestone</Th>
                            <Th>Periode</Th>
                            <Th>Tanggal Target</Th>
                            <Th align="right">Target (%)</Th>
                            <Th align="right">Realisasi (%)</Th>
                            <Th align="center">Deviasi</Th>
                            <Th align="center">Status</Th>
                          </tr>
                        </thead>
                        <tbody>
                          {milestone.data.milestone.map((item) => (
                            <tr key={item.id}>
                              <Td>
                                <p className="font-medium">{item.nama}</p>
                                {item.deskripsi && <p className="text-[11px] text-muted">{item.deskripsi}</p>}
                              </Td>
                              <Td className="text-muted">{item.periode ?? '-'}</Td>
                              <Td className="text-muted">{tanggalSingkat(item.tanggal_target)}</Td>
                              <Td align="right">{angka(item.target_persentase, 2)}</Td>
                              <Td align="right">{item.realisasi_persentase === null ? '-' : angka(item.realisasi_persentase, 2)}</Td>
                              <Td align="center">
                                <DeviationBadge nilai={item.deviasi} />
                              </Td>
                              <Td align="center">
                                <Badge tone={item.status === 'TERCAPAI' ? 'success' : item.status === 'TERLAMBAT' ? 'danger' : 'neutral'}>
                                  {item.status.replace('_', ' ')}
                                </Badge>
                              </Td>
                            </tr>
                          ))}
                        </tbody>
                      </Table>
                    </TableWrap>
                  )}
                </div>
              </div>
            )}
          </>
        )}
      </Card>
    </div>
  )
}
