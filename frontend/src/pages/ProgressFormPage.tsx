import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, Input, Select, Textarea } from '@/components/ui/Field'
import { FileUpload, type FotoTerpilih } from '@/components/ui/FileUpload'
import { ConfirmDialog } from '@/components/ui/Modal'
import { EmptyState, LoadingState } from '@/components/ui/State'
import { qk, useProjects, useWorkItems } from '@/hooks/queries'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { progressService, type ProgressPayload } from '@/services/progressService'
import { cn } from '@/utils/cn'
import { angka, hariIni } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Plus, Save, Send, Trash2 } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'

interface BarisDetail {
  work_item_id: number | ''
  volume_realisasi: number | ''
}

interface BarisKendala {
  work_item_id: number | ''
  jenis_kendala: string
  jenis_lainnya: string
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

const kendalaKosong = (workItemId: number | '' = ''): BarisKendala => ({
  work_item_id: workItemId,
  jenis_kendala: 'LAINNYA',
  jenis_lainnya: '',
  deskripsi: '',
  alasan_keterlambatan: '',
  tindak_lanjut: '',
})

/** Form input progres harian oleh QS. */
export function ProgressFormPage() {
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [params] = useSearchParams()
  const workItemAwal: number | '' = params.get('work_item_id') ? Number(params.get('work_item_id')) : ''

  const [projectIdDipilih, setProjectId] = useState<number | ''>(params.get('project_id') ? Number(params.get('project_id')) : '')
  const [tanggal, setTanggal] = useState(hariIni())
  const [lokasi, setLokasi] = useState('')
  const [keterangan, setKeterangan] = useState('')
  const [details, setDetails] = useState<BarisDetail[]>([{ work_item_id: workItemAwal, volume_realisasi: '' }])
  const [selesai, setSelesai] = useState<boolean | null>(null)
  const [issues, setIssues] = useState<BarisKendala[]>([])
  const [foto, setFoto] = useState<FotoTerpilih[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [tujuanKeluar, setTujuanKeluar] = useState<string | null>(null)

  const { data: projects, isLoading: memuatProyek } = useProjects({ per_page: 100 })
  const projectId: number | '' = projectIdDipilih !== '' ? projectIdDipilih : projects?.data.length === 1 ? projects.data[0].id : ''
  const { data: pekerjaan, isLoading: memuatPekerjaan } = useWorkItems(projectId ? Number(projectId) : null)

  useEffect(() => {
    if (!projectId || !projects) return

    const project = projects.data.find((item) => item.id === Number(projectId))

    if (project && !lokasi) {
      setLokasi(project.lokasi)
    }
  }, [projectId, projects, lokasi])

  const adaPerubahan =
    details.some((baris) => baris.volume_realisasi !== '') ||
    foto.length > 0 ||
    selesai !== null ||
    keterangan.trim() !== '' ||
    issues.some((baris) => baris.deskripsi.trim() !== '')

  /** Peringatkan pengguna saat menutup tab atau memuat ulang halaman selagi ada isian yang belum dikirim. */
  useEffect(() => {
    if (!adaPerubahan) return

    const konfirmasiUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault()
      event.returnValue = ''
    }

    window.addEventListener('beforeunload', konfirmasiUnload)

    return () => window.removeEventListener('beforeunload', konfirmasiUnload)
  }, [adaPerubahan])

  /** Cegat navigasi dalam aplikasi (menu, sidebar, tautan Kembali) selagi ada isian yang belum dikirim. */
  useEffect(() => {
    if (!adaPerubahan) return

    const cegatNavigasi = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

      const tautan = (event.target as HTMLElement)?.closest('a')

      if (!tautan || tautan.target === '_blank') return

      const href = tautan.getAttribute('href')

      if (!href || href.startsWith('#') || href.startsWith('http')) return

      event.preventDefault()
      setTujuanKeluar(href)
    }

    document.addEventListener('click', cegatNavigasi, true)

    return () => document.removeEventListener('click', cegatNavigasi, true)
  }, [adaPerubahan])

  const simpan = useMutation({
    mutationFn: (payload: ProgressPayload) => progressService.create(payload),
    onSuccess: async (laporan) => {
      toast.sukses(laporan.status === 'DIKIRIM' ? 'Progres berhasil dikirim.' : 'Progres tersimpan sebagai draf.')
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
    if (!tanggal) validasi.tanggal_laporan = 'Tanggal progres wajib diisi.'

    const detailValid = details.filter((baris) => baris.work_item_id !== '' && baris.volume_realisasi !== '')

    if (detailValid.length === 0) validasi.details = 'Minimal satu pekerjaan beserta volume realisasi wajib diisi.'

    const duplikat = new Set(detailValid.map((baris) => baris.work_item_id)).size !== detailValid.length

    if (duplikat) validasi.details = 'Pekerjaan yang sama tidak boleh diinput dua kali dalam satu progres.'

    if (details.some((baris) => baris.work_item_id !== '' && baris.volume_realisasi === '')) {
      validasi.details = 'Volume realisasi wajib diisi untuk setiap pekerjaan yang dipilih.'
    }

    if (status === 'DIKIRIM') {
      if (!lokasi.trim()) validasi.lokasi = 'Lokasi wajib diisi.'
      if (!keterangan.trim()) validasi.keterangan = 'Keterangan pekerjaan wajib diisi.'
      if (foto.length === 0) validasi.photos = 'Minimal satu foto bukti pekerjaan wajib diunggah.'
    }

    const kendalaTerisi =
      selesai === false
        ? issues
            .filter((baris) => baris.deskripsi.trim())
            .map((baris) =>
              baris.jenis_kendala === 'LAINNYA' && baris.jenis_lainnya.trim()
                ? { ...baris, deskripsi: `${baris.jenis_lainnya.trim()}: ${baris.deskripsi}` }
                : baris,
            )
        : []

    if (status === 'DIKIRIM') {
      if (selesai === null) {
        validasi.selesai = 'Pilih apakah pekerjaan sudah selesai.'
      } else if (!selesai) {
        const lengkap =
          issues.length > 0 &&
          issues.every(
            (baris) =>
              baris.work_item_id !== '' &&
              baris.deskripsi.trim() &&
              baris.alasan_keterlambatan.trim() &&
              baris.tindak_lanjut.trim() &&
              (baris.jenis_kendala !== 'LAINNYA' || baris.jenis_lainnya.trim()),
          )

        if (!lengkap) {
          validasi.issues = issues.some((baris) => baris.work_item_id === '')
            ? 'Pilih pekerjaan terkait untuk setiap kendala.'
            : issues.some((baris) => baris.jenis_kendala === 'LAINNYA' && !baris.jenis_lainnya.trim())
              ? 'Sebutkan jenis kendala untuk pilihan "Lainnya".'
              : 'Pekerjaan belum selesai: isi kendala, alasan keterlambatan, dan rencana tindak lanjut.'
        }
      }
    }

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) {
      toast.gagal(Object.values(validasi)[0])

      return
    }

    simpan.mutate({
      project_id: Number(projectId),
      tanggal_laporan: tanggal,
      lokasi: lokasi || null,
      keterangan: keterangan || null,
      status,
      details: detailValid.map((baris) => ({
        work_item_id: Number(baris.work_item_id),
        volume_realisasi: Number(baris.volume_realisasi),
      })),
      issues: kendalaTerisi.map((baris) => ({
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
    <>
    <form className="flex flex-col gap-4" onSubmit={(event) => kirim(event, 'DIKIRIM')} noValidate>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/progres" className="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink">
            <ArrowLeft className="size-4" aria-hidden />
            Kembali
          </Link>
          <h2 className="mt-1 text-lg font-semibold text-ink">Tambah Progres Harian</h2>
        </div>
      </div>

      <Card title="Informasi Progres">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="Proyek"
            required
            value={projectId}
            onChange={(event) => {
              setProjectId(event.target.value ? Number(event.target.value) : '')
              setDetails([{ work_item_id: '', volume_realisasi: '' }])
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
          <DatePicker label="Tanggal" required value={tanggal} onChange={(event) => setTanggal(event.target.value)} error={errors.tanggal_laporan} />
          <Input
            label="Lokasi"
            required
            wrapClassName="sm:col-span-2 lg:col-span-2"
            value={lokasi}
            onChange={(event) => setLokasi(event.target.value)}
            error={errors.lokasi}
            placeholder="Lokasi pekerjaan"
          />
          <Textarea
            label="Keterangan Pekerjaan"
            required
            wrapClassName="sm:col-span-2 lg:col-span-4"
            value={keterangan}
            onChange={(event) => setKeterangan(event.target.value)}
            error={errors.keterangan}
            placeholder="Uraian singkat pelaksanaan pekerjaan hari ini"
          />
        </div>
      </Card>

      <Card
        title="Progres Pekerjaan"
        description="Pilih pekerjaan dan masukkan volume yang terealisasi pada tanggal ini."
        action={
          <Button
            variant="outline"
            size="sm"
            icon={<Plus className="size-4" />}
            onClick={() => setDetails((current) => [...current, { work_item_id: '', volume_realisasi: '' }])}
          >
            Tambah Baris
          </Button>
        }
      >
        {!projectId ? (
          <EmptyState judul="Pilih proyek terlebih dahulu" pesan="Pilih proyek pada bagian Informasi Progres di atas untuk menampilkan daftar pekerjaan." />
        ) : memuatPekerjaan ? (
          <LoadingState pesan="Memuat daftar pekerjaan..." />
        ) : daftarPekerjaan.length === 0 ? (
          <EmptyState judul="Belum ada data pekerjaan" pesan="Proyek ini belum memiliki data pekerjaan. Hubungi Admin untuk menambahkannya." />
        ) : (
          <ul className="flex flex-col gap-3">
            {details.map((baris, index) => {
              const item = daftarPekerjaan.find((pekerjaanItem) => pekerjaanItem.id === Number(baris.work_item_id))
              const sisa = item ? item.volume - (item.volume_realisasi ?? 0) : null

              return (
                <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                  <Select
                    label="Pekerjaan"
                    required
                    wrapClassName="sm:col-span-7"
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
                    required
                    wrapClassName="sm:col-span-4"
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
        title={
          <>
            Foto Bukti Pekerjaan<span className="ml-0.5 text-primary">*</span>
          </>
        }
        description="Unggah minimal satu foto. Foto ditampilkan sebagai pratinjau sebelum progres dikirim."
      >
        <FileUpload files={foto} onChange={setFoto} error={errors.photos ?? errors['photos.0']} />
      </Card>

      <Card title="Apakah pekerjaan sudah selesai?" description="Jika belum selesai, isi kendala, alasan keterlambatan, dan rencana tindak lanjut.">
        <div className="grid grid-cols-2 gap-2 sm:max-w-sm" role="radiogroup" aria-label="Status pekerjaan">
          {(
            [
              [true, 'Selesai'],
              [false, 'Belum Selesai'],
            ] as const
          ).map(([nilai, label]) => (
            <button
              key={label}
              type="button"
              role="radio"
              aria-checked={selesai === nilai}
              onClick={() => {
                setSelesai(nilai)
                if (!nilai && issues.length === 0) setIssues([kendalaKosong(details[0]?.work_item_id ?? '')])
              }}
              className={cn(
                'rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors',
                selesai === nilai
                  ? nilai
                    ? 'border-success bg-success text-white'
                    : 'border-warning bg-warning text-white'
                  : 'border-line text-ink hover:bg-surface',
              )}
            >
              {label}
            </button>
          ))}
        </div>
        {errors.selesai && <p className="mt-2 text-[11px] font-medium text-danger">{errors.selesai}</p>}

        {selesai === false && (
          <div className="mt-4 flex flex-col gap-3">
            <ul className="flex flex-col gap-3">
              {issues.map((baris, index) => (
                <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-2">
                  <Select
                    label="Pekerjaan Terkait"
                    required
                    value={baris.work_item_id}
                    onChange={(event) =>
                      setIssues((current) =>
                        current.map((row, i) => (i === index ? { ...row, work_item_id: event.target.value ? Number(event.target.value) : '' } : row)),
                      )
                    }
                  >
                    <option value="" disabled>
                      Pilih pekerjaan
                    </option>
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
                  {baris.jenis_kendala === 'LAINNYA' && (
                    <Input
                      label="Sebutkan Jenis Kendala"
                      required
                      value={baris.jenis_lainnya}
                      onChange={(event) =>
                        setIssues((current) => current.map((row, i) => (i === index ? { ...row, jenis_lainnya: event.target.value } : row)))
                      }
                      placeholder="Contoh: Perizinan, Akses Lokasi"
                    />
                  )}
                  <Textarea
                    label="Kendala"
                    required
                    wrapClassName={baris.jenis_kendala === 'LAINNYA' ? 'sm:col-span-2' : undefined}
                    value={baris.deskripsi}
                    onChange={(event) => setIssues((current) => current.map((row, i) => (i === index ? { ...row, deskripsi: event.target.value } : row)))}
                  />
                  <Textarea
                    label="Alasan Keterlambatan"
                    required
                    value={baris.alasan_keterlambatan}
                    onChange={(event) =>
                      setIssues((current) => current.map((row, i) => (i === index ? { ...row, alasan_keterlambatan: event.target.value } : row)))
                    }
                  />
                  <Textarea
                    label="Rencana Tindak Lanjut"
                    required
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
            <Button
              variant="outline"
              size="sm"
              className="w-fit"
              icon={<Plus className="size-4" />}
              onClick={() => setIssues((current) => [...current, kendalaKosong(details[0]?.work_item_id ?? '')])}
            >
              Tambah Kendala
            </Button>
            {errors.issues && <p className="text-[11px] font-medium text-danger">{errors.issues}</p>}
          </div>
        )}
      </Card>

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variant="outline" icon={<Save className="size-4" />} loading={simpan.isPending} onClick={(event) => kirim(event, 'DRAFT')}>
          Simpan Draf
        </Button>
        <Button type="submit" icon={<Send className="size-4" />} loading={simpan.isPending}>
          Kirim Progres
        </Button>
      </div>
    </form>

    <ConfirmDialog
      open={tujuanKeluar !== null}
      title="Keluar dari form progres?"
      pesan="Isian yang sudah Anda masukkan belum dikirim dan akan hilang jika keluar sekarang."
      labelKonfirmasi="Ya, Keluar"
      onConfirm={() => {
        const tujuan = tujuanKeluar
        setTujuanKeluar(null)
        if (tujuan) navigate(tujuan)
      }}
      onClose={() => setTujuanKeluar(null)}
    />
    </>
  )
}
