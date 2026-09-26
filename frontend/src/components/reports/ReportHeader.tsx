import type { ReportHeaderData } from '@/types'
import { tanggal } from '@/utils/format'

interface ReportHeaderProps {
  judul: string
  header: ReportHeaderData
  kanan: { label: string; nilai: string }[]
}

/** Blok identitas proyek pada laporan, mengikuti format dokumen resmi. */
export function ReportHeader({ judul, header, kanan }: ReportHeaderProps) {
  const kiri = [
    ['PEKERJAAN', header.nama_proyek],
    ['LOKASI', header.lokasi],
    ['SUMBER DANA', header.sumber_dana ?? '-'],
    ['TAHUN ANGGARAN', String(header.tahun_anggaran ?? '-')],
    ['NO. SPK', header.nomor_spk ?? '-'],
    ['TANGGAL SPK', tanggal(header.tanggal_spk)],
  ]

  return (
    <header className="mb-4">
      <h2 className="mb-4 text-center text-base font-bold tracking-wide text-ink underline">{judul}</h2>

      <div className="grid gap-4 text-xs lg:grid-cols-2">
        <dl className="flex flex-col gap-1">
          {kiri.map(([label, nilai]) => (
            <div key={label} className="flex gap-2">
              <dt className="w-32 shrink-0 font-semibold text-ink">{label}</dt>
              <dd className="flex-1 text-ink">: {nilai}</dd>
            </div>
          ))}
        </dl>

        <dl className="flex flex-col gap-1">
          {kanan.map((item) => (
            <div key={item.label} className="flex gap-2">
              <dt className="w-40 shrink-0 font-semibold text-ink">{item.label}</dt>
              <dd className="flex-1 text-ink">: {item.nilai}</dd>
            </div>
          ))}
        </dl>
      </div>
    </header>
  )
}

/** Blok tanda tangan sesuai dokumen laporan asli. */
export function ReportSignature({ header, tanggalDokumen }: { header: ReportHeaderData; tanggalDokumen: string }) {
  return (
    <div className="report-section mt-8 grid gap-8 text-center text-xs sm:grid-cols-2">
      <div>
        <p>Diperiksa,</p>
        <p>Konsultan Pengawas</p>
        <p className="font-medium">{header.konsultan_pengawas ?? '-'}</p>
        <p className="mt-12 font-semibold underline">{header.nama_site_engineer ?? '-'}</p>
        <p>Site Engineer</p>
      </div>
      <div>
        <p>Bontang, {tanggal(tanggalDokumen)}</p>
        <p>Dibuat Oleh</p>
        <p className="font-medium">{header.kontraktor_pelaksana ?? '-'}</p>
        <p className="mt-12 font-semibold underline">{header.nama_pelaksana_lapangan ?? '-'}</p>
        <p>Pelaksana Lapangan</p>
      </div>
    </div>
  )
}
