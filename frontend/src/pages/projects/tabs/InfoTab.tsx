import { Card } from '@/components/ui/Card'
import { usePeriods } from '@/hooks/queries'
import type { Project } from '@/types'
import { rentangTanggal, tanggal } from '@/utils/format'

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

        <Card title="Periode Pelaksanaan" description={`${periods?.length ?? 0} periode mingguan`}>
          <ul className="flex max-h-72 flex-col gap-1.5 overflow-y-auto text-xs">
            {periods?.map((period) => (
              <li key={period.id} className="flex items-center justify-between gap-2 rounded-lg bg-surface px-3 py-2">
                <span className="font-medium text-ink">{period.nama_periode}</span>
                <span className="text-muted">{rentangTanggal(period.tanggal_mulai, period.tanggal_selesai)}</span>
              </li>
            ))}
          </ul>
        </Card>
      </div>
    </div>
  )
}
