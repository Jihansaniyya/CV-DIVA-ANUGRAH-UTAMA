import { Badge, ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { useProgressList, useWorkPlans } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import type { Period } from '@/types'
import { angka, rentangTanggal, tanggalSingkat } from '@/utils/format'
import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'

interface BarisPekerjaan {
  work_item_id: number
  uraian: string
  satuan: string | null
  target: number
  realisasi: number
  terjadwal: boolean
}

/** Rincian satu periode: pekerjaan yang dijadwalkan beserta realisasi dan progres yang dikirim pada minggu itu. */
export function PeriodDetailModal({ projectId, period, onClose }: { projectId: number; period: Period; onClose: () => void }) {
  const rencana = useWorkPlans(projectId)
  const progres = useProgressList({ project_id: projectId, period_id: period.id, per_page: 100 })

  const memuat = rencana.isLoading || progres.isLoading
  const galat = rencana.error ?? progres.error
  const laporan = progres.data?.data ?? []

  const realisasi = new Map<number, { uraian: string; satuan: string | null; volume: number }>()

  for (const item of laporan.filter((l) => l.status === 'DIKIRIM')) {
    for (const detail of item.detail ?? []) {
      const lama = realisasi.get(detail.work_item_id)
      realisasi.set(detail.work_item_id, {
        uraian: detail.uraian_pekerjaan ?? '-',
        satuan: detail.satuan,
        volume: (lama?.volume ?? 0) + detail.volume_realisasi,
      })
    }
  }

  const terjadwal: BarisPekerjaan[] = (rencana.data?.baris ?? []).flatMap((baris) => {
    const sel = baris.periode.find((cell) => cell.period_id === period.id)

    if (!sel || sel.target_volume <= 0) return []

    return [
      {
        work_item_id: baris.work_item_id,
        uraian: baris.uraian_pekerjaan,
        satuan: baris.satuan,
        target: sel.target_volume,
        realisasi: realisasi.get(baris.work_item_id)?.volume ?? 0,
        terjadwal: true,
      },
    ]
  })

  const idTerjadwal = new Set(terjadwal.map((baris) => baris.work_item_id))
  const diLuarJadwal: BarisPekerjaan[] = [...realisasi.entries()]
    .filter(([id]) => !idTerjadwal.has(id))
    .map(([id, nilai]) => ({ work_item_id: id, uraian: nilai.uraian, satuan: nilai.satuan, target: 0, realisasi: nilai.volume, terjadwal: false }))

  const pekerjaan = [...terjadwal, ...diLuarJadwal]

  return (
    <Modal
      open
      onClose={onClose}
      title={period.nama_periode}
      description={rentangTanggal(period.tanggal_mulai, period.tanggal_selesai)}
      size="lg"
      footer={
        <Button variant="outline" onClick={onClose}>
          Tutup
        </Button>
      }
    >
      {memuat ? (
        <LoadingState />
      ) : galat ? (
        <ErrorState pesan={pesanError(galat)} />
      ) : (
        <div className="flex flex-col gap-5">
          <dl className="grid grid-cols-3 divide-x divide-line rounded-xl border border-line">
            {[
              ['Pekerjaan Dijadwalkan', terjadwal.length],
              ['Pekerjaan Dikerjakan', realisasi.size],
              ['Progres Masuk', laporan.length],
            ].map(([label, nilai]) => (
              <div key={label} className="px-3 py-3 text-center">
                <dd className="text-lg font-semibold text-ink">{nilai}</dd>
                <dt className="text-[11px] text-muted">{label}</dt>
              </div>
            ))}
          </dl>

          <section>
            <h3 className="mb-2 text-sm font-semibold text-ink">Pekerjaan Minggu Ini</h3>
            {pekerjaan.length === 0 ? (
              <EmptyState judul="Tidak ada pekerjaan" pesan="Belum ada pekerjaan yang dijadwalkan atau dikerjakan pada minggu ini." />
            ) : (
              <ul className="flex flex-col divide-y divide-line rounded-xl border border-line">
                {pekerjaan.map((baris) => {
                  const tercapai = baris.terjadwal && baris.realisasi >= baris.target

                  return (
                    <li key={baris.work_item_id} className="flex flex-col gap-2 px-3 py-2.5 sm:flex-row sm:items-center sm:gap-4">
                      <p className="min-w-0 flex-1 text-sm text-ink">{baris.uraian}</p>
                      <div className="flex items-center gap-4 text-xs">
                        <div className="w-24 text-right">
                          <p className="text-[10px] text-muted uppercase">Target</p>
                          <p className="font-medium text-ink tabular-nums">{baris.terjadwal ? `${angka(baris.target)} ${baris.satuan ?? ''}` : '-'}</p>
                        </div>
                        <div className="w-24 text-right">
                          <p className="text-[10px] text-muted uppercase">Realisasi</p>
                          <p className="font-medium text-ink tabular-nums">
                            {angka(baris.realisasi)} {baris.satuan ?? ''}
                          </p>
                        </div>
                        <div className="w-24 text-right">
                          {!baris.terjadwal ? (
                            <Badge tone="info">Di luar jadwal</Badge>
                          ) : tercapai ? (
                            <Badge tone="success">Tercapai</Badge>
                          ) : (
                            <Badge tone="warning">Belum tercapai</Badge>
                          )}
                        </div>
                      </div>
                    </li>
                  )
                })}
              </ul>
            )}
          </section>

          <section>
            <h3 className="mb-2 text-sm font-semibold text-ink">Progres yang Masuk</h3>
            {laporan.length === 0 ? (
              <p className="rounded-xl border border-dashed border-line px-3 py-4 text-center text-xs text-muted">Belum ada progres pada minggu ini.</p>
            ) : (
              <ul className="flex flex-col divide-y divide-line rounded-xl border border-line">
                {laporan.map((item) => (
                  <li key={item.id}>
                    <Link to={`/progres/${item.id}`} className="group flex items-center gap-3 px-3 py-2.5 hover:bg-surface/60">
                      <div className="min-w-0 flex-1">
                        <p className="text-sm text-ink group-hover:text-primary">{tanggalSingkat(item.tanggal_laporan)}</p>
                        <p className="text-[11px] text-muted">
                          {item.pelapor ?? '-'} · {item.detail?.length ?? 0} pekerjaan
                        </p>
                      </div>
                      <ReportStatusBadge status={item.status} />
                      <ChevronRight className="size-4 text-muted" aria-hidden />
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      )}
    </Modal>
  )
}
