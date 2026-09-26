import { ReportHeader } from '@/components/reports/ReportHeader'
import { Badge } from '@/components/ui/Badge'
import { EmptyState } from '@/components/ui/State'
import type { DailyReport } from '@/types'
import { angka, rentangTanggal, tanggal, waktu } from '@/utils/format'

/** Laporan harian: rincian pekerjaan, kendala, dan foto per tanggal laporan. */
export function DailyReportTable({ data }: { data: DailyReport }) {
  return (
    <div className="bg-white p-4 sm:p-6">
      <ReportHeader
        judul="LAPORAN HARIAN"
        header={data.header}
        kanan={[
          { label: 'Periode', nilai: rentangTanggal(data.periode.dari, data.periode.sampai) },
          { label: 'Jumlah Laporan', nilai: String(data.ringkasan.jumlah_laporan) },
          { label: 'Laporan Terkirim', nilai: String(data.ringkasan.jumlah_dikirim) },
          { label: 'Total Bobot Realisasi', nilai: `${angka(data.ringkasan.bobot_realisasi, 2)}%` },
          { label: 'Kontraktor Pelaksana', nilai: data.header.kontraktor_pelaksana ?? '-' },
        ]}
      />

      {data.laporan.length === 0 ? (
        <EmptyState judul="Belum ada laporan pada periode ini" />
      ) : (
        <div className="flex flex-col gap-5">
          {data.laporan.map((laporan) => (
            <article key={laporan.id} className="rounded-xl border border-line p-4">
              <header className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                  <h3 className="text-sm font-semibold text-ink">{tanggal(laporan.tanggal_laporan)}</h3>
                  <p className="text-[11px] text-muted">
                    {laporan.periode ?? '-'} - Pelapor {laporan.pelapor ?? '-'}
                    {laporan.dikirim_pada ? ` - Dikirim ${waktu(laporan.dikirim_pada)}` : ''}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  {laporan.lokasi && <Badge tone="neutral">Lokasi: {laporan.lokasi}</Badge>}
                  {laporan.cuaca && <Badge tone="info">Cuaca: {laporan.cuaca}</Badge>}
                  <Badge tone={laporan.status === 'DIKIRIM' ? 'success' : 'neutral'}>
                    {laporan.status === 'DIKIRIM' ? 'Dikirim' : 'Draft'}
                  </Badge>
                </div>
              </header>

              {laporan.keterangan && <p className="mb-3 text-xs text-muted">{laporan.keterangan}</p>}

              <div className="app-scroll-x">
                <table className="report-table min-w-max">
                  <thead>
                    <tr>
                      <th className="min-w-56">Uraian Pekerjaan</th>
                      <th>Satuan</th>
                      <th>Volume Rencana</th>
                      <th>Volume Realisasi</th>
                      <th>Realisasi (%)</th>
                      <th>Bobot Realisasi (%)</th>
                      <th className="min-w-40">Keterangan</th>
                    </tr>
                  </thead>
                  <tbody>
                    {laporan.detail.map((detail, index) => (
                      <tr key={index}>
                        <td>{detail.uraian_pekerjaan}</td>
                        <td className="text-center">{detail.satuan}</td>
                        <td className="num">{angka(detail.volume_rencana, 2)}</td>
                        <td className="num">{angka(detail.volume_realisasi, 2)}</td>
                        <td className="num">{angka(detail.persentase_realisasi, 2)}</td>
                        <td className="num">{angka(detail.bobot_realisasi, 4)}</td>
                        <td>{detail.keterangan ?? '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <section className="mt-3">
                <h4 className="mb-1 text-xs font-semibold text-ink">Kendala dan Tindak Lanjut</h4>
                {laporan.kendala.length === 0 ? (
                  <p className="text-[11px] text-muted">Tidak ada kendala yang dicatat.</p>
                ) : (
                  <ul className="flex flex-col gap-1.5 text-[11px] text-muted">
                    {laporan.kendala.map((item, index) => (
                      <li key={index}>
                        <span className="font-medium text-ink">{item.jenis_kendala.replace('_', ' ')}:</span> {item.deskripsi}
                        {item.alasan_keterlambatan ? ` - Alasan: ${item.alasan_keterlambatan}` : ''}
                        {item.tindak_lanjut ? ` - Tindak lanjut: ${item.tindak_lanjut}` : ''}
                      </li>
                    ))}
                  </ul>
                )}
              </section>

              {laporan.foto.length > 0 && (
                <section className="mt-3">
                  <h4 className="mb-1.5 text-xs font-semibold text-ink">Dokumentasi</h4>
                  <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                    {laporan.foto.map((foto, index) => (
                      <li key={index}>
                        <a href={foto.url} target="_blank" rel="noreferrer">
                          <img src={foto.url} alt={foto.caption ?? 'Foto progres'} className="h-24 w-full rounded-lg object-cover" loading="lazy" />
                        </a>
                        {foto.caption && <p className="mt-1 line-clamp-2 text-[10px] text-muted">{foto.caption}</p>}
                      </li>
                    ))}
                  </ul>
                </section>
              )}
            </article>
          ))}
        </div>
      )}
    </div>
  )
}
