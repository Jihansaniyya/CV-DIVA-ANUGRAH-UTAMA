import { Button } from '@/components/ui/Button'
import { Input, Select } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { projectService, type WorkItemBatchPayload } from '@/services/projectService'
import type { Period, Unit, WorkCategory } from '@/types'
import { angka, rupiah } from '@/utils/format'
import { useMutation } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'

interface BarisPekerjaan {
  key: number
  uraian_pekerjaan: string
  unit_id: number | null
  volume: number | null
  harga_satuan: number | null
  period_mulai_id: number | null
  period_selesai_id: number | null
}

/** 'baru' = kelompok baru, 'tanpa' = tanpa kelompok, angka = id kelompok yang sudah ada. */
type PilihanKelompok = 'baru' | 'tanpa' | number

interface WorkItemBatchModalProps {
  open: boolean
  projectId: number
  units: Unit[]
  categories: WorkCategory[]
  periods: Period[]
  /** Total harga pekerjaan yang sudah ada, untuk pratinjau bobot. */
  totalHargaSaatIni: number
  onClose: () => void
  onSaved: () => Promise<void>
}

let kunciBaris = 0

const barisKosong = (): BarisPekerjaan => ({
  key: ++kunciBaris,
  uraian_pekerjaan: '',
  unit_id: null,
  volume: null,
  harga_satuan: null,
  period_mulai_id: null,
  period_selesai_id: null,
})

const hargaPekerjaan = (baris: BarisPekerjaan) => Math.round((baris.volume ?? 0) * (baris.harga_satuan ?? 0) * 100) / 100

/**
 * Tambah pekerjaan dalam satu form: tentukan kelompok (baru atau yang sudah ada),
 * lalu langsung isi daftar pekerjaannya. Semua disimpan dalam satu transaksi.
 */
export function WorkItemBatchModal({ open, projectId, units, categories, periods, totalHargaSaatIni, onClose, onSaved }: WorkItemBatchModalProps) {
  const toast = useToast()
  const [kelompok, setKelompok] = useState<PilihanKelompok>('baru')
  const [kategoriBaru, setKategoriBaru] = useState({ kode: '', nama: '' })
  const [daftar, setDaftar] = useState<BarisPekerjaan[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!open) return

    setKelompok('baru')
    setKategoriBaru({ kode: '', nama: '' })
    setDaftar([barisKosong()])
    setErrors({})
  }, [open])

  const simpan = useMutation({
    mutationFn: (payload: WorkItemBatchPayload) => projectService.createWorkItemBatch(projectId, payload),
    onSuccess: async (items) => {
      toast.sukses(`${items.length} pekerjaan berhasil ditambahkan.`)
      await onSaved()
      onClose()
    },
    onError: (err) => {
      const validasi = errorValidasi(err)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? 'Periksa kembali data yang kamu masukkan.' : pesanError(err))
    },
  })

  const urutan = (id: number | null) => periods.find((period) => period.id === id)?.urutan ?? 0

  const ubahBaris = (key: number, perubahan: Partial<BarisPekerjaan>) =>
    setDaftar((current) => current.map((baris) => (baris.key === key ? { ...baris, ...perubahan } : baris)))

  const totalHarga = totalHargaSaatIni + daftar.reduce((total, baris) => total + hargaPekerjaan(baris), 0)

  const kirim = (event: FormEvent) => {
    event.preventDefault()
    const validasi: Record<string, string> = {}

    if (kelompok === 'baru' && !kategoriBaru.nama.trim()) validasi['kategori_baru.nama'] = 'Nama kelompok pekerjaan wajib diisi.'

    daftar.forEach((baris, index) => {
      const k = (field: string) => `items.${index}.${field}`

      if (!baris.uraian_pekerjaan.trim()) validasi[k('uraian_pekerjaan')] = 'Uraian pekerjaan wajib diisi.'
      if (!baris.unit_id) validasi[k('unit_id')] = 'Satuan pekerjaan wajib dipilih.'
      if (!baris.volume || baris.volume <= 0) validasi[k('volume')] = 'Volume harus lebih besar dari 0.'
      if (baris.harga_satuan === null || baris.harga_satuan < 0) validasi[k('harga_satuan')] = 'Harga satuan wajib diisi.'
      if (!baris.period_mulai_id) validasi[k('period_mulai_id')] = 'Periode mulai wajib dipilih.'
      if (!baris.period_selesai_id) validasi[k('period_selesai_id')] = 'Periode selesai wajib dipilih.'
      else if (urutan(baris.period_selesai_id) < urutan(baris.period_mulai_id)) {
        validasi[k('period_selesai_id')] = 'Periode selesai tidak boleh sebelum periode mulai.'
      }
    })

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) return

    simpan.mutate({
      work_category_id: typeof kelompok === 'number' ? kelompok : null,
      kategori_baru: kelompok === 'baru' ? { kode: kategoriBaru.kode.trim() || null, nama: kategoriBaru.nama.trim() } : null,
      items: daftar.map((baris) => ({
        uraian_pekerjaan: baris.uraian_pekerjaan.trim(),
        unit_id: Number(baris.unit_id),
        volume: Number(baris.volume),
        harga_satuan: Number(baris.harga_satuan),
        period_mulai_id: baris.period_mulai_id,
        period_selesai_id: baris.period_selesai_id,
      })),
    })
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Tambah Pekerjaan"
      description="Tentukan kelompok pekerjaan, lalu isi daftar pekerjaannya. Bobot dan target awal dihitung otomatis."
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={simpan.isPending}>
            Batal
          </Button>
          <Button form="form-tambah-pekerjaan" type="submit" loading={simpan.isPending}>
            Simpan {daftar.length} Pekerjaan
          </Button>
        </>
      }
    >
      <form id="form-tambah-pekerjaan" className="grid gap-5" onSubmit={kirim} noValidate>
        <section className="grid gap-4 sm:grid-cols-[1fr_7rem_1.5fr]">
          <Select
            label="Kelompok Pekerjaan"
            required
            wrapClassName={kelompok === 'baru' ? '' : 'sm:col-span-3'}
            value={String(kelompok)}
            onChange={(event) => {
              const nilai = event.target.value
              setKelompok(nilai === 'baru' || nilai === 'tanpa' ? nilai : Number(nilai))
            }}
            error={errors.work_category_id}
          >
            <option value="baru">+ Kelompok baru</option>
            {categories.map((kategori) => (
              <option key={kategori.id} value={kategori.id}>
                {kategori.kode ? `${kategori.kode}. ` : ''}
                {kategori.nama}
              </option>
            ))}
            <option value="tanpa">Tanpa kelompok</option>
          </Select>
          {kelompok === 'baru' && (
            <>
              <Input
                label="Kode"
                value={kategoriBaru.kode}
                onChange={(event) => setKategoriBaru({ ...kategoriBaru, kode: event.target.value })}
                error={errors['kategori_baru.kode']}
                placeholder="Contoh: A"
              />
              <Input
                label="Nama Kelompok"
                required
                value={kategoriBaru.nama}
                onChange={(event) => setKategoriBaru({ ...kategoriBaru, nama: event.target.value })}
                error={errors['kategori_baru.nama']}
                placeholder="Contoh: PEKERJAAN PENDAHULUAN"
              />
            </>
          )}
        </section>

        <section className="grid gap-3">
          <h3 className="text-xs font-semibold tracking-wide text-muted uppercase">Daftar Pekerjaan</h3>

          {daftar.map((baris, index) => {
            const e = (field: string) => errors[`items.${index}.${field}`]
            const harga = hargaPekerjaan(baris)

            return (
              <div key={baris.key} className="rounded-xl border border-line p-3 sm:p-4">
                <div className="mb-3 flex items-center justify-between gap-2">
                  <p className="text-sm font-semibold text-ink">Pekerjaan {index + 1}</p>
                  {daftar.length > 1 && (
                    <button
                      type="button"
                      aria-label={`Hapus baris pekerjaan ${index + 1}`}
                      className="rounded-lg p-1.5 text-muted hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDaftar((current) => current.filter((item) => item.key !== baris.key))}
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </button>
                  )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  <Input
                    label="Uraian Pekerjaan"
                    required
                    wrapClassName="sm:col-span-2"
                    value={baris.uraian_pekerjaan}
                    onChange={(event) => ubahBaris(baris.key, { uraian_pekerjaan: event.target.value })}
                    error={e('uraian_pekerjaan')}
                    placeholder="Contoh: Pek. Beton K-250 Ready Mix"
                  />
                  <Select
                    label="Satuan Pekerjaan"
                    required
                    value={baris.unit_id ?? ''}
                    onChange={(event) => ubahBaris(baris.key, { unit_id: event.target.value ? Number(event.target.value) : null })}
                    error={e('unit_id')}
                  >
                    <option value="">Pilih satuan</option>
                    {units.map((unit) => (
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
                    value={baris.volume ?? ''}
                    onChange={(event) => ubahBaris(baris.key, { volume: event.target.value ? Number(event.target.value) : null })}
                    error={e('volume')}
                    placeholder="Contoh: 23,87"
                  />
                  <Input
                    label="Harga Satuan (Rp)"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={baris.harga_satuan ?? ''}
                    onChange={(event) => ubahBaris(baris.key, { harga_satuan: event.target.value ? Number(event.target.value) : null })}
                    error={e('harga_satuan')}
                    placeholder="Contoh: 2909892,81"
                  />
                  <Input
                    label="Harga Pekerjaan / Bobot"
                    readOnly
                    disabled
                    value={`${rupiah(harga)} · ${angka(totalHarga > 0 ? (harga / totalHarga) * 100 : 0, 4)}%`}
                    hint="Dihitung otomatis"
                  />
                  <Select
                    label="Periode Mulai"
                    required
                    value={baris.period_mulai_id ?? ''}
                    onChange={(event) => ubahBaris(baris.key, { period_mulai_id: event.target.value ? Number(event.target.value) : null })}
                    error={e('period_mulai_id')}
                  >
                    <option value="">Pilih minggu</option>
                    {periods.map((period) => (
                      <option key={period.id} value={period.id}>
                        {period.nama_periode}
                      </option>
                    ))}
                  </Select>
                  <Select
                    label="Periode Selesai"
                    required
                    value={baris.period_selesai_id ?? ''}
                    onChange={(event) => ubahBaris(baris.key, { period_selesai_id: event.target.value ? Number(event.target.value) : null })}
                    error={e('period_selesai_id')}
                  >
                    <option value="">Pilih minggu</option>
                    {periods.map((period) => (
                      <option key={period.id} value={period.id} disabled={period.urutan < urutan(baris.period_mulai_id)}>
                        {period.nama_periode}
                      </option>
                    ))}
                  </Select>
                </div>
              </div>
            )
          })}

          <Button type="button" variant="outline" icon={<Plus className="size-4" />} onClick={() => setDaftar((current) => [...current, barisKosong()])}>
            Tambah Baris Pekerjaan
          </Button>
          <p className="text-[11px] text-muted">Target volume awal dibagi rata ke setiap minggu aktif dan dapat diubah pada tab Rencana.</p>
        </section>
      </form>
    </Modal>
  )
}
