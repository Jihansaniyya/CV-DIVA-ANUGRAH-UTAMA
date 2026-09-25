import { Button } from '@/components/ui/Button'
import { DatePicker, Input, Select, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { qk } from '@/hooks/queries'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { projectService, type ProjectPayload } from '@/services/projectService'
import { userService } from '@/services/userService'
import type { Project } from '@/types'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'

interface ProjectFormModalProps {
  open: boolean
  project: Project | null
  onClose: () => void
}

const KOSONG: ProjectPayload = {
  nama_proyek: '',
  nomor_spk: '',
  lokasi: '',
  sumber_dana: '',
  tahun_anggaran: new Date().getFullYear(),
  tanggal_spk: '',
  tanggal_mulai: '',
  tanggal_selesai: '',
  jangka_waktu_hari: null,
  kontraktor_pelaksana: 'CV. DIVA ANUGRAH UTAMA',
  konsultan_pengawas: '',
  nama_site_engineer: '',
  nama_pelaksana_lapangan: '',
  qs_user_id: null,
  status: 'BELUM_DIMULAI',
  keterangan: '',
}

export function ProjectFormModal({ open, project, onClose }: ProjectFormModalProps) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [form, setForm] = useState<ProjectPayload>(KOSONG)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const { data: daftarQs } = useQuery({
    queryKey: ['users', { role: 'QS', per_page: 100 }],
    queryFn: () => userService.list({ role: 'QS', per_page: 100 }),
    enabled: open,
  })

  useEffect(() => {
    if (!open) return

    setErrors({})
    setForm(
      project
        ? {
            nama_proyek: project.nama_proyek,
            nomor_spk: project.nomor_spk ?? '',
            lokasi: project.lokasi,
            sumber_dana: project.sumber_dana ?? '',
            tahun_anggaran: project.tahun_anggaran ? Number(project.tahun_anggaran) : null,
            tanggal_spk: project.tanggal_spk ?? '',
            tanggal_mulai: project.tanggal_mulai,
            tanggal_selesai: project.tanggal_selesai,
            jangka_waktu_hari: project.jangka_waktu_hari,
            kontraktor_pelaksana: project.kontraktor_pelaksana ?? '',
            konsultan_pengawas: project.konsultan_pengawas ?? '',
            nama_site_engineer: project.nama_site_engineer ?? '',
            nama_pelaksana_lapangan: project.nama_pelaksana_lapangan ?? '',
            qs_user_id: project.qs_user_id,
            status: project.status,
            keterangan: project.keterangan ?? '',
          }
        : KOSONG,
    )
  }, [open, project])

  const simpan = useMutation({
    mutationFn: (payload: ProjectPayload) =>
      project ? projectService.update(project.id, payload) : projectService.create(payload),
    onSuccess: async () => {
      toast.sukses(project ? 'Proyek berhasil diperbarui.' : 'Proyek berhasil ditambahkan.')
      await queryClient.invalidateQueries({ queryKey: ['projects'] })
      await queryClient.invalidateQueries({ queryKey: qk.dashboard })

      if (project) {
        // Perubahan durasi menyinkronkan periode; seluruh modul yang memakai periode dimuat ulang.
        await queryClient.invalidateQueries({ queryKey: qk.project(project.id) })
        await queryClient.invalidateQueries({ queryKey: ['progress'] })
        await queryClient.invalidateQueries({ queryKey: ['report'] })
      }

      onClose()
    },
    onError: (error) => {
      const validasi = errorValidasi(error)
      setErrors(validasi)

      if (Object.keys(validasi).length === 0) {
        toast.gagal(pesanError(error))
      } else {
        toast.gagal('Periksa kembali data yang kamu masukkan.')
      }
    },
  })

  const ubah = (field: keyof ProjectPayload, nilai: string | number | null) => {
    setForm((current) => ({ ...current, [field]: nilai }))
  }

  const kirim = (event: FormEvent) => {
    event.preventDefault()
    const validasiLokal: Record<string, string> = {}

    if (!form.nama_proyek.trim()) validasiLokal.nama_proyek = 'Nama proyek wajib diisi.'
    if (!form.lokasi.trim()) validasiLokal.lokasi = 'Lokasi proyek wajib diisi.'
    if (!form.kontraktor_pelaksana?.trim()) validasiLokal.kontraktor_pelaksana = 'Kontraktor pelaksana wajib diisi.'
    if (!form.konsultan_pengawas?.trim()) validasiLokal.konsultan_pengawas = 'Konsultan pengawas wajib diisi.'
    if (!form.nama_site_engineer?.trim()) validasiLokal.nama_site_engineer = 'Nama site engineer wajib diisi.'
    if (!form.tanggal_mulai) validasiLokal.tanggal_mulai = 'Tanggal mulai wajib diisi.'
    if (!form.tanggal_selesai) validasiLokal.tanggal_selesai = 'Tanggal selesai wajib diisi.'
    if (form.tanggal_mulai && form.tanggal_selesai && form.tanggal_selesai < form.tanggal_mulai) {
      validasiLokal.tanggal_selesai = 'Tanggal selesai harus setelah tanggal mulai.'
    }

    setErrors(validasiLokal)

    if (Object.keys(validasiLokal).length > 0) return

    simpan.mutate({
      ...form,
      qs_user_id: form.qs_user_id ? Number(form.qs_user_id) : null,
      tahun_anggaran: form.tahun_anggaran ? Number(form.tahun_anggaran) : null,
      jangka_waktu_hari: form.jangka_waktu_hari ? Number(form.jangka_waktu_hari) : null,
    })
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={project ? 'Ubah Proyek' : 'Tambah Proyek'}
      description="Periode pelaksanaan mingguan dibentuk otomatis dari tanggal mulai dan selesai."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={simpan.isPending}>
            Batal
          </Button>
          <Button form="form-proyek" type="submit" loading={simpan.isPending}>
            Simpan
          </Button>
        </>
      }
    >
      <form id="form-proyek" className="grid gap-4 sm:grid-cols-2" onSubmit={kirim} noValidate>
        <Input
          label="Nama Proyek"
          required
          wrapClassName="sm:col-span-2"
          value={form.nama_proyek}
          onChange={(event) => ubah('nama_proyek', event.target.value)}
          error={errors.nama_proyek}
          placeholder="Contoh: Belanja Modal Jalan Kota (Pembuatan Penutup Parit)"
        />
        <Input
          label="Nomor SPK"
          value={form.nomor_spk ?? ''}
          onChange={(event) => ubah('nomor_spk', event.target.value)}
          error={errors.nomor_spk}
          placeholder="Contoh: 000.3.2/98.1/SPK/Penutup Parit RT. 09-Kel.Boba/2025"
        />
        <Input
          label="Lokasi Proyek"
          required
          value={form.lokasi}
          onChange={(event) => ubah('lokasi', event.target.value)}
          error={errors.lokasi}
          placeholder="Contoh: RT. 09 Kel. Bontang Baru, Bontang Utara"
        />
        <Input label="Sumber Dana" value={form.sumber_dana ?? ''} onChange={(event) => ubah('sumber_dana', event.target.value)} error={errors.sumber_dana} placeholder="Contoh: PAD Kota Bontang" />
        <Input
          label="Tahun Anggaran"
          type="number"
          min={2000}
          max={2100}
          value={form.tahun_anggaran ?? ''}
          onChange={(event) => ubah('tahun_anggaran', event.target.value ? Number(event.target.value) : null)}
          error={errors.tahun_anggaran}
        />
        <DatePicker label="Tanggal SPK" value={form.tanggal_spk ?? ''} onChange={(event) => ubah('tanggal_spk', event.target.value)} error={errors.tanggal_spk} />
        <DatePicker label="Tanggal Mulai" required value={form.tanggal_mulai} onChange={(event) => ubah('tanggal_mulai', event.target.value)} error={errors.tanggal_mulai} />
        <DatePicker label="Tanggal Selesai" required value={form.tanggal_selesai} onChange={(event) => ubah('tanggal_selesai', event.target.value)} error={errors.tanggal_selesai} />
        <Input
          label="Jangka Waktu (hari kalender)"
          type="number"
          min={1}
          hint="Sesuai SPK. Kosongkan untuk dihitung dari tanggal pelaksanaan."
          value={form.jangka_waktu_hari ?? ''}
          onChange={(event) => ubah('jangka_waktu_hari', event.target.value ? Number(event.target.value) : null)}
          error={errors.jangka_waktu_hari}
        />
        <Select
          label="QS Penanggung Jawab"
          value={form.qs_user_id ?? ''}
          onChange={(event) => ubah('qs_user_id', event.target.value ? Number(event.target.value) : null)}
          error={errors.qs_user_id}
        >
          <option value="">Belum ditentukan</option>
          {daftarQs?.data.map((qs) => (
            <option key={qs.id} value={qs.id}>
              {qs.name}
            </option>
          ))}
        </Select>
        <Select label="Status Proyek" value={form.status ?? 'BELUM_DIMULAI'} onChange={(event) => ubah('status', event.target.value)} error={errors.status}>
          <option value="BELUM_DIMULAI">Belum Dimulai</option>
          <option value="BERJALAN">Berjalan</option>
          <option value="SELESAI">Selesai</option>
          <option value="TERLAMBAT">Terlambat</option>
        </Select>
        <Input
          label="Kontraktor Pelaksana"
          required
          value={form.kontraktor_pelaksana ?? ''}
          onChange={(event) => ubah('kontraktor_pelaksana', event.target.value)}
          error={errors.kontraktor_pelaksana}
          placeholder="Contoh: CV. DIVA ANUGRAH UTAMA"
        />
        <Input
          label="Konsultan Pengawas"
          required
          value={form.konsultan_pengawas ?? ''}
          onChange={(event) => ubah('konsultan_pengawas', event.target.value)}
          error={errors.konsultan_pengawas}
          placeholder="Contoh: CV. AKMAL BERKAH ABADI"
        />
        <Input
          label="Nama Site Engineer"
          required
          value={form.nama_site_engineer ?? ''}
          onChange={(event) => ubah('nama_site_engineer', event.target.value)}
          error={errors.nama_site_engineer}
          hint="Dipakai pada tanda tangan laporan."
          placeholder="Contoh: Abdul Muiz, ST"
        />
        <Input label="Nama Pelaksana Lapangan" value={form.nama_pelaksana_lapangan ?? ''} onChange={(event) => ubah('nama_pelaksana_lapangan', event.target.value)} error={errors.nama_pelaksana_lapangan} />
        <Textarea label="Keterangan" wrapClassName="sm:col-span-2" value={form.keterangan ?? ''} onChange={(event) => ubah('keterangan', event.target.value)} error={errors.keterangan} />
      </form>
    </Modal>
  )
}
