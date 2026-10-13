import { StatusBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { Select } from '@/components/ui/Field'
import { KpiCard } from '@/components/ui/KpiCard'
import { Pagination } from '@/components/ui/Pagination'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import type { DashboardData, DashboardProjectRow } from '@/types'
import { cn } from '@/utils/cn'
import { deviasi, hariIni, persen, tanggal, tanggalSingkat } from '@/utils/format'
import { AlertTriangle, CalendarClock, CalendarDays, CheckCircle2, ChevronRight, ClipboardList, HardHat, type LucideIcon } from 'lucide-react'
import type { ComponentProps } from 'react'
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

type Nada = NonNullable<ComponentProps<typeof KpiCard>['tone']>

/** Pembagian proyek per status; jumlah keempatnya sama dengan total proyek. */
const FILTER_STATUS: { kunci: Exclude<Filter, 'tertinggal'>; label: string; ikon: LucideIcon; nada: Nada }[] = [
  { kunci: 'semua', label: 'Semua Proyek', ikon: ClipboardList, nada: 'navy' },
  { kunci: 'berjalan', label: 'Sedang Berjalan', ikon: HardHat, nada: 'warning' },
  { kunci: 'selesai', label: 'Selesai', ikon: CheckCircle2, nada: 'success' },
  { kunci: 'belum', label: 'Belum Dimulai', ikon: CalendarClock, nada: 'neutral' },
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

function NamaProyek({ proyek }: { proyek: DashboardProjectRow }) {
  return (
    <Link to={`/kurva-s?project_id=${proyek.id}`} className="font-medium text-ink hover:text-primary" title="Lihat Kurva S">
      {proyek.nama_proyek}
    </Link>
  )
}

function MonitoringProyek({
  proyek,
  filter,
  onFilterChange,
}: {
  proyek: DashboardProjectRow[]
  filter: Filter
  onFilterChange: (filter: Filter) => void
}) {
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
      action={
        <Select
          aria-label="Filter proyek"
          value={filter}
          onChange={(event) => onFilterChange(event.target.value as Filter)}
          className="py-1.5 text-xs"
        >
          {(Object.keys(JUDUL_FILTER) as Filter[]).map((kunci) => (
            <option key={kunci} value={kunci}>
              {JUDUL_FILTER[kunci]}
            </option>
          ))}
        </Select>
      }
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
      // Tinggi mengikuti jumlah progres (tidak ikut meregang setinggi tabel), tetapi dibatasi tinggi area.
      className="flex flex-col xl:max-h-full xl:min-h-0 xl:self-start"
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
      <div className="flex flex-col gap-3 lg:flex-row lg:gap-0 xl:shrink-0">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:flex-[4]">
          {FILTER_STATUS.map((item) => (
            <KpiCard
              key={item.kunci}
              compact
              accent
              label={item.label}
              value={proyek.filter(COCOK[item.kunci]).length}
              icon={item.ikon}
              tone={item.nada}
            />
          ))}
        </div>
        <div className="hidden w-px shrink-0 bg-line lg:mx-4 lg:block" aria-hidden />
        <KpiCard
          compact
          accent
          className="lg:flex-1"
          label="Tertinggal"
          value={jumlahTertinggal}
          icon={jumlahTertinggal > 0 ? AlertTriangle : CheckCircle2}
          tone={jumlahTertinggal > 0 ? 'danger' : 'neutral'}
          hint={jumlahTertinggal > 0 ? `dari ${jumlahBerjalan} berjalan` : 'Semua sesuai rencana'}
        />
      </div>

      {/* Layar lebar: tabel dan progres terbaru mengisi sisa tinggi layar dan menggulir isinya sendiri. */}
      <div className="grid gap-4 xl:min-h-0 xl:flex-1 xl:grid-cols-[minmax(0,1fr)_20rem] xl:grid-rows-[minmax(0,1fr)]">
        <MonitoringProyek proyek={proyek} filter={filter} onFilterChange={setFilter} />
        <ProgresTerbaru laporan={data.laporan_terbaru ?? []} />
      </div>
    </div>
  )
}
