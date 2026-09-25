import { Card } from '@/components/ui/Card'
import { usePeriods } from '@/hooks/queries'
import { PeriodDetailModal } from '@/pages/projects/PeriodDetailModal'
import type { Period, Project } from '@/types'
import { cn } from '@/utils/cn'
import { hariIni, rentangTanggal, tanggal } from '@/utils/format'
import { ChevronRight } from 'lucide-react'
import { useState } from 'react'

function Baris({ label, nilai }: { label: string; nilai: string | number | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5 border-b border-line py-2 last:border-b-0 sm:flex-row sm:gap-3">
      <dt className="w-52 shrink-0 text-xs text-muted">{label}</dt>
      <dd className="text-sm text-ink">{nilai === null || nilai === undefined || nilai === '' ? '-' : nilai}</dd>
    </div>
  )
}

export function InfoTab({ project }: { project: Project }) {
  const { data: periods } = usePeriods(project.id)
  const [periodeDipilih, setPeriodeDipilih] = useState<Period | null>(null)
  const hari = hariIni()

  return (
    <div className="grid gap-4 lg:grid-cols-3">
      <Card title="Informasi Proyek" className="lg:col-span-2">
        <dl>
          <Baris label="Nama Proyek" nilai={project.nama_proyek} />
          <Baris label="Nomor SPK" nilai={project.nomor_spk} />
          <Baris label="Nomor Pekerjaan" nilai={project.nomor_pekerjaan} />
          <Baris label="Nomor Proyek" nilai={project.nomor_proyek} />
          <Baris label="Lokasi" nilai={project.lokasi} />
          <Baris label="Sumber Dana" nilai={project.sumber_dana} />
          <Baris label="Tahun Anggaran" nilai={project.tahun_anggaran} />
          <Baris label="Tanggal SPK" nilai={tanggal(project.tanggal_spk)} />
          <Baris label="Waktu Pelaksanaan" nilai={rentangTanggal(project.tanggal_mulai, project.tanggal_selesai)} />
          <Baris label="Jangka Waktu" nilai={project.jangka_waktu_hari ? `${project.jangka_waktu_hari} hari kalender` : null} />
          <Baris label="QS Penanggung Jawab" nilai={project.qs?.name} />
          <Baris label="Keterangan" nilai={project.keterangan} />
        </dl>
      </Card>

      <div className="flex flex-col gap-4">
        <Card title="Pelaksana &amp; Pengawas">
          <dl>
            <Baris label="Kontraktor Pelaksana" nilai={project.kontraktor_pelaksana} />
            <Baris label="Konsultan Pengawas" nilai={project.konsultan_pengawas} />
            <Baris label="Site Engineer" nilai={project.nama_site_engineer} />
            <Baris label="Pelaksana Lapangan" nilai={project.nama_pelaksana_lapangan} />
          </dl>
        </Card>

        <Card title="Periode Pelaksanaan" description={`${periods?.length ?? 0} periode mingguan · klik untuk melihat rincian`}>
          <ul className="flex max-h-72 flex-col gap-1.5 overflow-y-auto text-xs">
            {periods?.map((period) => {
              const berjalan = hari >= period.tanggal_mulai && hari <= period.tanggal_selesai

              return (
                <li key={period.id}>
                  <button
                    type="button"
                    onClick={() => setPeriodeDipilih(period)}
                    className={cn(
                      'group flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left transition-colors',
                      berjalan ? 'border-navy/30 bg-[#e8edfb]' : 'border-transparent bg-surface hover:border-line hover:bg-white',
                    )}
                  >
                    <span className="font-medium text-ink group-hover:text-primary">{period.nama_periode}</span>
                    {berjalan && <span className="rounded-full bg-navy px-1.5 py-0.5 text-[10px] font-medium text-white">Minggu ini</span>}
                    <span className="ml-auto text-muted">{rentangTanggal(period.tanggal_mulai, period.tanggal_selesai)}</span>
                    <ChevronRight className="size-3.5 shrink-0 text-muted group-hover:text-primary" aria-hidden />
                  </button>
                </li>
              )
            })}
          </ul>
        </Card>
      </div>

      {periodeDipilih && <PeriodDetailModal projectId={project.id} period={periodeDipilih} onClose={() => setPeriodeDipilih(null)} />}
    </div>
  )
}
