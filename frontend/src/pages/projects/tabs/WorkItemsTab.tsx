import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Input, Select } from '@/components/ui/Field'
import { ConfirmDialog, Modal } from '@/components/ui/Modal'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { qk, useCategories, usePeriods, useUnits, useWorkItems } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { projectService, type WorkItemPayload } from '@/services/projectService'
import type { WorkItem } from '@/types'
import { angka, rupiah } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { WorkItemBatchModal } from './WorkItemBatchModal'

const KOSONG: WorkItemPayload = {
  work_category_id: null,
  unit_id: 0,
  uraian_pekerjaan: '',
  volume: 0,
  harga_satuan: null,
  period_mulai_id: null,
  period_selesai_id: null,
  keterangan: null,
}

export function WorkItemsTab({ projectId }: { projectId: number }) {
  const { punyaPeran } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const adminMode = punyaPeran('ADMIN')

  const { data, isLoading, error, refetch } = useWorkItems(projectId)
  const { data: units } = useUnits()
  const { data: categories } = useCategories(projectId)
  const { data: periods } = usePeriods(projectId)

  const [formTerbuka, setFormTerbuka] = useState(false)
  const [tambahTerbuka, setTambahTerbuka] = useState(false)
  const [itemDiedit, setItemDiedit] = useState<WorkItem | null>(null)
  const [itemDihapus, setItemDihapus] = useState<WorkItem | null>(null)
  const [form, setForm] = useState<WorkItemPayload>(KOSONG)
  const [errors, setErrors] = useState<Record<string, string>>({})

  /** Form "Ubah Pekerjaan"; penambahan pekerjaan memakai WorkItemBatchModal. */
  useEffect(() => {
    if (!formTerbuka || !itemDiedit) return

    setErrors({})
    setForm({
      work_category_id: itemDiedit.work_category_id,
      unit_id: itemDiedit.unit_id,
      uraian_pekerjaan: itemDiedit.uraian_pekerjaan,
      volume: itemDiedit.volume,
      harga_satuan: itemDiedit.harga_satuan,
      period_mulai_id: itemDiedit.period_mulai_id,
      period_selesai_id: itemDiedit.period_selesai_id,
      keterangan: itemDiedit.keterangan,
    })
  }, [formTerbuka, itemDiedit])

  const urutanPeriode = (id: number | null) => periods?.find((period) => period.id === id)?.urutan ?? 0

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: qk.workItems(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.workPlans(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.curve(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.project(projectId) })
  }

  const simpan = useMutation({
    mutationFn: (payload: WorkItemPayload) => projectService.updateWorkItem(projectId, (itemDiedit as WorkItem).id, payload),
    onSuccess: async () => {
      toast.sukses('Pekerjaan berhasil diperbarui.')
      await invalidate()
      setFormTerbuka(false)
    },
    onError: (err) => {
      const validasi = errorValidasi(err)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? 'Periksa kembali data yang kamu masukkan.' : pesanError(err))
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => projectService.removeWorkItem(projectId, id),
    onSuccess: async () => {
      toast.sukses('Pekerjaan berhasil dihapus.')
      setItemDihapus(null)
      await invalidate()
    },
    onError: (err) => {
      toast.gagal(pesanError(err))
      setItemDihapus(null)
    },
  })

  const kirim = (event: FormEvent) => {
    event.preventDefault()
    const validasi: Record<string, string> = {}

    if (!form.uraian_pekerjaan.trim()) validasi.uraian_pekerjaan = 'Uraian pekerjaan wajib diisi.'
    if (!form.unit_id) validasi.unit_id = 'Satuan pekerjaan wajib dipilih.'
    if (!form.volume || form.volume <= 0) validasi.volume = 'Volume harus lebih besar dari 0.'
    if (form.harga_satuan === null || form.harga_satuan < 0) validasi.harga_satuan = 'Harga satuan wajib diisi.'
    if (!form.period_mulai_id) validasi.period_mulai_id = 'Periode mulai wajib dipilih.'
    if (!form.period_selesai_id) validasi.period_selesai_id = 'Periode selesai wajib dipilih.'
    else if (urutanPeriode(form.period_selesai_id) < urutanPeriode(form.period_mulai_id)) {
      validasi.period_selesai_id = 'Periode selesai tidak boleh sebelum periode mulai.'
    }

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) return

    simpan.mutate({
      ...form,
      volume: Number(form.volume),
      unit_id: Number(form.unit_id),
      harga_satuan: Number(form.harga_satuan),
      work_category_id: form.work_category_id ? Number(form.work_category_id) : null,
    })
  }

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />

  const items = data?.data ?? []

  /**
   * Pratinjau read-only pada form. Nilai final tetap dihitung backend
   * (WeightCalculatorService) setelah data disimpan.
   */
  const hargaPekerjaan = Math.round(Number(form.volume || 0) * Number(form.harga_satuan || 0) * 100) / 100
  const hargaLain = items.filter((item) => item.id !== itemDiedit?.id).reduce((total, item) => total + (item.harga_pekerjaan ?? 0), 0)
  const totalHarga = hargaLain + hargaPekerjaan
  const pratinjau = { hargaPekerjaan, totalHarga, bobot: totalHarga > 0 ? (hargaPekerjaan / totalHarga) * 100 : 0 }

  return (
    <Card
      title="Data Pekerjaan"
      description={
        data
          ? `Total harga ${rupiah(data.meta.total_harga_pekerjaan)} - Total bobot ${angka(data.meta.total_bobot)}% (dihitung otomatis)`
          : undefined
      }
      action={
        adminMode ? (
          <Button size="sm" icon={<Plus className="size-4" />} onClick={() => setTambahTerbuka(true)}>
            Tambah Pekerjaan
          </Button>
        ) : undefined
      }
      bodyClassName="pt-0"
    >
      {items.length === 0 ? (
        <EmptyState judul="Belum ada pekerjaan" pesan="Tambahkan uraian pekerjaan beserta volume dan satuannya." />
      ) : (
        <TableWrap>
          <Table>
            <thead>
              <tr>
                <Th>No</Th>
                <Th>Uraian Pekerjaan</Th>
                <Th>Satuan</Th>
                <Th align="right">Volume</Th>
                <Th align="right">Harga Satuan</Th>
                <Th align="right">Harga Pekerjaan</Th>
                <Th align="right">Bobot (%)</Th>
                <Th>Periode</Th>
                <Th>Progres</Th>
                {adminMode && <Th align="center">Aksi</Th>}
              </tr>
            </thead>
            <tbody>
              {items.map((item, index) => (
                <tr key={item.id} className="hover:bg-surface/60">
                  <Td>{index + 1}</Td>
                  <Td>
                    <p className="font-medium text-ink">{item.uraian_pekerjaan}</p>
                    {item.kategori && <p className="text-[11px] text-muted">{item.kategori.nama}</p>}
                  </Td>
                  <Td className="text-muted">{item.satuan}</Td>
                  <Td align="right">{angka(item.volume, 2)}</Td>
                  <Td align="right">{item.harga_satuan ? rupiah(item.harga_satuan) : '-'}</Td>
                  <Td align="right">{item.harga_pekerjaan ? rupiah(item.harga_pekerjaan) : '-'}</Td>
                  <Td align="right">{angka(item.bobot, 4)}</Td>
                  <Td className="text-muted whitespace-nowrap">
                    {item.periode_mulai ? `${item.periode_mulai} s/d ${item.periode_selesai ?? item.periode_mulai}` : '-'}
                  </Td>
                  <Td className="min-w-36">
                    <ProgressBar nilai={item.persentase_realisasi ?? 0} />
                    <p className="mt-0.5 text-[10px] text-muted">
                      {angka(item.volume_realisasi ?? 0, 2)} / {angka(item.volume, 2)} {item.satuan}
                    </p>
                  </Td>
                  {adminMode && (
                    <Td align="center">
                      <div className="flex justify-center gap-1">
                        <button
                          type="button"
                          aria-label="Ubah pekerjaan"
                          className="rounded-lg p-1.5 text-muted hover:bg-surface hover:text-navy"
                          onClick={() => {
                            setItemDiedit(item)
                            setFormTerbuka(true)
                          }}
                        >
                          <Pencil className="size-4" aria-hidden />
                        </button>
                        <button
                          type="button"
                          aria-label="Hapus pekerjaan"
                          className="rounded-lg p-1.5 text-muted hover:bg-danger-soft hover:text-danger"
                          onClick={() => setItemDihapus(item)}
                        >
                          <Trash2 className="size-4" aria-hidden />
                        </button>
                      </div>
                    </Td>
                  )}
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="bg-surface/60 font-semibold">
                <Td colSpan={5}>JUMLAH</Td>
                <Td align="right">{rupiah(data?.meta.total_harga_pekerjaan ?? 0)}</Td>
                <Td align="right">{angka(data?.meta.total_bobot ?? 0, 4)}</Td>
                <Td colSpan={adminMode ? 3 : 2} />
              </tr>
            </tfoot>
          </Table>
        </TableWrap>
      )}

      <Modal
        open={formTerbuka}
        onClose={() => setFormTerbuka(false)}
        title="Ubah Pekerjaan"
        description="Bobot pekerjaan dihitung otomatis dari harga pekerjaan terhadap total harga proyek."
        footer={
          <>
            <Button variant="outline" onClick={() => setFormTerbuka(false)} disabled={simpan.isPending}>
              Batal
            </Button>
            <Button form="form-pekerjaan" type="submit" loading={simpan.isPending}>
              Simpan
            </Button>
          </>
        }
      >
        <form id="form-pekerjaan" className="grid gap-4 sm:grid-cols-2" onSubmit={kirim} noValidate>
          <Input
            label="Uraian Pekerjaan"
            required
            wrapClassName="sm:col-span-2"
            value={form.uraian_pekerjaan}
            onChange={(event) => setForm({ ...form, uraian_pekerjaan: event.target.value })}
            error={errors.uraian_pekerjaan}
            placeholder="Masukkan uraian pekerjaan"
          />
          <Select
            label="Kelompok Pekerjaan"
            value={form.work_category_id ?? ''}
            onChange={(event) => setForm({ ...form, work_category_id: event.target.value ? Number(event.target.value) : null })}
            error={errors.work_category_id}
          >
            <option value="">Tanpa kelompok</option>
            {categories?.map((kategori) => (
              <option key={kategori.id} value={kategori.id}>
                {kategori.kode ? `${kategori.kode}. ` : ''}
                {kategori.nama}
              </option>
            ))}
          </Select>
          <Select
            label="Satuan Pekerjaan"
            required
            value={form.unit_id || ''}
            onChange={(event) => setForm({ ...form, unit_id: Number(event.target.value) })}
            error={errors.unit_id}
          >
            <option value="">Pilih satuan pekerjaan</option>
            {units?.map((unit) => (
              <option key={unit.id} value={unit.id}>
                {unit.code} - {unit.name}
              </option>
            ))}
          </Select>
          <Input
            label="Volume"
            type="number"
            step="0.001"
            min="0"
            required
            value={form.volume || ''}
            onChange={(event) => setForm({ ...form, volume: Number(event.target.value) })}
            error={errors.volume}
            placeholder="Masukkan volume"
          />
          <Input
            label="Harga Satuan (Rp)"
            type="number"
            step="0.01"
            min="0"
            required
            value={form.harga_satuan ?? ''}
            onChange={(event) => setForm({ ...form, harga_satuan: event.target.value ? Number(event.target.value) : null })}
            error={errors.harga_satuan}
            placeholder="Masukkan harga satuan"
          />
          <Input
            label="Harga Pekerjaan (Rp)"
            readOnly
            disabled
            value={rupiah(pratinjau.hargaPekerjaan)}
            hint="Volume × Harga Satuan"
          />
          <Input
            label="Bobot Pekerjaan (%)"
            readOnly
            disabled
            value={angka(pratinjau.bobot, 4)}
            hint={`Harga Pekerjaan / Total Harga Proyek (${rupiah(pratinjau.totalHarga)}) × 100%`}
          />
          <Select
            label="Periode Mulai"
            required
            value={form.period_mulai_id ?? ''}
            onChange={(event) => setForm({ ...form, period_mulai_id: event.target.value ? Number(event.target.value) : null })}
            error={errors.period_mulai_id}
          >
            <option value="">Pilih minggu</option>
            {periods?.map((period) => (
              <option key={period.id} value={period.id}>
                {period.nama_periode}
              </option>
            ))}
          </Select>
          <Select
            label="Periode Selesai"
            required
            value={form.period_selesai_id ?? ''}
            onChange={(event) => setForm({ ...form, period_selesai_id: event.target.value ? Number(event.target.value) : null })}
            error={errors.period_selesai_id}
          >
            <option value="">Pilih minggu</option>
            {periods?.map((period) => (
              <option key={period.id} value={period.id} disabled={period.urutan < urutanPeriode(form.period_mulai_id)}>
                {period.nama_periode}
              </option>
            ))}
          </Select>
          <p className="text-[11px] text-muted sm:col-span-2">
            Mengubah Periode Mulai/Selesai membagi ulang target volume secara merata. Mengubah volume menyesuaikan target secara
            proporsional.
          </p>
        </form>
      </Modal>

      <WorkItemBatchModal
        open={tambahTerbuka}
        projectId={projectId}
        units={units ?? []}
        categories={categories ?? []}
        periods={periods ?? []}
        totalHargaSaatIni={data?.meta.total_harga_pekerjaan ?? 0}
        onClose={() => setTambahTerbuka(false)}
        onSaved={async () => {
          await queryClient.invalidateQueries({ queryKey: qk.categories(projectId) })
          await invalidate()
        }}
      />

      <ConfirmDialog
        open={Boolean(itemDihapus)}
        title="Hapus Pekerjaan"
        pesan={`Pekerjaan "${itemDihapus?.uraian_pekerjaan ?? ''}" akan dihapus beserta rencananya. Lanjutkan?`}
        loading={hapus.isPending}
        onConfirm={() => itemDihapus && hapus.mutate(itemDihapus.id)}
        onClose={() => setItemDihapus(null)}
      />
    </Card>
  )
}
