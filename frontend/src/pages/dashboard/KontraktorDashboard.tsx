import { StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { Pagination } from '@/components/ui/Pagination'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import type { DashboardData, DashboardProjectRow } from '@/types'
import { cn } from '@/utils/cn'
import { deviasi, hariIni, persen, tanggal, tanggalSingkat } from '@/utils/format'
import { AlertTriangle, CalendarClock, CalendarDays, CheckCircle2, ChevronRight, ClipboardList, HardHat, type LucideIcon } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

const tautan = 'inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:text-primary-dark hover:underline'

/** Jumlah baris proyek per halaman tabel. */
const PER_HALAMAN = 10

type Laporan = NonNullable<DashboardData['laporan_terbaru']>[number]
type Filter = 'semua' | 'berjalan' | 'tertinggal' | 'selesai' | 'belum'

/** Kriteria tiap filter; "berjalan" mencakup proyek berstatus Terlambat karena masih dikerjakan. */
const COCOK: Record<Filter, (baris: DashboardProjectRow) => boolean> = {
  semua: () => true,
  berjalan: (baris) => baris.status === 'BERJALAN' || baris.status === 'TERLAMBAT',
  tertinggal: (baris) => Boolean(baris.tertinggal),
  selesai: (baris) => baris.status === 'SELESAI',
  belum: (baris) => baris.status === 'BELUM_DIMULAI',
}

type Nada = 'navy' | 'warning' | 'success' | 'netral' | 'danger'

/**
 * Warna kartu filter: garis atas, lencana ikon, dan tampilan saat dipilih.
 * Kelas ditulis lengkap (bukan dirangkai) agar terbaca oleh Tailwind.
 */
const WARNA: Record<Nada, { garis: string; ikon: string; aktif: string }> = {
  navy: { garis: 'border-t-navy', ikon: 'bg-navy/8 text-navy', aktif: 'bg-navy/4 ring-navy/60' },
  warning: { garis: 'border-t-warning', ikon: 'bg-warning-soft text-warning', aktif: 'bg-warning-soft/50 ring-warning/70' },
  success: { garis: 'border-t-success', ikon: 'bg-success-soft text-success', aktif: 'bg-success-soft/50 ring-success/60' },
  netral: { garis: 'border-t-muted/40', ikon: 'bg-surface text-muted', aktif: 'bg-surface ring-muted/50' },
  danger: { garis: 'border-t-danger', ikon: 'bg-danger-soft text-danger', aktif: 'bg-danger-soft/50 ring-danger/60' },
}

/** Pembagian proyek per status; jumlah keempatnya sama dengan total proyek. */
const FILTER_STATUS: { kunci: Exclude<Filter, 'tertinggal'>; label: string; ikon: LucideIcon; nada: Nada }[] = [
  { kunci: 'semua', label: 'Semua Proyek', ikon: ClipboardList, nada: 'navy' },
  { kunci: 'berjalan', label: 'Sedang Berjalan', ikon: HardHat, nada: 'warning' },
  { kunci: 'selesai', label: 'Selesai', ikon: CheckCircle2, nada: 'success' },
  { kunci: 'belum', label: 'Belum Dimulai', ikon: CalendarClock, nada: 'netral' },
]

const JUDUL_FILTER: Record<Filter, string> = {
  semua: 'Semua proyek',
  berjalan: 'Proyek sedang berjalan',
  tertinggal: 'Proyek tertinggal dari rencana',
  selesai: 'Proyek selesai',
  belum: 'Proyek belum dimulai',
}

/** Deviasi berwarna: merah bila tertinggal, hijau bila mendahului rencana. */
function TeksDeviasi({ nilai, className }: { nilai: number; className?: string }) {
  return (
    <span className={cn('font-semibold tabular-nums', nilai < -0.005 ? 'text-danger' : nilai > 0.005 ? 'text-success' : 'text-muted', className)}>
      {deviasi(nilai, 1)}
    </span>
  )
}

/** Kartu angka yang sekaligus menjadi filter tabel proyek. */
function KartuFilter({
  label,
  jumlah,
  ikon: Ikon,
  nada,
  keterangan,
  aktif,
  onClick,
  className,
}: {
  label: string
  jumlah: number
  ikon: LucideIcon
  nada: Nada
  keterangan?: string
  aktif: boolean
  onClick: () => void
  className?: string
}) {
  const warna = WARNA[nada]

  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={aktif}
      className={cn(
        'app-card flex items-start justify-between gap-3 border-t-[3px] px-4 py-3.5 text-left transition-colors',
        'focus-visible:ring-2 focus-visible:ring-navy/30 focus-visible:outline-none',
        warna.garis,
        aktif ? cn('ring-1', warna.aktif) : 'hover:bg-surface/50',
        className,
      )}
    >
      <span className="min-w-0">
        <span className="block truncate text-xs font-medium text-muted">{label}</span>
        <span className="mt-1.5 flex items-baseline gap-2">
          <span className={cn('text-2xl leading-none font-semibold tabular-nums', nada === 'danger' ? 'text-danger' : 'text-ink')}>{jumlah}</span>
          {keterangan && <span className="truncate text-xs text-muted">{keterangan}</span>}
        </span>
      </span>
      <span className={cn('grid size-9 shrink-0 place-items-center rounded-lg', warna.ikon)}>
        <Ikon className="size-[18px]" aria-hidden />
      </span>
    </button>
  )
}

function NamaProyek({ proyek }: { proyek: DashboardProjectRow }) {
  return (
    <Link to={`/kurva-s?project_id=${proyek.id}`} className="font-medium text-ink hover:text-primary" title="Lihat Kurva S">
      {proyek.nama_proyek}
    </Link>
  )
}

function MonitoringProyek({ proyek, filter }: { proyek: DashboardProjectRow[]; filter: Filter }) {
  const [halaman, setHalaman] = useState(1)
  const [filterSebelumnya, setFilterSebelumnya] = useState(filter)

  // Kembali ke halaman pertama setiap kali filter berganti.
  if (filterSebelumnya !== filter) {
    setFilterSebelumnya(filter)
    setHalaman(1)
  }

  // Urutan dari backend: deviasi paling tertinggal berada di atas.
  const terfilter = proyek.filter(COCOK[filter])
  const halamanTerakhir = Math.max(1, Math.ceil(terfilter.length / PER_HALAMAN))
  const aktif = Math.min(halaman, halamanTerakhir)
  const awal = (aktif - 1) * PER_HALAMAN
  const tampil = terfilter.slice(awal, awal + PER_HALAMAN)

  return (
    <Card
      title="Monitoring Proyek"
      description={`${JUDUL_FILTER[filter]} · paling tertinggal di atas`}
      className="flex flex-col xl:min-h-0"
      bodyClassName="flex flex-col xl:min-h-0 xl:flex-1"
      flush
    >
      {proyek.length === 0 ? (
        <EmptyState judul="Belum ada proyek" pesan="Proyek yang dibuat Admin akan tampil di sini." />
      ) : terfilter.length === 0 ? (
        <EmptyState judul="Tidak ada proyek" pesan="Tidak ada proyek pada kategori ini." />
      ) : (
        <>
          <div className="xl:min-h-0 xl:flex-1 xl:overflow-y-auto">
            {/* Layar lebar: tabel dengan lebar kolom tetap. */}
            <TableWrap flush className="hidden lg:block">
              <Table className="w-full table-fixed">
                <thead className="xl:sticky xl:top-0 xl:z-10">
                  <tr>
                    <Th>Proyek</Th>
                    <Th align="center" className="w-28">
                      Status
                    </Th>
                    <Th className="w-44">Realisasi / Rencana</Th>
                    <Th align="right" className="w-24">
                      Deviasi
                    </Th>
                  </tr>
                </thead>
                <tbody>
                  {tampil.map((baris) => (
                    <tr key={baris.id} className="hover:bg-surface/60">
                      <Td className="break-words">
                        <NamaProyek proyek={baris} />
                        <p className="mt-0.5 truncate text-[11px] text-muted" title={baris.lokasi}>
                          {baris.lokasi}
                        </p>
                      </Td>
                      <Td align="center">
                        <StatusBadge status={baris.status} bertumpuk />
                      </Td>
                      <Td>
                        <ProgressBar nilai={baris.progres_aktual} pembanding={baris.progres_rencana} />
                        <p className="mt-0.5 text-[11px] text-muted">Rencana {persen(baris.progres_rencana, 1)}</p>
                      </Td>
                      <Td align="right">
                        <TeksDeviasi nilai={baris.deviasi} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </TableWrap>

            {/* Layar kecil: satu baris ringkas per proyek. */}
            <ul className="divide-y divide-line lg:hidden">
              {tampil.map((baris) => (
                <li key={baris.id} className="flex flex-col gap-2 px-4 py-3 sm:px-5">
                  <div className="flex items-start justify-between gap-3 text-sm">
                    <div className="min-w-0">
                      <NamaProyek proyek={baris} />
                      <p className="mt-0.5 truncate text-[11px] text-muted">{baris.lokasi}</p>
                    </div>
                    <StatusBadge status={baris.status} bertumpuk />
                  </div>
                  <ProgressBar nilai={baris.progres_aktual} pembanding={baris.progres_rencana} />
                  <p className="text-[11px] text-muted">
                    Rencana {persen(baris.progres_rencana, 1)} · Deviasi <TeksDeviasi nilai={baris.deviasi} />
                  </p>
                </li>
              ))}
            </ul>
          </div>

          <div className="border-t border-line px-4 sm:px-5 xl:shrink-0">
            <Pagination
              page={aktif}
              lastPage={halamanTerakhir}
              total={terfilter.length}
              from={awal + 1}
              to={awal + tampil.length}
              onChange={setHalaman}
            />
          </div>
        </>
      )}
    </Card>
  )
}

function ProgresTerbaru({ laporan }: { laporan: Laporan[] }) {
  return (
    <Card
      title="Progres Terbaru"
      action={
        <Link to="/progres" className={tautan}>
          Lihat semua
          <ChevronRight className="size-3.5" aria-hidden />
        </Link>
      }
      className="flex flex-col xl:min-h-0"
      bodyClassName="xl:min-h-0 xl:flex-1 xl:overflow-y-auto"
      flush
    >
      {laporan.length === 0 ? (
        <EmptyState judul="Belum ada progres" pesan="Progres yang dikirim QS akan tampil di sini." />
      ) : (
        <ul className="divide-y divide-line">
          {laporan.slice(0, 5).map((item) => (
            <li key={item.id}>
              <Link to={`/progres/${item.id}`} className="group flex items-center gap-3 px-4 py-2.5 hover:bg-surface/60 sm:px-5">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[13px] text-ink group-hover:text-primary" title={item.nama_proyek ?? undefined}>
                    {item.nama_proyek}
                  </p>
                  <p className="truncate text-[11px] text-muted">
                    {tanggalSingkat(item.tanggal_laporan)} · {item.periode ?? 'Di luar periode'}
                  </p>
                </div>
                {item.bobot_realisasi !== undefined && (
                  <span className="shrink-0 text-xs font-semibold text-ink tabular-nums">{persen(item.bobot_realisasi)}</span>
                )}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}

export function KontraktorDashboard({ data }: { data: DashboardData }) {
  const proyek = data.proyek ?? []
  // Default menampilkan proyek yang sedang berjalan agar proyek selesai tidak menumpuk di atas.
  const [filter, setFilter] = useState<Filter>(() => (proyek.some(COCOK.berjalan) ? 'berjalan' : 'semua'))
  const jumlahBerjalan = proyek.filter(COCOK.berjalan).length
  const jumlahTertinggal = proyek.filter(COCOK.tertinggal).length

  return (
    // Layar lebar: tinggi halaman dikunci setinggi layar (dikurangi navbar 4rem + padding 3rem) agar tidak perlu scroll.
    <div className="flex flex-col gap-4 xl:h-[calc(100dvh-7rem)]">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 xl:shrink-0">
        <h2 className="text-lg font-semibold text-ink">Beranda</h2>
        <p className="flex items-center gap-1.5 text-xs text-muted">
          <CalendarDays className="size-3.5" aria-hidden />
          Data per {tanggal(hariIni(), 'EEEE, dd MMMM yyyy')}
        </p>
      </div>

      {/* Empat kartu status membagi seluruh proyek; kartu Tertinggal adalah peringatan dari proyek berjalan, sengaja dipisah. */}
      <div className="flex flex-col gap-3 lg:flex-row lg:gap-0 xl:shrink-0" role="group" aria-label="Filter proyek">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:flex-[4]">
          {FILTER_STATUS.map((item) => (
            <KartuFilter
              key={item.kunci}
              label={item.label}
              jumlah={proyek.filter(COCOK[item.kunci]).length}
              ikon={item.ikon}
              nada={item.nada}
              aktif={filter === item.kunci}
              onClick={() => setFilter(item.kunci)}
            />
          ))}
        </div>
        <div className="hidden w-px shrink-0 bg-line lg:mx-4 lg:block" aria-hidden />
        <KartuFilter
          className="lg:flex-1"
          label="Tertinggal"
          jumlah={jumlahTertinggal}
          ikon={jumlahTertinggal > 0 ? AlertTriangle : CheckCircle2}
          nada={jumlahTertinggal > 0 ? 'danger' : 'netral'}
          keterangan={jumlahTertinggal > 0 ? `dari ${jumlahBerjalan} berjalan` : 'Semua sesuai rencana'}
          aktif={filter === 'tertinggal'}
          onClick={() => setFilter('tertinggal')}
        />
      </div>

      {/* Layar lebar: tabel dan progres terbaru mengisi sisa tinggi layar dan menggulir isinya sendiri. */}
      <div className="grid gap-4 xl:min-h-0 xl:flex-1 xl:grid-cols-[minmax(0,1fr)_20rem] xl:grid-rows-[minmax(0,1fr)]">
        <MonitoringProyek proyek={proyek} filter={filter} />
        <ProgresTerbaru laporan={data.laporan_terbaru ?? []} />
      </div>
    </div>
  )
}
