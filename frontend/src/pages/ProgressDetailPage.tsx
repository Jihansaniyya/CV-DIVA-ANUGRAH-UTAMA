import { Badge, ReportStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { ConfirmDialog } from '@/components/ui/Modal'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { qk, useProgress } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { progressService } from '@/services/progressService'
import { angka, persen, tanggal, waktu } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Send, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

export function ProgressDetailPage() {
  const { id } = useParams<{ id: string }>()
  const laporanId = Number(id)
  const { user, punyaPeran } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [konfirmasiHapus, setKonfirmasiHapus] = useState(false)

  const { data: laporan, isLoading, error, refetch } = useProgress(laporanId)

  const kirim = useMutation({
    mutationFn: () => progressService.submit(laporanId),
    onSuccess: async () => {
      toast.sukses('Laporan progres berhasil dikirim.')
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
      toast.sukses('Laporan progres berhasil dihapus.')
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
  if (!laporan) return <EmptyState judul="Laporan tidak ditemukan" />

  const bolehUbah = punyaPeran('ADMIN') || (laporan.user_id === user?.id && laporan.status === 'DRAFT')

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/progres" className="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink">
            <ArrowLeft className="size-4" aria-hidden />
            Kembali
          </Link>
          <h2 className="mt-1 flex flex-wrap items-center gap-2 text-lg font-semibold text-ink">
            Laporan {tanggal(laporan.tanggal_laporan)}
            <ReportStatusBadge status={laporan.status} />
          </h2>
          <p className="text-xs text-muted">
            <Link to={`/proyek/${laporan.project_id}`} className="hover:text-primary">
              {laporan.nama_proyek}
            </Link>
            {laporan.periode ? ` - ${laporan.periode}` : ''}
          </p>
        </div>

        {bolehUbah && (
          <div className="flex gap-2">
            {laporan.status === 'DRAFT' && (
              <Button icon={<Send className="size-4" />} loading={kirim.isPending} onClick={() => kirim.mutate()}>
                Kirim Laporan
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
          <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-xs text-muted">Pelapor</dt>
              <dd>{laporan.pelapor ?? '-'}</dd>
            </div>
            <div>
              <dt className="text-xs text-muted">Waktu Pelaporan</dt>
              <dd>{laporan.dikirim_pada ? waktu(laporan.dikirim_pada) : 'Belum dikirim'}</dd>
            </div>
            <div>
              <dt className="text-xs text-muted">Lokasi</dt>
              <dd>{laporan.lokasi ?? '-'}</dd>
            </div>
            <div>
              <dt className="text-xs text-muted">Cuaca</dt>
              <dd>{laporan.cuaca ?? '-'}</dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-xs text-muted">Keterangan</dt>
              <dd>{laporan.keterangan ?? '-'}</dd>
            </div>
          </dl>
        </Card>

        <Card title="Kontribusi Progres">
          <p className="text-3xl font-semibold text-ink">{persen(laporan.total_bobot_realisasi)}</p>
          <p className="mt-1 text-xs text-muted">Bobot realisasi dari laporan ini terhadap total progres proyek.</p>
        </Card>
      </div>

      <Card title="Detail Pekerjaan" bodyClassName="pt-0">
        <TableWrap>
          <Table>
            <thead>
              <tr>
                <Th>Uraian Pekerjaan</Th>
                <Th>Satuan</Th>
                <Th align="right">Volume Rencana</Th>
                <Th align="right">Volume Realisasi</Th>
                <Th align="right">Realisasi (%)</Th>
                <Th align="right">Bobot Realisasi (%)</Th>
                <Th>Keterangan</Th>
              </tr>
            </thead>
            <tbody>
              {laporan.detail?.map((detail) => (
                <tr key={detail.id}>
                  <Td className="font-medium">{detail.uraian_pekerjaan}</Td>
                  <Td className="text-muted">{detail.satuan}</Td>
                  <Td align="right">{angka(detail.volume_rencana, 2)}</Td>
                  <Td align="right">{angka(detail.volume_realisasi, 2)}</Td>
                  <Td align="right">{angka(detail.persentase_realisasi, 2)}</Td>
                  <Td align="right">{angka(detail.bobot_realisasi, 4)}</Td>
                  <Td className="text-muted">{detail.keterangan ?? '-'}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </TableWrap>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title="Material" bodyClassName="pt-0">
          {!laporan.material || laporan.material.length === 0 ? (
            <p className="py-3 text-xs text-muted">Tidak ada material yang dicatat.</p>
          ) : (
            <TableWrap>
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

        <Card title="Kendala dan Tindak Lanjut">
          {!laporan.kendala || laporan.kendala.length === 0 ? (
            <p className="text-xs text-muted">Tidak ada kendala yang dicatat.</p>
          ) : (
            <ul className="flex flex-col gap-3">
              {laporan.kendala.map((item) => (
                <li key={item.id} className="rounded-xl border border-line p-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge tone="warning">{item.jenis_kendala.replace('_', ' ')}</Badge>
                    {item.pekerjaan && <span className="text-[11px] text-muted">{item.pekerjaan}</span>}
                  </div>
                  <p className="mt-2 text-sm text-ink">{item.deskripsi}</p>
                  {item.alasan_keterlambatan && (
                    <p className="mt-1 text-xs text-muted">
                      <span className="font-medium">Alasan keterlambatan:</span> {item.alasan_keterlambatan}
                    </p>
                  )}
                  {item.tindak_lanjut && (
                    <p className="mt-1 text-xs text-muted">
                      <span className="font-medium">Tindak lanjut:</span> {item.tindak_lanjut}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <Card title="Foto Bukti Pekerjaan">
        {!laporan.foto || laporan.foto.length === 0 ? (
          <p className="text-xs text-muted">Tidak ada foto yang diunggah.</p>
        ) : (
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {laporan.foto.map((item) => (
              <li key={item.id} className="app-card overflow-hidden">
                <a href={item.url} target="_blank" rel="noreferrer">
                  <img src={item.url} alt={item.caption ?? 'Foto progres'} className="h-36 w-full object-cover" loading="lazy" />
                </a>
                <p className="p-2 text-[11px] text-muted">{item.caption ?? 'Tanpa keterangan'}</p>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <ConfirmDialog
        open={konfirmasiHapus}
        title="Hapus Laporan Progres"
        pesan="Laporan beserta detail, foto, material, dan kendalanya akan dihapus. Lanjutkan?"
        loading={hapus.isPending}
        onConfirm={() => hapus.mutate()}
        onClose={() => setKonfirmasiHapus(false)}
      />
    </div>
  )
}
