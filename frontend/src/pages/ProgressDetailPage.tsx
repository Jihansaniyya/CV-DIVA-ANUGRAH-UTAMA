import { Badge, ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { ConfirmDialog, Modal } from '@/components/ui/Modal'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { qk, useProgress } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { progressService } from '@/services/progressService'
import { cn } from '@/utils/cn'
import { angka, persen, tanggal, waktu } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ArrowLeft,
  CalendarDays,
  CalendarRange,
  ChevronLeft,
  ChevronRight,
  Clock,
  CloudSun,
  Expand,
  ImageOff,
  MapPin,
  Send,
  Trash2,
  UserRound,
  type LucideIcon,
} from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

export function ProgressDetailPage() {
  const { id } = useParams<{ id: string }>()
  const laporanId = Number(id)
  const { user, punyaPeran } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [konfirmasiHapus, setKonfirmasiHapus] = useState(false)
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)

  const { data: laporan, isLoading, error, refetch } = useProgress(laporanId)

  const kirim = useMutation({
    mutationFn: () => progressService.submit(laporanId),
    onSuccess: async () => {
      toast.sukses('Progres berhasil dikirim.')
      await queryClient.invalidateQueries({ queryKey: ['progress'] })
      await queryClient.invalidateQueries({ queryKey: qk.dashboard })

      if (laporan) {
        await queryClient.invalidateQueries({ queryKey: qk.curve(laporan.project_id) })
      }
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  const hapus = useMutation({
    mutationFn: () => progressService.remove(laporanId),
    onSuccess: async () => {
      toast.sukses('Progres berhasil dihapus.')
      await queryClient.invalidateQueries({ queryKey: ['progress'] })
      navigate('/progres')
    },
    onError: (err) => {
      toast.gagal(pesanError(err))
      setKonfirmasiHapus(false)
    },
  })

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!laporan) return <EmptyState judul="Progres tidak ditemukan" />

  const bolehUbah = punyaPeran('ADMIN') || (laporan.user_id === user?.id && laporan.status === 'DRAFT')
  const tampilMaterial = !punyaPeran('QS')
  const kontraktor = punyaPeran('KONTRAKTOR')

  const foto = laporan.foto ?? []
  const kendala = laporan.kendala ?? []
  const keterlambatan = kendala.filter((item) => item.alasan_keterlambatan)
  const fotoTerpilih = fotoAktif === null ? null : foto[fotoAktif]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <Link to="/progres" className="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink">
            <ArrowLeft className="size-4" aria-hidden />
            Kembali ke daftar progres
          </Link>
          <h2 className="mt-2 flex flex-wrap items-center gap-2 text-lg leading-tight font-semibold text-ink">
            Laporan Progres {tanggal(laporan.tanggal_laporan)}
            <ReportStatusBadge status={laporan.status} />
          </h2>
          <p className="mt-1 text-sm text-muted">
            {kontraktor ? (
              laporan.nama_proyek
            ) : (
              <Link to={`/proyek/${laporan.project_id}`} className="hover:text-primary">
                {laporan.nama_proyek}
              </Link>
            )}
            {laporan.periode ? ` · ${laporan.periode}` : ''}
          </p>
        </div>

        {bolehUbah && (
          <div className="flex shrink-0 gap-2">
            {laporan.status === 'DRAFT' && (
              <Button icon={<Send className="size-4" />} loading={kirim.isPending} onClick={() => kirim.mutate()}>
                Kirim Progres
              </Button>
            )}
            <Button variant="outline" className="text-danger" icon={<Trash2 className="size-4" />} onClick={() => setKonfirmasiHapus(true)}>
              Hapus
            </Button>
          </div>
        )}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card title="Informasi Laporan" className="lg:col-span-2">
          <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <Info label="Proyek" className="sm:col-span-2">
              <span className="font-medium">{laporan.nama_proyek ?? '-'}</span>
            </Info>
            <Info label="Pelapor (QS)" icon={UserRound}>
              {laporan.pelapor ?? '-'}
            </Info>
            <Info label="Tanggal Laporan" icon={CalendarDays}>
              {tanggal(laporan.tanggal_laporan)}
            </Info>
            <Info label="Periode" icon={CalendarRange}>
              {laporan.periode ?? 'Di luar periode'}
            </Info>
            <Info label="Waktu Pelaporan" icon={Clock}>
              {laporan.dikirim_pada ? waktu(laporan.dikirim_pada) : 'Belum dikirim'}
            </Info>
            <Info label="Lokasi Pekerjaan" icon={MapPin}>
              {laporan.lokasi ?? '-'}
            </Info>
            <Info label="Cuaca" icon={CloudSun}>
              {laporan.cuaca ?? '-'}
            </Info>
            <Info label="Keterangan" className="sm:col-span-2">
              {laporan.keterangan ?? '-'}
            </Info>
          </dl>
        </Card>

        <Card title="Kontribusi Progres">
          <p className="text-3xl leading-none font-semibold text-ink">{persen(laporan.total_bobot_realisasi)}</p>
          <p className="mt-2 text-xs text-muted">Bobot realisasi laporan ini terhadap total progres proyek.</p>
          <dl className="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-4 text-center">
            <div>
              <dd className="text-lg font-semibold text-ink">{laporan.detail?.length ?? 0}</dd>
              <dt className="text-[11px] text-muted">Pekerjaan</dt>
            </div>
            <div>
              <dd className="text-lg font-semibold text-ink">{foto.length}</dd>
              <dt className="text-[11px] text-muted">Foto</dt>
            </div>
            <div>
              <dd className={cn('text-lg font-semibold', kendala.length > 0 ? 'text-[#b45309]' : 'text-ink')}>{kendala.length}</dd>
              <dt className="text-[11px] text-muted">Kendala</dt>
            </div>
          </dl>
        </Card>
      </div>

      <Card title="Realisasi Pekerjaan" description="Volume yang dilaporkan dan kontribusinya terhadap bobot proyek." flush>
        {!laporan.detail || laporan.detail.length === 0 ? (
          <EmptyState judul="Tidak ada pekerjaan" pesan="Laporan ini tidak memuat realisasi pekerjaan." />
        ) : (
          <TableWrap flush>
            <Table>
              <thead>
                <tr>
                  <Th className="min-w-56">Uraian Pekerjaan</Th>
                  <Th>Satuan</Th>
                  <Th align="right">Volume Rencana</Th>
                  <Th align="right">Volume Realisasi</Th>
                  <Th align="right">Realisasi (%)</Th>
                  <Th align="right">Bobot Realisasi (%)</Th>
                  <Th className="min-w-40">Keterangan</Th>
                </tr>
              </thead>
              <tbody>
                {laporan.detail.map((detail) => (
                  <tr key={detail.id} className="hover:bg-surface/60">
                    <Td className="font-medium">{detail.uraian_pekerjaan}</Td>
                    <Td className="text-muted">{detail.satuan}</Td>
                    <Td align="right" className="text-muted">
                      {angka(detail.volume_rencana, 2)}
                    </Td>
                    <Td align="right" className="font-medium">
                      {angka(detail.volume_realisasi, 2)}
                    </Td>
                    <Td align="right">{angka(detail.persentase_realisasi, 2)}</Td>
                    <Td align="right" className="font-semibold">
                      {angka(detail.bobot_realisasi, 4)}
                    </Td>
                    <Td className="text-muted">{detail.keterangan ?? '-'}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>

      <Card
        title="Foto Bukti Pekerjaan"
        description={foto.length > 0 ? 'Klik foto untuk memperbesar.' : undefined}
        action={foto.length > 0 ? <span className="text-xs text-muted">{foto.length} foto</span> : undefined}
      >
        {foto.length === 0 ? (
          <EmptyState judul="Tidak ada foto" pesan="QS tidak mengunggah foto pada laporan ini." icon={<ImageOff className="size-6" aria-hidden />} />
        ) : (
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {foto.map((item, index) => (
              <li key={item.id}>
                <button
                  type="button"
                  onClick={() => setFotoAktif(index)}
                  className="group block w-full overflow-hidden rounded-lg border border-line bg-white text-left transition-shadow hover:shadow-md"
                >
                  <span className="relative block aspect-[4/3] overflow-hidden bg-surface">
                    <img
                      src={item.url}
                      alt={item.caption || 'Foto bukti pekerjaan'}
                      className="size-full object-cover transition-transform duration-200 group-hover:scale-[1.03]"
                      loading="lazy"
                    />
                    <span className="absolute right-2 bottom-2 grid size-7 place-items-center rounded-full bg-ink/60 text-white opacity-0 transition-opacity group-hover:opacity-100">
                      <Expand className="size-3.5" aria-hidden />
                    </span>
                  </span>
                  <span className="block px-2.5 py-2">
                    <span className="block truncate text-xs text-ink">{item.caption || 'Tanpa keterangan'}</span>
                    {item.diunggah_pada && <span className="block text-[11px] text-muted">{waktu(item.diunggah_pada)}</span>}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title="Kendala" action={kendala.length > 0 ? <Badge tone="warning">{kendala.length} kendala</Badge> : undefined}>
          {kendala.length === 0 ? (
            <p className="text-sm text-muted">Tidak ada kendala yang dicatat pada laporan ini.</p>
          ) : (
            <ul className="flex flex-col gap-3">
              {kendala.map((item) => (
                <li key={item.id} className="rounded-lg border border-line p-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge tone="warning">{item.jenis_kendala.replace('_', ' ')}</Badge>
                    {item.pekerjaan && <span className="text-xs text-muted">{item.pekerjaan}</span>}
                  </div>
                  <p className="mt-2 text-sm text-ink">{item.deskripsi}</p>
                  {item.tindak_lanjut && (
                    <p className="mt-1.5 text-xs text-muted">
                      <span className="font-medium text-ink">Tindak lanjut:</span> {item.tindak_lanjut}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card title="Alasan Keterlambatan">
          {keterlambatan.length === 0 ? (
            <p className="text-sm text-muted">Tidak ada alasan keterlambatan yang dilaporkan.</p>
          ) : (
            <ul className="flex flex-col gap-3">
              {keterlambatan.map((item) => (
                <li key={item.id} className="rounded-lg border-l-[3px] border-danger bg-danger-soft/50 px-3 py-2.5">
                  {item.pekerjaan && <p className="text-xs font-medium text-muted">{item.pekerjaan}</p>}
                  <p className="text-sm text-ink">{item.alasan_keterlambatan}</p>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      {tampilMaterial && (
        <Card title="Material" flush>
          {!laporan.material || laporan.material.length === 0 ? (
            <p className="px-4 py-4 text-sm text-muted sm:px-5">Tidak ada material yang dicatat.</p>
          ) : (
            <TableWrap flush>
              <Table>
                <thead>
                  <tr>
                    <Th>Nama Material</Th>
                    <Th align="right">Jumlah</Th>
                    <Th>Satuan</Th>
                    <Th>Keterangan</Th>
                  </tr>
                </thead>
                <tbody>
                  {laporan.material.map((item) => (
                    <tr key={item.id}>
                      <Td>{item.nama_material}</Td>
                      <Td align="right">{angka(item.jumlah, 2)}</Td>
                      <Td className="text-muted">{item.satuan ?? '-'}</Td>
                      <Td className="text-muted">{item.keterangan ?? '-'}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </TableWrap>
          )}
        </Card>
      )}

      <Modal
        open={fotoTerpilih !== null && fotoTerpilih !== undefined}
        onClose={() => setFotoAktif(null)}
        title={fotoTerpilih?.caption || 'Foto bukti pekerjaan'}
        description={fotoTerpilih?.diunggah_pada ? `Diunggah ${waktu(fotoTerpilih.diunggah_pada)}` : undefined}
        size="xl"
        footer={
          foto.length > 1 && fotoAktif !== null ? (
            <div className="flex w-full items-center justify-between gap-2">
              <Button variant="outline" size="sm" icon={<ChevronLeft className="size-4" />} disabled={fotoAktif === 0} onClick={() => setFotoAktif(fotoAktif - 1)}>
                Sebelumnya
              </Button>
              <span className="text-xs text-muted">
                {fotoAktif + 1} / {foto.length}
              </span>
              <Button variant="outline" size="sm" disabled={fotoAktif === foto.length - 1} onClick={() => setFotoAktif(fotoAktif + 1)}>
                Berikutnya
                <ChevronRight className="size-4" aria-hidden />
              </Button>
            </div>
          ) : undefined
        }
      >
        {fotoTerpilih && (
          <img src={fotoTerpilih.url} alt={fotoTerpilih.caption || 'Foto bukti pekerjaan'} className="mx-auto max-h-[70vh] w-auto rounded-lg object-contain" />
        )}
      </Modal>

      <ConfirmDialog
        open={konfirmasiHapus}
        title="Hapus Progres"
        pesan={
          tampilMaterial
            ? 'Progres beserta detail, foto, material, dan kendalanya akan dihapus. Lanjutkan?'
            : 'Progres beserta detail, foto, dan kendalanya akan dihapus. Lanjutkan?'
        }
        loading={hapus.isPending}
        onConfirm={() => hapus.mutate()}
        onClose={() => setKonfirmasiHapus(false)}
      />
    </div>
  )
}

function Info({ label, icon: Icon, className, children }: { label: string; icon?: LucideIcon; className?: string; children: ReactNode }) {
  return (
    <div className={className}>
      <dt className="flex items-center gap-1.5 text-xs text-muted">
        {Icon && <Icon className="size-3.5" aria-hidden />}
        {label}
      </dt>
      <dd className="mt-0.5 text-ink">{children}</dd>
    </div>
  )
}
