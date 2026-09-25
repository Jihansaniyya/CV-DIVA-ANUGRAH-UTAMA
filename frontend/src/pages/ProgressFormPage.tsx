import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, Input, Select, Textarea } from '@/components/ui/Field'
import { FileUpload, type FotoTerpilih } from '@/components/ui/FileUpload'
import { EmptyState, LoadingState } from '@/components/ui/State'
import { qk, useProjects, useWorkItems } from '@/hooks/queries'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { progressService, type ProgressPayload } from '@/services/progressService'
import { angka, hariIni } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Plus, Save, Send, Trash2 } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'

interface BarisDetail {
  work_item_id: number | ''
  volume_realisasi: number | ''
  keterangan: string
}

interface BarisMaterial {
  nama_material: string
  jumlah: number | ''
  satuan: string
  keterangan: string
}

interface BarisKendala {
  work_item_id: number | ''
  jenis_kendala: string
  deskripsi: string
  alasan_keterlambatan: string
  tindak_lanjut: string
}

const JENIS_KENDALA = [
  ['CUACA', 'Cuaca'],
  ['MATERIAL', 'Material'],
  ['TENAGA_KERJA', 'Tenaga Kerja'],
  ['PERALATAN', 'Peralatan'],
  ['TEKNIS', 'Teknis'],
  ['LAINNYA', 'Lainnya'],
]

/** Form input laporan progres harian oleh QS. */
export function ProgressFormPage() {
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [params] = useSearchParams()

  const [projectId, setProjectId] = useState<number | ''>(params.get('project_id') ? Number(params.get('project_id')) : '')
  const [tanggal, setTanggal] = useState(hariIni())
  const [lokasi, setLokasi] = useState('')
  const [cuaca, setCuaca] = useState('')
  const [keterangan, setKeterangan] = useState('')
  const [details, setDetails] = useState<BarisDetail[]>([{ work_item_id: '', volume_realisasi: '', keterangan: '' }])
  const [materials, setMaterials] = useState<BarisMaterial[]>([])
  const [issues, setIssues] = useState<BarisKendala[]>([])
  const [foto, setFoto] = useState<FotoTerpilih[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})

  const { data: projects, isLoading: memuatProyek } = useProjects({ per_page: 100 })
  const { data: pekerjaan } = useWorkItems(projectId ? Number(projectId) : null)

  useEffect(() => {
    if (!projectId || !projects) return

    const project = projects.data.find((item) => item.id === Number(projectId))

    if (project && !lokasi) {
      setLokasi(project.lokasi)
    }
  }, [projectId, projects, lokasi])

  const simpan = useMutation({
    mutationFn: (payload: ProgressPayload) => progressService.create(payload),
    onSuccess: async (laporan) => {
      toast.sukses(laporan.status === 'DIKIRIM' ? 'Laporan progres berhasil dikirim.' : 'Laporan progres tersimpan sebagai draft.')
      await queryClient.invalidateQueries({ queryKey: ['progress'] })
      await queryClient.invalidateQueries({ queryKey: qk.dashboard })
      await queryClient.invalidateQueries({ queryKey: qk.curve(laporan.project_id) })
      navigate(`/progres/${laporan.id}`)
    },
    onError: (error) => {
      const validasi = errorValidasi(error)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? Object.values(validasi)[0] : pesanError(error))
    },
  })

  const kirim = (event: FormEvent, status: 'DRAFT' | 'DIKIRIM') => {
    event.preventDefault()
    const validasi: Record<string, string> = {}

    if (!projectId) validasi.project_id = 'Proyek wajib dipilih.'
    if (!tanggal) validasi.tanggal_laporan = 'Tanggal laporan wajib diisi.'

    const detailValid = details.filter((baris) => baris.work_item_id !== '' && baris.volume_realisasi !== '')

    if (detailValid.length === 0) validasi.details = 'Minimal satu pekerjaan beserta volume realisasi wajib diisi.'

    const duplikat = new Set(detailValid.map((baris) => baris.work_item_id)).size !== detailValid.length

    if (duplikat) validasi.details = 'Pekerjaan yang sama tidak boleh diinput dua kali dalam satu laporan.'

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) {
      toast.gagal(Object.values(validasi)[0])

      return
    }

    simpan.mutate({
      project_id: Number(projectId),
      tanggal_laporan: tanggal,
      lokasi: lokasi || null,
      cuaca: cuaca || null,
      keterangan: keterangan || null,
      status,
      details: detailValid.map((baris) => ({
        work_item_id: Number(baris.work_item_id),
        volume_realisasi: Number(baris.volume_realisasi),
        keterangan: baris.keterangan || null,
      })),
      materials: materials
        .filter((baris) => baris.nama_material.trim())
        .map((baris) => ({
          nama_material: baris.nama_material,
          jumlah: Number(baris.jumlah || 0),
          satuan: baris.satuan || null,
          keterangan: baris.keterangan || null,
        })),
      issues: issues
        .filter((baris) => baris.deskripsi.trim())
        .map((baris) => ({
          work_item_id: baris.work_item_id ? Number(baris.work_item_id) : null,
          jenis_kendala: baris.jenis_kendala,
          deskripsi: baris.deskripsi,
          alasan_keterlambatan: baris.alasan_keterlambatan || null,
          tindak_lanjut: baris.tindak_lanjut || null,
        })),
      photos: foto.map((item) => item.file),
      photo_captions: foto.map((item) => item.caption),
    })
  }

  if (memuatProyek) return <LoadingState />

  if (!projects || projects.data.length === 0) {
    return <EmptyState judul="Belum ada proyek yang ditugaskan" pesan="Hubungi Admin untuk penugasan proyek." />
  }

  const daftarPekerjaan = pekerjaan?.data ?? []

  return (
    <form className="flex flex-col gap-4" onSubmit={(event) => kirim(event, 'DIKIRIM')} noValidate>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/progres" className="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink">
            <ArrowLeft className="size-4" aria-hidden />
            Kembali
          </Link>
          <h2 className="mt-1 text-lg font-semibold text-ink">Input Laporan Progres Harian</h2>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" icon={<Save className="size-4" />} loading={simpan.isPending} onClick={(event) => kirim(event, 'DRAFT')}>
            Simpan Draft
          </Button>
          <Button type="submit" icon={<Send className="size-4" />} loading={simpan.isPending}>
            Kirim Laporan
          </Button>
        </div>
      </div>

      <Card title="Informasi Laporan">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="Proyek"
            required
            value={projectId}
            onChange={(event) => {
              setProjectId(event.target.value ? Number(event.target.value) : '')
              setDetails([{ work_item_id: '', volume_realisasi: '', keterangan: '' }])
            }}
            error={errors.project_id}
          >
            <option value="">Pilih proyek</option>
            {projects.data.map((project) => (
              <option key={project.id} value={project.id}>
                {project.nama_proyek}
              </option>
            ))}
          </Select>
          <DatePicker label="Tanggal Laporan" required value={tanggal} onChange={(event) => setTanggal(event.target.value)} error={errors.tanggal_laporan} />
          <Input label="Lokasi" value={lokasi} onChange={(event) => setLokasi(event.target.value)} error={errors.lokasi} placeholder="Lokasi pekerjaan" />
          <Input label="Cuaca" value={cuaca} onChange={(event) => setCuaca(event.target.value)} error={errors.cuaca} placeholder="Contoh: Cerah berawan" />
          <Textarea
            label="Keterangan Pekerjaan"
            wrapClassName="sm:col-span-2 lg:col-span-4"
            value={keterangan}
            onChange={(event) => setKeterangan(event.target.value)}
            error={errors.keterangan}
            placeholder="Uraian singkat pelaksanaan pekerjaan hari ini"
          />
        </div>
      </Card>

      <Card
        title="Pekerjaan yang Dilaporkan"
        description="Pilih pekerjaan dan masukkan volume yang terealisasi pada tanggal laporan."
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<Plus className="size-4" />}
            onClick={() => setDetails((current) => [...current, { work_item_id: '', volume_realisasi: '', keterangan: '' }])}
          >
            Tambah Baris
          </Button>
        }
      >
        {!projectId ? (
          <EmptyState judul="Pilih proyek terlebih dahulu" pesan="Daftar pekerjaan tampil setelah proyek dipilih." />
        ) : (
          <ul className="flex flex-col gap-3">
            {details.map((baris, index) => {
              const item = daftarPekerjaan.find((pekerjaanItem) => pekerjaanItem.id === Number(baris.work_item_id))
              const sisa = item ? item.volume - (item.volume_realisasi ?? 0) : null

              return (
                <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                  <Select
                    label="Pekerjaan"
                    wrapClassName="sm:col-span-5"
                    value={baris.work_item_id}
                    onChange={(event) =>
                      setDetails((current) =>
                        current.map((row, i) => (i === index ? { ...row, work_item_id: event.target.value ? Number(event.target.value) : '' } : row)),
                      )
                    }
                  >
                    <option value="">Pilih pekerjaan</option>
                    {daftarPekerjaan.map((pekerjaanItem) => (
                      <option key={pekerjaanItem.id} value={pekerjaanItem.id}>
                        {pekerjaanItem.uraian_pekerjaan}
                      </option>
                    ))}
                  </Select>
                  <Input
                    label="Volume Realisasi"
                    wrapClassName="sm:col-span-3"
                    type="number"
                    step="0.001"
                    min="0"
                    value={baris.volume_realisasi}
                    onChange={(event) =>
                      setDetails((current) =>
                        current.map((row, i) => (i === index ? { ...row, volume_realisasi: event.target.value ? Number(event.target.value) : '' } : row)),
                      )
                    }
                    hint={item ? `Satuan ${item.satuan} - sisa ${angka(sisa ?? 0, 2)}` : undefined}
                  />
                  <Input
                    label="Keterangan"
                    wrapClassName="sm:col-span-3"
                    value={baris.keterangan}
                    onChange={(event) =>
                      setDetails((current) => current.map((row, i) => (i === index ? { ...row, keterangan: event.target.value } : row)))
                    }
                  />
                  <div className="flex items-end sm:col-span-1">
                    <Button
                      variant="ghost"
                      className="text-danger"
                      aria-label="Hapus baris"
                      onClick={() => setDetails((current) => current.filter((_, i) => i !== index))}
                      icon={<Trash2 className="size-4" />}
                    />
                  </div>
                </li>
              )
            })}
          </ul>
        )}
        {errors.details && <p className="mt-2 text-[11px] font-medium text-danger">{errors.details}</p>}
      </Card>

      <Card
        title="Material yang Digunakan"
        description="Hanya untuk kebutuhan pelaporan progres, bukan pengelolaan persediaan."
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<Plus className="size-4" />}
            onClick={() => setMaterials((current) => [...current, { nama_material: '', jumlah: '', satuan: '', keterangan: '' }])}
          >
            Tambah Material
          </Button>
        }
      >
        {materials.length === 0 ? (
          <p className="text-xs text-muted">Belum ada material yang dicatat.</p>
        ) : (
          <ul className="flex flex-col gap-3">
            {materials.map((baris, index) => (
              <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                <Input
                  label="Nama Material"
                  wrapClassName="sm:col-span-4"
                  value={baris.nama_material}
                  onChange={(event) =>
                    setMaterials((current) => current.map((row, i) => (i === index ? { ...row, nama_material: event.target.value } : row)))
                  }
                />
                <Input
                  label="Jumlah"
                  wrapClassName="sm:col-span-2"
                  type="number"
                  step="0.001"
                  min="0"
                  value={baris.jumlah}
                  onChange={(event) =>
                    setMaterials((current) =>
                      current.map((row, i) => (i === index ? { ...row, jumlah: event.target.value ? Number(event.target.value) : '' } : row)),
                    )
                  }
                />
                <Input
                  label="Satuan"
                  wrapClassName="sm:col-span-2"
                  value={baris.satuan}
                  onChange={(event) => setMaterials((current) => current.map((row, i) => (i === index ? { ...row, satuan: event.target.value } : row)))}
                />
                <Input
                  label="Keterangan"
                  wrapClassName="sm:col-span-3"
                  value={baris.keterangan}
                  onChange={(event) =>
                    setMaterials((current) => current.map((row, i) => (i === index ? { ...row, keterangan: event.target.value } : row)))
                  }
                />
                <div className="flex items-end sm:col-span-1">
                  <Button
                    variant="ghost"
                    className="text-danger"
                    aria-label="Hapus material"
                    onClick={() => setMaterials((current) => current.filter((_, i) => i !== index))}
                    icon={<Trash2 className="size-4" />}
                  />
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <Card
        title="Kendala, Alasan Keterlambatan, dan Tindak Lanjut"
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<Plus className="size-4" />}
            onClick={() =>
              setIssues((current) => [
                ...current,
                { work_item_id: '', jenis_kendala: 'LAINNYA', deskripsi: '', alasan_keterlambatan: '', tindak_lanjut: '' },
              ])
            }
          >
            Tambah Kendala
          </Button>
        }
      >
        {issues.length === 0 ? (
          <p className="text-xs text-muted">Belum ada kendala yang dicatat.</p>
        ) : (
          <ul className="flex flex-col gap-3">
            {issues.map((baris, index) => (
              <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-2">
                <Select
                  label="Pekerjaan Terkait"
                  value={baris.work_item_id}
                  onChange={(event) =>
                    setIssues((current) =>
                      current.map((row, i) => (i === index ? { ...row, work_item_id: event.target.value ? Number(event.target.value) : '' } : row)),
                    )
                  }
                >
                  <option value="">Tidak spesifik</option>
                  {daftarPekerjaan.map((pekerjaanItem) => (
                    <option key={pekerjaanItem.id} value={pekerjaanItem.id}>
                      {pekerjaanItem.uraian_pekerjaan}
                    </option>
                  ))}
                </Select>
                <Select
                  label="Jenis Kendala"
                  value={baris.jenis_kendala}
                  onChange={(event) => setIssues((current) => current.map((row, i) => (i === index ? { ...row, jenis_kendala: event.target.value } : row)))}
                >
                  {JENIS_KENDALA.map(([nilai, label]) => (
                    <option key={nilai} value={nilai}>
                      {label}
                    </option>
                  ))}
                </Select>
                <Textarea
                  label="Deskripsi Kendala"
                  value={baris.deskripsi}
                  onChange={(event) => setIssues((current) => current.map((row, i) => (i === index ? { ...row, deskripsi: event.target.value } : row)))}
                />
                <Textarea
                  label="Alasan Keterlambatan"
                  value={baris.alasan_keterlambatan}
                  onChange={(event) =>
                    setIssues((current) => current.map((row, i) => (i === index ? { ...row, alasan_keterlambatan: event.target.value } : row)))
                  }
                />
                <Textarea
                  label="Rencana Tindak Lanjut"
                  wrapClassName="sm:col-span-2"
                  value={baris.tindak_lanjut}
                  onChange={(event) => setIssues((current) => current.map((row, i) => (i === index ? { ...row, tindak_lanjut: event.target.value } : row)))}
                />
                <div className="sm:col-span-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="text-danger"
                    onClick={() => setIssues((current) => current.filter((_, i) => i !== index))}
                    icon={<Trash2 className="size-4" />}
                  >
                    Hapus kendala
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <Card title="Foto Bukti Pekerjaan" description="Foto ditampilkan sebagai pratinjau sebelum laporan dikirim.">
        <FileUpload files={foto} onChange={setFoto} error={errors['photos.0']} />
      </Card>
    </form>
  )
}
