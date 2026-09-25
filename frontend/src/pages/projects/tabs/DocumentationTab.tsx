import { Badge, ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Modal } from '@/components/ui/Modal'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { useProgressList } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import type { ProgressReport, Project } from '@/types'
import { cn } from '@/utils/cn'
import { angka, hariIni, tanggal } from '@/utils/format'
import { addMonths, eachDayOfInterval, endOfMonth, format, getDay, isAfter, parseISO, startOfMonth } from 'date-fns'
import { id as localeId } from 'date-fns/locale'
import { Camera, ChevronRight } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'

const NAMA_HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const LABEL_KENDALA: Record<string, string> = {
  CUACA: 'Cuaca',
  MATERIAL: 'Material',
  TENAGA_KERJA: 'Tenaga Kerja',
  PERALATAN: 'Peralatan',
  TEKNIS: 'Teknis',
  LAINNYA: 'Lainnya',
}

function daftarBulan(dari: string, sampai: string): Date[] {
  const hasil: Date[] = []
  const akhir = startOfMonth(parseISO(sampai))

  for (let bulan = startOfMonth(parseISO(dari)); !isAfter(bulan, akhir); bulan = addMonths(bulan, 1)) {
    hasil.push(bulan)
  }

  return hasil
}

/** Kalender foto: setiap tanggal yang memiliki progres menampilkan foto bukti sebagai sampul. */
export function DocumentationTab({ project }: { project: Project }) {
  const { data, isLoading, error, refetch } = useProgressList({ project_id: project.id, per_page: 200 })
  const [tanggalDipilih, setTanggalDipilih] = useState<string | null>(null)

  const perTanggal = useMemo(() => {
    const peta = new Map<string, ProgressReport[]>()

    for (const laporan of data?.data ?? []) {
      const kunci = laporan.tanggal_laporan.slice(0, 10)
      peta.set(kunci, [...(peta.get(kunci) ?? []), laporan])
    }

    return peta
  }, [data])

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />

  const mulai = project.tanggal_mulai.slice(0, 10)
  const selesai = project.tanggal_selesai.slice(0, 10)
  const tanggalProgres = [...perTanggal.keys()].sort()
  const awal = [mulai, ...tanggalProgres].sort()[0]
  const akhir = [selesai, ...tanggalProgres].sort().at(-1) ?? selesai
  const totalFoto = (data?.data ?? []).reduce((jumlah, laporan) => jumlah + (laporan.foto?.length ?? 0), 0)
  const hari = hariIni()

  return (
    <>
      <Card
        title="Dokumentasi Pekerjaan"
        description={`${totalFoto} foto dari ${perTanggal.size} hari progres · klik tanggal untuk melihat rincian`}
      >
        {perTanggal.size === 0 ? (
          <EmptyState judul="Belum ada dokumentasi" pesan="Foto yang diunggah QS saat tambah progres akan tampil di kalender ini." />
        ) : (
          <div className="flex flex-col gap-8">
            {daftarBulan(awal, akhir).map((bulan) => (
              <KalenderBulan
                key={bulan.toISOString()}
                bulan={bulan}
                perTanggal={perTanggal}
                mulai={mulai}
                selesai={selesai}
                hari={hari}
                onPilih={setTanggalDipilih}
              />
            ))}

            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line pt-4 text-[11px] text-muted">
              <span className="flex items-center gap-1.5">
                <span className="size-3 rounded bg-ink/70" aria-hidden />
                Ada foto bukti
              </span>
              <span className="flex items-center gap-1.5">
                <span className="size-1.5 rounded-full bg-primary" aria-hidden />
                Ada progres tanpa foto
              </span>
              <span className="flex items-center gap-1.5">
                <span className="size-3 rounded-full bg-navy" aria-hidden />
                Hari ini
              </span>
            </div>
          </div>
        )}
      </Card>

      {tanggalDipilih && (
        <DetailHari kunci={tanggalDipilih} laporan={perTanggal.get(tanggalDipilih) ?? []} onClose={() => setTanggalDipilih(null)} />
      )}
    </>
  )
}

interface KalenderBulanProps {
  bulan: Date
  perTanggal: Map<string, ProgressReport[]>
  mulai: string
  selesai: string
  hari: string
  onPilih: (kunci: string) => void
}

function KalenderBulan({ bulan, perTanggal, mulai, selesai, hari, onPilih }: KalenderBulanProps) {
  const hariHari = eachDayOfInterval({ start: bulan, end: endOfMonth(bulan) })

  return (
    <section>
      <h3 className="mb-3 text-sm font-semibold text-ink capitalize">{format(bulan, 'MMMM yyyy', { locale: localeId })}</h3>
      <div className="grid grid-cols-7 gap-1.5 sm:gap-2">
        {NAMA_HARI.map((nama) => (
          <div key={nama} className="pb-1 text-center text-[10px] font-semibold tracking-wide text-muted uppercase">
            {nama}
          </div>
        ))}

        {Array.from({ length: getDay(bulan) }, (_, index) => (
          <div key={`kosong-${index}`} aria-hidden />
        ))}

        {hariHari.map((tanggalHari) => {
          const kunci = format(tanggalHari, 'yyyy-MM-dd')
          const laporan = perTanggal.get(kunci)
          const nomor = format(tanggalHari, 'd')
          const hariIniKah = kunci === hari

          if (!laporan) {
            const dalamProyek = kunci >= mulai && kunci <= selesai

            return (
              <div key={kunci} className="grid aspect-[3/4] place-items-center">
                <span
                  className={cn(
                    'grid size-7 place-items-center rounded-full text-sm tabular-nums sm:size-8',
                    hariIniKah ? 'bg-navy font-semibold text-white' : dalamProyek ? 'text-ink' : 'text-muted/40',
                  )}
                >
                  {nomor}
                </span>
              </div>
            )
          }

          const foto = laporan.flatMap((item) => item.foto ?? [])
          const sampul = foto[0]

          return (
            <button
              key={kunci}
              type="button"
              onClick={() => onPilih(kunci)}
              aria-label={`Lihat progres ${tanggal(kunci)}`}
              className={cn(
                'group relative grid aspect-[3/4] place-items-center overflow-hidden rounded-lg transition-shadow hover:shadow-md',
                sampul ? 'bg-ink' : 'border border-line bg-surface hover:border-primary/40',
                hariIniKah && 'ring-2 ring-navy ring-offset-2',
              )}
            >
              {sampul && (
                <>
                  <img
                    src={sampul.url}
                    alt=""
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover transition-transform duration-200 group-hover:scale-105"
                  />
                  <span className="absolute inset-0 bg-ink/35 transition-colors group-hover:bg-ink/20" aria-hidden />
                </>
              )}
              <span className={cn('relative text-base font-semibold tabular-nums sm:text-lg', sampul ? 'text-white drop-shadow' : 'text-ink')}>{nomor}</span>
              {foto.length > 1 && (
                <span className="absolute top-1 right-1 rounded-full bg-black/55 px-1.5 text-[10px] leading-4 font-medium text-white">{foto.length}</span>
              )}
              {!sampul && <span className="absolute bottom-2 left-1/2 size-1.5 -translate-x-1/2 rounded-full bg-primary" aria-hidden />}
            </button>
          )
        })}
      </div>
    </section>
  )
}

function DetailHari({ kunci, laporan, onClose }: { kunci: string; laporan: ProgressReport[]; onClose: () => void }) {
  return (
    <Modal
      open
      onClose={onClose}
      title={tanggal(kunci, 'EEEE, dd MMMM yyyy')}
      description={`${laporan.length} progres pada tanggal ini`}
      size="lg"
      footer={
        <Button variant="outline" onClick={onClose}>
          Tutup
        </Button>
      }
    >
      <div className="flex flex-col gap-4">
        {laporan.map((item) => (
          <article key={item.id} className="flex flex-col gap-3 rounded-xl border border-line p-3 sm:p-4">
            <header className="flex flex-wrap items-center gap-2">
              <p className="text-sm font-semibold text-ink">{item.pelapor ?? '-'}</p>
              <ReportStatusBadge status={item.status} />
              {item.periode && <span className="text-[11px] text-muted">{item.periode}</span>}
              <Link to={`/progres/${item.id}`} className="ml-auto inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:underline">
                Lihat detail progres
                <ChevronRight className="size-3.5" aria-hidden />
              </Link>
            </header>

            {item.foto && item.foto.length > 0 ? (
              <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {item.foto.map((foto) => (
                  <li key={foto.id}>
                    <a href={foto.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg">
                      <img src={foto.url} alt={foto.caption || 'Foto progres'} loading="lazy" className="aspect-[4/3] w-full object-cover transition hover:opacity-90" />
                    </a>
                    {foto.caption && <p className="mt-1 line-clamp-1 text-[11px] text-muted">{foto.caption}</p>}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="flex items-center gap-1.5 rounded-lg bg-surface px-3 py-2 text-xs text-muted">
                <Camera className="size-3.5" aria-hidden />
                Tidak ada foto pada progres ini.
              </p>
            )}

            {item.keterangan && <p className="text-sm text-ink">{item.keterangan}</p>}

            {item.detail && item.detail.length > 0 && (
              <div>
                <p className="mb-1.5 text-[11px] font-semibold tracking-wide text-muted uppercase">Pekerjaan</p>
                <ul className="divide-y divide-line rounded-lg border border-line">
                  {item.detail.map((detail) => (
                    <li key={detail.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                      <span className="min-w-0 text-ink">{detail.uraian_pekerjaan}</span>
                      <span className="shrink-0 text-xs text-muted tabular-nums">
                        {angka(detail.volume_realisasi)} {detail.satuan}
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {item.kendala && item.kendala.length > 0 && (
              <div>
                <p className="mb-1.5 text-[11px] font-semibold tracking-wide text-muted uppercase">Kendala</p>
                <ul className="flex flex-col gap-2">
                  {item.kendala.map((kendala) => (
                    <li key={kendala.id} className="rounded-lg border border-warning/30 bg-warning-soft/60 px-3 py-2 text-xs text-ink">
                      <Badge tone="warning">{LABEL_KENDALA[kendala.jenis_kendala] ?? kendala.jenis_kendala}</Badge>
                      <p className="mt-1.5">{kendala.deskripsi}</p>
                      {kendala.tindak_lanjut && <p className="mt-0.5 text-muted">Tindak lanjut: {kendala.tindak_lanjut}</p>}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </article>
        ))}
      </div>
    </Modal>
  )
}
