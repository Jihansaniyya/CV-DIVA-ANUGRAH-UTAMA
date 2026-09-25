import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, Input, Select } from '@/components/ui/Field'
import { ConfirmDialog, Modal } from '@/components/ui/Modal'
import { ProgressBar } from '@/components/ui/ProgressBar'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { qk, useCategories, useUnits, useWorkItems } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { projectService, type WorkItemPayload } from '@/services/projectService'
import type { WorkItem } from '@/types'
import { angka, rupiah, tanggalSingkat } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FolderPlus, Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'

const KOSONG: WorkItemPayload = {
  work_category_id: null,
  unit_id: 0,
  uraian_pekerjaan: '',
  volume: 0,
  harga_satuan: null,
  waktu_mulai: null,
  waktu_selesai: null,
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

  const [formTerbuka, setFormTerbuka] = useState(false)
  const [kategoriTerbuka, setKategoriTerbuka] = useState(false)
  const [itemDiedit, setItemDiedit] = useState<WorkItem | null>(null)
  const [itemDihapus, setItemDihapus] = useState<WorkItem | null>(null)
  const [form, setForm] = useState<WorkItemPayload>(KOSONG)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [kategoriBaru, setKategoriBaru] = useState({ kode: '', nama: '' })

  useEffect(() => {
    if (!formTerbuka) return

    setErrors({})
    setForm(
      itemDiedit
        ? {
            work_category_id: itemDiedit.work_category_id,
            unit_id: itemDiedit.unit_id,
            uraian_pekerjaan: itemDiedit.uraian_pekerjaan,
            volume: itemDiedit.volume,
            harga_satuan: itemDiedit.harga_satuan,
            waktu_mulai: itemDiedit.waktu_mulai,
            waktu_selesai: itemDiedit.waktu_selesai,
            keterangan: itemDiedit.keterangan,
          }
        : { ...KOSONG, unit_id: units?.[0]?.id ?? 0, work_category_id: categories?.[0]?.id ?? null },
    )
  }, [formTerbuka, itemDiedit, units, categories])

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: qk.workItems(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.workPlans(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.curve(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.project(projectId) })
  }

  const simpan = useMutation({
    mutationFn: (payload: WorkItemPayload) =>
      itemDiedit
        ? projectService.updateWorkItem(projectId, itemDiedit.id, payload)
        : projectService.createWorkItem(projectId, payload),
    onSuccess: async () => {
      toast.sukses(itemDiedit ? 'Pekerjaan berhasil diperbarui.' : 'Pekerjaan berhasil ditambahkan.')
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

  const simpanKategori = useMutation({
    mutationFn: () => projectService.createCategory(projectId, { kode: kategoriBaru.kode || null, nama: kategoriBaru.nama }),
    onSuccess: async () => {
      toast.sukses('Kelompok pekerjaan berhasil ditambahkan.')
      setKategoriBaru({ kode: '', nama: '' })
      setKategoriTerbuka(false)
      await queryClient.invalidateQueries({ queryKey: qk.categories(projectId) })
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  const kirim = (event: FormEvent) => {
    event.preventDefault()
    const validasi: Record<string, string> = {}

    if (!form.uraian_pekerjaan.trim()) validasi.uraian_pekerjaan = 'Uraian pekerjaan wajib diisi.'
    if (!form.unit_id) validasi.unit_id = 'Satuan pekerjaan wajib dipilih.'
    if (!form.volume || form.volume <= 0) validasi.volume = 'Volume harus lebih besar dari 0.'
    if (form.harga_satuan === null || form.harga_satuan < 0) validasi.harga_satuan = 'Harga satuan wajib diisi.'

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
          <div className="flex gap-2">
            <Button variant="outline" size="sm" icon={<FolderPlus className="size-4" />} onClick={() => setKategoriTerbuka(true)}>
              Kelompok
            </Button>
            <Button
              size="sm"
              icon={<Plus className="size-4" />}
              onClick={() => {
                setItemDiedit(null)
                setFormTerbuka(true)
              }}
            >
              Tambah Pekerjaan
            </Button>
          </div>
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
                <Th>Waktu Pelaksanaan</Th>
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
                    {item.waktu_mulai ? `${tanggalSingkat(item.waktu_mulai)} - ${tanggalSingkat(item.waktu_selesai)}` : '-'}
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
        title={itemDiedit ? 'Ubah Pekerjaan' : 'Tambah Pekerjaan'}
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
          <DatePicker
            label="Waktu Mulai"
            value={form.waktu_mulai ?? ''}
            onChange={(event) => setForm({ ...form, waktu_mulai: event.target.value || null })}
            error={errors.waktu_mulai}
          />
          <DatePicker
            label="Waktu Selesai"
            value={form.waktu_selesai ?? ''}
            onChange={(event) => setForm({ ...form, waktu_selesai: event.target.value || null })}
            error={errors.waktu_selesai}
          />
        </form>
      </Modal>

      <Modal
        open={kategoriTerbuka}
        onClose={() => setKategoriTerbuka(false)}
        title="Tambah Kelompok Pekerjaan"
        description="Kelompok dipakai sebagai bagian A, B, ... pada laporan."
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setKategoriTerbuka(false)}>
              Batal
            </Button>
            <Button loading={simpanKategori.isPending} disabled={!kategoriBaru.nama.trim()} onClick={() => simpanKategori.mutate()}>
              Simpan
            </Button>
          </>
        }
      >
        <div className="grid gap-4">
          <Input label="Kode" value={kategoriBaru.kode} onChange={(event) => setKategoriBaru({ ...kategoriBaru, kode: event.target.value })} placeholder="A" />
          <Input
            label="Nama Kelompok"
            required
            value={kategoriBaru.nama}
            onChange={(event) => setKategoriBaru({ ...kategoriBaru, nama: event.target.value })}
            placeholder="PEKERJAAN PENDAHULUAN"
          />
        </div>
      </Modal>

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
