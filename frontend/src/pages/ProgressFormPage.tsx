import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { DatePicker, FieldWrap, Input, Select, Textarea } from '@/components/ui/Field'
import { FileUpload, type FotoTerpilih } from '@/components/ui/FileUpload'
import { AlertDialog, ConfirmDialog } from '@/components/ui/Modal'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { Dropdown } from '@/components/ui/Dropdown'
import { invalidasiSetelahProgres, useProgress, useProjectOptions, useUploadLimits, useWorkItems } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError, statusTidakPasti } from '@/lib/api'
import { progressService, type ProgressPayload } from '@/services/progressService'
import { cn } from '@/utils/cn'
import { angka, hariIni, persen, rentangTanggal, tanggal as formatTanggal } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Plus, Save, Send, Trash2 } from 'lucide-react'
import type { ProgressReport } from '@/types'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom'

interface BarisDetail {
  work_item_id: number | ''
  volume_realisasi: number | ''
}

interface BarisKendala {
  /** Pekerjaan yang belum selesai, dipilih dari baris Progres Pekerjaan. */
  work_item_ids: number[]
  jenis_kendala: string
  jenis_lainnya: string
  /** Kendala sekaligus alasan keterlambatan (satu isian). */
  deskripsi: string
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

const kendalaKosong = (workItemIds: number[] = []): BarisKendala => ({
  work_item_ids: workItemIds,
  jenis_kendala: '',
  jenis_lainnya: '',
  deskripsi: '',
  tindak_lanjut: '',
})

/** Buang pesan error milik blok kendala agar tidak menempel pada blok yang salah setelah daftar berubah. */
const tanpaErrorKendala = (errors: Record<string, string>) =>
  Object.fromEntries(Object.entries(errors).filter(([key]) => key !== 'selesai' && !key.startsWith('issues')))

/** Isian form yang dibandingkan untuk mendeteksi perubahan draf yang belum disimpan. */
interface IsianForm {
  tanggal: string
  lokasi: string
  keterangan: string
  details: BarisDetail[]
  selesai: boolean | null
  issues: BarisKendala[]
}

const ringkasIsian = (isian: IsianForm) => JSON.stringify(isian)

/** Tujuan khusus dialog keluar: tombol Kembali browser. */
const KEMBALI = '__kembali__'

const adaPenjagaRiwayat = () => Boolean((window.history.state as { penjagaForm?: boolean } | null)?.penjagaForm)

const pasangPenjagaRiwayat = () => window.history.pushState({ ...(window.history.state ?? {}), penjagaForm: true }, '', window.location.href)

/** Identitas unik isian form; dipakai backend untuk menolak pengiriman ganda dari permintaan yang sama. */
function buatIdPermintaan(): string {
  // randomUUID hanya tersedia pada konteks aman (HTTPS atau localhost).
  if (window.isSecureContext) return crypto.randomUUID()

  // Cadangan untuk koneksi non-HTTPS (mis. diakses lewat IP jaringan lokal) yang tidak menyediakan randomUUID.
  const b = crypto.getRandomValues(new Uint8Array(16))
  b[6] = (b[6] & 0x0f) | 0x40
  b[8] = (b[8] & 0x3f) | 0x80
  const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('')

  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
}

/** Kendala tersimpan per pekerjaan; jenis "Lainnya" menyimpan sebutannya di awal deskripsi ("Perizinan: ..."). */
function kendalaDariLaporan(laporan: ProgressReport): BarisKendala[] {
  return (laporan.kendala ?? []).map((item) => {
    const lainnya = item.jenis_kendala === 'LAINNYA' ? /^([^:]{1,60}):\s([\s\S]+)$/.exec(item.deskripsi) : null

    return {
      work_item_ids: item.work_item_id ? [item.work_item_id] : [],
      jenis_kendala: item.jenis_kendala ?? '',
      jenis_lainnya: lainnya ? lainnya[1] : '',
      deskripsi: lainnya ? lainnya[2] : item.deskripsi,
      tindak_lanjut: item.tindak_lanjut ?? '',
    }
  })
}

/** Form input progres harian oleh QS, juga dipakai untuk membuka dan mengubah draf (/progres/:id/ubah). */
export function ProgressFormPage() {
  const { id: idParam } = useParams<{ id: string }>()
  const editId = idParam ? Number(idParam) : null
  const { user } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [params] = useSearchParams()
  const workItemAwal: number | '' = params.get('work_item_id') ? Number(params.get('work_item_id')) : ''

  const [projectIdDipilih, setProjectId] = useState<number | ''>(params.get('project_id') ? Number(params.get('project_id')) : '')
  const [tanggalDipilih, setTanggal] = useState(hariIni())
  const [lokasi, setLokasi] = useState('')
  const [keterangan, setKeterangan] = useState('')
  const [details, setDetails] = useState<BarisDetail[]>([{ work_item_id: workItemAwal, volume_realisasi: '' }])
  const [selesai, setSelesai] = useState<boolean | null>(null)
  const [issues, setIssues] = useState<BarisKendala[]>([])
  const [foto, setFoto] = useState<FotoTerpilih[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [tujuanKeluar, setTujuanKeluar] = useState<string | null>(null)
  /** Isian yang sudah lolos validasi dan menunggu konfirmasi "Kirim Progres". */
  const [payloadKirim, setPayloadKirim] = useState<ProgressPayload | null>(null)
  const [idPermintaan] = useState(buatIdPermintaan)
  const sudahDiisi = useRef(false)
  const [isianAwal, setIsianAwal] = useState<string | null>(null)
  const [tersimpan, setTersimpan] = useState(false)
  /** Pengguna sudah memilih keluar (atau sudah tersimpan); penjaga tombol Kembali tidak lagi menahan. */
  const keluar = useRef(false)
  const perluKonfirmasiRef = useRef(false)
  /** Form dibuka dari tombol "Ubah Draf" di halaman detail; setelah simpan cukup kembali ke halaman itu. */
  const { state: stateLokasi } = useLocation() as { state: { dariDetail?: boolean } | null }

  const { data: laporanEdit, isLoading: memuatLaporan, error: errorLaporan } = useProgress(editId)
  const { data: limits } = useUploadLimits()
  const { data: projectList, isLoading: memuatProyek } = useProjectOptions()
  const projects = projectList ?? []
  const projectId: number | '' =
    laporanEdit?.project_id ?? (projectIdDipilih !== '' ? projectIdDipilih : projects.length === 1 ? projects[0].id : '')
  const { data: pekerjaan, isLoading: memuatPekerjaan } = useWorkItems(projectId ? Number(projectId) : null)
  const proyekDipilih = projects.find((item) => item.id === Number(projectId))
  const fotoTersimpan = laporanEdit?.foto ?? []
  const bolehDiubah = !editId || (laporanEdit?.status === 'DRAFT' && laporanEdit.user_id === user?.id)

  /** Isi form dari draf yang dibuka, sekali saja agar isian yang sedang diubah tidak tertimpa saat data dimuat ulang. */
  useEffect(() => {
    if (!laporanEdit || sudahDiisi.current) return

    sudahDiisi.current = true
    const kendala = kendalaDariLaporan(laporanEdit)
    const awal: IsianForm = {
      tanggal: laporanEdit.tanggal_laporan.slice(0, 10),
      lokasi: laporanEdit.lokasi ?? '',
      keterangan: laporanEdit.keterangan ?? '',
      details:
        (laporanEdit.detail ?? []).length > 0
          ? (laporanEdit.detail ?? []).map((detail) => ({ work_item_id: detail.work_item_id, volume_realisasi: detail.volume_realisasi }))
          : [{ work_item_id: '', volume_realisasi: '' }],
      // Pilihan "Selesai" tidak disimpan; draf berkendala berarti "Belum Selesai", selebihnya dipilih ulang saat dikirim.
      selesai: kendala.length > 0 ? false : null,
      issues: kendala,
    }

    setTanggal(awal.tanggal)
    setLokasi(awal.lokasi)
    setKeterangan(awal.keterangan)
    setDetails(awal.details)
    setIssues(awal.issues)
    setSelesai(awal.selesai)
    setIsianAwal(ringkasIsian(awal))
  }, [laporanEdit])

  /** Volume draf yang sedang diubah tidak ikut mengurangi sisa volume pekerjaannya sendiri. */
  const volumeDrafIni = (workItemId: number) =>
    laporanEdit?.detail?.filter((detail) => detail.work_item_id === workItemId).reduce((total, detail) => total + detail.volume_realisasi, 0) ?? 0
  // Progres baru hanya dapat ditambahkan bila total bobot rencana proyek sudah 100% (backend ikut memvalidasi).
  const pesanRencanaBelumLengkap =
    proyekDipilih?.rencana_lengkap === false
      ? `Rencana pekerjaan proyek ini baru dijadwalkan ${persen(proyekDipilih.total_bobot_rencana ?? 0)} dari total bobot pekerjaan. Progres dapat ditambahkan setelah Admin melengkapi rencana hingga 100%.`
      : null
  // Tanggal progres dibatasi pada jadwal proyek agar laporan selalu masuk ke salah satu periode,
  // dan tidak melewati hari ini karena progres mencatat pekerjaan yang sudah dilaksanakan (backend ikut memvalidasi).
  const hari = hariIni()
  const tanggalMulai = proyekDipilih?.tanggal_mulai?.slice(0, 10)
  const jadwalSelesai = proyekDipilih?.tanggal_selesai?.slice(0, 10)
  const tanggalSelesai = jadwalSelesai && jadwalSelesai < hari ? jadwalSelesai : hari
  const proyekBelumDimulai = Boolean(tanggalMulai && tanggalMulai > hari)
  // Hanya ada satu tanggal yang sah (mis. proyek baru dimulai hari ini): tampilkan sebagai teks, bukan kalender.
  const tanggalTerkunci = Boolean(tanggalMulai && tanggalMulai >= tanggalSelesai)
  // Bawaan "hari ini" bisa di luar jadwal (proyek belum mulai / sudah lewat); geser ke batas jadwal terdekat.
  const tanggal =
    tanggalDipilih && tanggalMulai && tanggalDipilih < tanggalMulai
      ? tanggalMulai
      : tanggalDipilih && tanggalSelesai && tanggalDipilih > tanggalSelesai
        ? tanggalSelesai
        : tanggalDipilih

  useEffect(() => {
    if (!projectId || editId) return

    const project = projectList?.find((item) => item.id === Number(projectId))

    if (project && !lokasi) {
      setLokasi(project.lokasi)
    }
  }, [projectId, projectList, lokasi, editId])

  /** ID pekerjaan unik yang sudah dipilih pada bagian Progres Pekerjaan. */
  const idPekerjaanDipilih = (rows: BarisDetail[] = details) => [
    ...new Set(rows.flatMap((baris) => (baris.work_item_id === '' ? [] : [baris.work_item_id]))),
  ]

  /**
   * Ubah baris Progres Pekerjaan sekaligus membersihkan centang "Pekerjaan Terkait"
   * yang pekerjaannya sudah tidak ada lagi di baris mana pun.
   */
  const aturDetails = (next: BarisDetail[]) => {
    setDetails(next)
    const tersedia = new Set(idPekerjaanDipilih(next))
    setIssues((current) => current.map((baris) => ({ ...baris, work_item_ids: baris.work_item_ids.filter((id) => tersedia.has(id)) })))
  }

  const ubahKendala = (index: number, perubahan: Partial<BarisKendala>) =>
    setIssues((current) => current.map((row, i) => (i === index ? { ...row, ...perubahan } : row)))

  /**
   * Ada isian yang belum disimpan.
   * - Ubah draf: isian berbeda dari draf saat dibuka, atau ada foto baru. Foto tersimpan yang dihapus
   *   langsung terhapus di server, jadi bukan perubahan yang tertunda.
   * - Tambah progres: ada isian berarti (lokasi & tanggal bawaan yang terisi otomatis tidak dihitung).
   * Setelah berhasil disimpan, form tidak lagi dianggap berubah.
   */
  const adaPerubahan =
    !tersimpan &&
    (foto.length > 0 ||
      (editId
        ? isianAwal !== null && ringkasIsian({ tanggal: tanggalDipilih, lokasi, keterangan, details, selesai, issues }) !== isianAwal
        : details.some((baris) => baris.volume_realisasi !== '') ||
          selesai !== null ||
          keterangan.trim() !== '' ||
          issues.some((baris) => baris.deskripsi.trim() !== '')))

  /**
   * Keluar dari form perlu dikonfirmasi bila ada isian yang belum disimpan, dan selalu saat mengubah draf
   * agar QS tidak tanpa sengaja meninggalkan draf yang sedang dibuka.
   */
  const perluKonfirmasiKeluar = adaPerubahan || (Boolean(editId) && !tersimpan)

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

  // Dibaca oleh pendengar popstate yang dipasang sekali; selalu memuat nilai render terakhir.
  useEffect(() => {
    perluKonfirmasiRef.current = perluKonfirmasiKeluar
  }, [perluKonfirmasiKeluar])

  /**
   * Tombol Kembali browser. BrowserRouter tidak menyediakan pemblokir navigasi, jadi saat form dibuka
   * disisipkan satu entri riwayat penjaga dengan URL yang sama (sekali saja; setelah muat ulang entri
   * penjaga yang sama dipakai lagi). Menekan Kembali melepas entri itu:
   *   - tanpa perlu konfirmasi -> diteruskan satu langkah lagi, jadi cukup sekali tekan untuk keluar;
   *   - perlu konfirmasi       -> penjaga dipasang lagi dan dialog konfirmasi ditampilkan.
   */
  useEffect(() => {
    if (!adaPenjagaRiwayat()) pasangPenjagaRiwayat()

    const tombolKembali = () => {
      if (keluar.current) return

      if (perluKonfirmasiRef.current) {
        pasangPenjagaRiwayat()
        setTujuanKeluar(KEMBALI)

        return
      }

      keluar.current = true
      window.history.back()
    }

    window.addEventListener('popstate', tombolKembali)

    return () => window.removeEventListener('popstate', tombolKembali)
  }, [])

  /** Cegat navigasi dalam aplikasi (menu, sidebar, tautan Kembali) selagi keluar perlu dikonfirmasi. */
  useEffect(() => {
    if (!perluKonfirmasiKeluar) return

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
  }, [perluKonfirmasiKeluar])

  const simpan = useMutation({
    mutationFn: (payload: ProgressPayload) => (editId ? progressService.update(editId, payload) : progressService.create(payload)),
    onSuccess: async (laporan) => {
      toast.sukses(laporan.status === 'DIKIRIM' ? 'Progres berhasil dikirim.' : 'Progres tersimpan sebagai draf.')
      // Isian sudah tersimpan: matikan penjaga keluar sebelum berpindah halaman.
      keluar.current = true
      setTersimpan(true)
      await invalidasiSetelahProgres(queryClient, laporan.project_id)
      if (editId && stateLokasi?.dariDetail && adaPenjagaRiwayat()) {
        // Dibuka dari halaman detail: kembali ke entri riwayat detail itu (melewati penjaga dan form),
        // sehingga tombol Kembali berikutnya tidak membuka form yang sudah tersimpan.
        window.history.go(-2)
      } else {
        // Entri penjaga riwayat diganti halaman detail.
        navigate(`/progres/${laporan.id}`, { replace: adaPenjagaRiwayat() })
      }
    },
    onError: (error) => {
      if (statusTidakPasti(error)) {
        // Respons tidak diterima: server mungkin sudah menyimpan. Menekan simpan lagi aman karena isian ini
        // membawa identitas permintaan yang sama, sehingga tidak akan menjadi laporan ganda.
        toast.gagal(`${pesanError(error)} Status penyimpanan belum dapat dipastikan; menekan tombol simpan lagi tidak akan membuat laporan ganda.`)

        return
      }

      const validasi = errorValidasi(error)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? Object.values(validasi)[0] : pesanError(error))
    },
  })

  const hapusFoto = useMutation({
    mutationFn: (photoId: number) => progressService.removePhoto(photoId),
    onSuccess: async () => {
      toast.sukses('Foto berhasil dihapus.')
      await invalidasiSetelahProgres(queryClient, laporanEdit?.project_id)
    },
    onError: (error) => toast.gagal(pesanError(error)),
  })

  const kirim = (event: FormEvent, status: 'DRAFT' | 'DIKIRIM') => {
    event.preventDefault()
    const validasi: Record<string, string> = {}

    if (!projectId) validasi.project_id = 'Proyek wajib dipilih.'
    else if (pesanRencanaBelumLengkap) validasi.project_id = pesanRencanaBelumLengkap
    if (!tanggal) {
      validasi.tanggal_laporan = 'Tanggal progres wajib diisi.'
    } else if (proyekBelumDimulai && tanggalMulai) {
      validasi.tanggal_laporan = `Proyek baru dimulai ${formatTanggal(tanggalMulai)}. Progres dapat diisi mulai tanggal tersebut.`
    } else if (tanggal > hari) {
      validasi.tanggal_laporan = 'Tanggal progres tidak boleh melewati hari ini.'
    } else if (tanggalMulai && jadwalSelesai && (tanggal < tanggalMulai || tanggal > jadwalSelesai)) {
      validasi.tanggal_laporan = `Tanggal progres berada di luar jadwal proyek (${rentangTanggal(tanggalMulai, jadwalSelesai)}).`
    }

    const detailValid = details.filter((baris) => baris.work_item_id !== '' && baris.volume_realisasi !== '')

    if (detailValid.length === 0) validasi.details = 'Minimal satu pekerjaan beserta volume realisasi wajib diisi.'

    const duplikat = new Set(detailValid.map((baris) => baris.work_item_id)).size !== detailValid.length

    if (duplikat) validasi.details = 'Pekerjaan yang sama tidak boleh diinput dua kali dalam satu progres.'

    if (details.some((baris) => baris.work_item_id !== '' && baris.volume_realisasi === '')) {
      validasi.details = 'Volume realisasi wajib diisi untuk setiap pekerjaan yang dipilih.'
    }

    if (status === 'DIKIRIM') {
      if (!lokasi.trim()) validasi.lokasi = 'Lokasi wajib diisi.'
      if (!keterangan.trim()) validasi.keterangan = 'Keterangan kegiatan hari ini wajib diisi.'
      if (foto.length + fotoTersimpan.length === 0) validasi.photos = 'Minimal satu foto bukti pekerjaan wajib diunggah.'
    }

    // Kendala hanya relevan saat "Belum Selesai"; saat "Selesai" tidak divalidasi maupun dikirim.
    const kendalaTerisi = selesai === false ? issues.filter((baris) => baris.deskripsi.trim()) : []

    if (status === 'DIKIRIM') {
      if (selesai === null) {
        validasi.selesai = 'Pilih apakah pekerjaan sudah selesai.'
      } else if (!selesai) {
        issues.forEach((baris, index) => {
          const key = `issues.${index}`

          if (baris.work_item_ids.length === 0) validasi[`${key}.work_item_ids`] = 'Centang minimal satu pekerjaan yang belum selesai.'

          if (!baris.jenis_kendala) {
            validasi[`${key}.jenis_kendala`] = 'Jenis kendala wajib dipilih.'
          } else if (baris.jenis_kendala === 'LAINNYA' && !baris.jenis_lainnya.trim()) {
            validasi[`${key}.jenis_lainnya`] = 'Sebutkan jenis kendala untuk pilihan "Lainnya".'
          }

          if (!baris.deskripsi.trim()) validasi[`${key}.deskripsi`] = 'Kendala & alasan keterlambatan wajib diisi.'
          if (!baris.tindak_lanjut.trim()) validasi[`${key}.tindak_lanjut`] = 'Rencana tindak lanjut wajib diisi.'
        })
      }
    }

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) {
      toast.gagal(Object.values(validasi)[0])

      return
    }

    const payload: ProgressPayload = {
      client_request_id: editId ? undefined : idPermintaan,
      project_id: Number(projectId),
      tanggal_laporan: tanggal,
      lokasi: lokasi || null,
      keterangan: keterangan || null,
      status,
      details: detailValid.map((baris) => ({
        work_item_id: Number(baris.work_item_id),
        volume_realisasi: Number(baris.volume_realisasi),
      })),
      // Satu blok kendala dipecah menjadi satu baris kendala per pekerjaan yang dicentang.
      issues: kendalaTerisi.flatMap((baris) => {
        const deskripsi =
          baris.jenis_kendala === 'LAINNYA' && baris.jenis_lainnya.trim()
            ? `${baris.jenis_lainnya.trim()}: ${baris.deskripsi.trim()}`
            : baris.deskripsi.trim()
        const workItemIds: (number | null)[] = baris.work_item_ids.length > 0 ? baris.work_item_ids : [null]

        return workItemIds.map((workItemId) => ({
          work_item_id: workItemId,
          jenis_kendala: baris.jenis_kendala || null,
          deskripsi,
          tindak_lanjut: baris.tindak_lanjut.trim() || null,
        }))
      }),
      photos: foto.map((item) => item.file),
      photo_captions: foto.map((item) => item.caption),
    }

    // Laporan terkirim tidak dapat diubah lagi, jadi pengiriman dikonfirmasi terlebih dahulu.
    if (status === 'DIKIRIM') setPayloadKirim(payload)
    else simpan.mutate(payload)
  }

  if (memuatProyek || (editId && memuatLaporan)) return <LoadingState />

  if (editId && (errorLaporan || !laporanEdit)) {
    return <ErrorState pesan={errorLaporan ? pesanError(errorLaporan) : 'Draf progres tidak ditemukan.'} />
  }

  if (!bolehDiubah) {
    return (
      <EmptyState
        judul="Laporan tidak dapat diubah"
        pesan="Hanya draf milik Anda yang dapat diubah. Laporan yang sudah dikirim tidak dapat diubah lagi."
        aksi={
          <Link to={`/progres/${editId}`} className="text-sm font-medium text-primary hover:underline">
            Lihat laporan
          </Link>
        }
      />
    )
  }

  if (projects.length === 0) {
    return <EmptyState judul="Belum ada proyek yang ditugaskan" pesan="Hubungi Admin untuk penugasan proyek." />
  }

  const daftarPekerjaan = pekerjaan?.data ?? []
  const idDipilih = idPekerjaanDipilih()
  const pekerjaanDipilih = daftarPekerjaan.filter((item) => idDipilih.includes(item.id))

  return (
    <>
    <form className="flex flex-col gap-4" onSubmit={(event) => kirim(event, 'DIKIRIM')} noValidate>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link
            to={editId ? `/progres/${editId}` : '/progres'}
            className="inline-flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink"
          >
            <ArrowLeft className="size-4" aria-hidden />
            Kembali
          </Link>
          <h2 className="mt-1 text-lg font-semibold text-ink">{editId ? 'Ubah Draf Progres' : 'Tambah Progres Harian'}</h2>
        </div>
      </div>

      <Card title="Informasi Progres">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Dropdown
            label="Proyek"
            required
            value={projectId === '' ? '' : String(projectId)}
            disabled={Boolean(editId)}
            options={projects.map((project) => ({
              value: String(project.id),
              label: project.rencana_lengkap === false ? `${project.nama_proyek} (rencana belum 100%)` : project.nama_proyek,
            }))}
            onChange={(nilai) => {
              setProjectId(nilai ? Number(nilai) : '')
              aturDetails([{ work_item_id: '', volume_realisasi: '' }])
            }}
            placeholder="Pilih proyek"
            error={errors.project_id}
            wrapClassName="sm:col-span-2 lg:col-span-3"
          />
          {tanggalTerkunci ? (
            <Input
              label="Tanggal"
              required
              readOnly
              value={tanggal ? formatTanggal(tanggal) : ''}
              className="bg-surface text-muted"
              error={errors.tanggal_laporan}
              hint={jadwalSelesai && tanggalMulai ? `Jadwal proyek: ${rentangTanggal(tanggalMulai, jadwalSelesai)}.` : undefined}
            />
          ) : (
            <DatePicker
              label="Tanggal"
              required
              value={tanggal}
              min={tanggalMulai}
              max={tanggalSelesai}
              onChange={(event) => setTanggal(event.target.value)}
              error={errors.tanggal_laporan}
              hint={tanggalMulai && jadwalSelesai ? `Jadwal proyek: ${rentangTanggal(tanggalMulai, jadwalSelesai)}; tidak boleh melewati hari ini.` : undefined}
            />
          )}
          <Input
            label="Lokasi"
            required
            wrapClassName="sm:col-span-2 lg:col-span-4"
            value={lokasi}
            onChange={(event) => setLokasi(event.target.value)}
            error={errors.lokasi}
            placeholder="Lokasi pekerjaan"
          />
          <Textarea
            label="Keterangan Kegiatan Hari Ini"
            required
            wrapClassName="sm:col-span-2 lg:col-span-4"
            value={keterangan}
            onChange={(event) => setKeterangan(event.target.value)}
            error={errors.keterangan}
            placeholder="Contoh: Pengecoran kolom lantai 2 area timur, 8 tukang dan 1 molen, dimulai pukul 08.00."
            
          />
        </div>
      </Card>

      <Card
        title="Progres Pekerjaan"
        description="Data volume terukur: pilih item pekerjaan dan isi volume yang terealisasi pada tanggal ini sesuai satuannya."
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
        ) : pesanRencanaBelumLengkap ? (
          <EmptyState judul="Rencana pekerjaan belum lengkap" pesan={pesanRencanaBelumLengkap} />
        ) : memuatPekerjaan ? (
          <LoadingState pesan="Memuat daftar pekerjaan..." />
        ) : daftarPekerjaan.length === 0 ? (
          <EmptyState judul="Belum ada data pekerjaan" pesan="Proyek ini belum memiliki data pekerjaan. Hubungi Admin untuk menambahkannya." />
        ) : (
          <ul className="flex flex-col gap-3">
            {details.map((baris, index) => {
              const item = daftarPekerjaan.find((pekerjaanItem) => pekerjaanItem.id === Number(baris.work_item_id))
              // Sisa dari backend: volume - (terkirim + draf lain), sama dengan batas yang divalidasi saat menyimpan.
              const sisa = item ? (item.sisa_volume ?? item.volume - (item.volume_realisasi ?? 0)) + volumeDrafIni(item.id) : null

              return (
                <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                  <Select
                    label="Pekerjaan"
                    required
                    wrapClassName="sm:col-span-7"
                    value={baris.work_item_id}
                    onChange={(event) =>
                      aturDetails(details.map((row, i) => (i === index ? { ...row, work_item_id: event.target.value ? Number(event.target.value) : '' } : row)))
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
                    placeholder="0"
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
                      onClick={() => aturDetails(details.filter((_, i) => i !== index))}
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
        {fotoTersimpan.length > 0 && (
          <div className="mb-4">
            <p className="mb-2 text-xs font-medium text-muted">Foto tersimpan pada draf ini</p>
            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {fotoTersimpan.map((item) => (
                <li key={item.id} className="app-card overflow-hidden">
                  <img src={item.url} alt={item.caption || 'Foto bukti pekerjaan'} className="h-28 w-full object-cover" loading="lazy" />
                  <div className="space-y-2 p-2">
                    <p className="truncate text-[11px] text-muted">{item.caption || item.original_name || 'Tanpa keterangan'}</p>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="w-full text-danger"
                      icon={<Trash2 className="size-3.5" />}
                      loading={hapusFoto.isPending && hapusFoto.variables === item.id}
                      onClick={() => hapusFoto.mutate(item.id)}
                    >
                      Hapus
                    </Button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}
        <FileUpload files={foto} onChange={setFoto} limits={limits} error={errors.photos ?? errors['photos.0']} />
      </Card>

      <Card
        title="Apakah pekerjaan sudah selesai?"
        description="Jika belum selesai, centang pekerjaan yang terkendala lalu isi kendala & alasan keterlambatan serta rencana tindak lanjut."
      >
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
                setErrors(tanpaErrorKendala)

                if (nilai) {
                  setIssues([])
                } else if (issues.length === 0) {
                  setIssues([kendalaKosong(idDipilih.length === 1 ? idDipilih : [])])
                }
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
              {issues.map((baris, index) => {
                const key = `issues.${index}`
                const lainnya = baris.jenis_kendala === 'LAINNYA'

                return (
                  <li key={index} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-2">
                    <FieldWrap
                      label="Pekerjaan Terkait"
                      required
                      className="sm:col-span-2"
                      error={errors[`${key}.work_item_ids`]}
                      hint="Centang pekerjaan dari bagian Progres Pekerjaan yang belum selesai."
                    >
                      {pekerjaanDipilih.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada pekerjaan yang dipilih pada bagian Progres Pekerjaan.</p>
                      ) : (
                        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap" role="group" aria-label="Pekerjaan terkait">
                          {pekerjaanDipilih.map((pekerjaanItem) => (
                            <label
                              key={pekerjaanItem.id}
                              className={cn(
                                'flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-sm text-ink transition-colors hover:bg-surface',
                                errors[`${key}.work_item_ids`] ? 'border-danger' : 'border-line',
                              )}
                            >
                              <input
                                type="checkbox"
                                checked={baris.work_item_ids.includes(pekerjaanItem.id)}
                                onChange={(event) =>
                                  ubahKendala(index, {
                                    work_item_ids: event.target.checked
                                      ? [...baris.work_item_ids, pekerjaanItem.id]
                                      : baris.work_item_ids.filter((id) => id !== pekerjaanItem.id),
                                  })
                                }
                                className="size-4 min-h-0 cursor-pointer rounded border-line accent-primary"
                              />
                              {pekerjaanItem.uraian_pekerjaan}
                            </label>
                          ))}
                        </div>
                      )}
                    </FieldWrap>
                    <Select
                      label="Jenis Kendala"
                      required
                      value={baris.jenis_kendala}
                      error={errors[`${key}.jenis_kendala`]}
                      // Isian "Sebutkan" direset agar nilai lama tidak ikut terkirim saat jenis diganti.
                      onChange={(event) => ubahKendala(index, { jenis_kendala: event.target.value, jenis_lainnya: '' })}
                    >
                      <option value="" disabled>
                        Pilih jenis kendala
                      </option>
                      {JENIS_KENDALA.map(([nilai, label]) => (
                        <option key={nilai} value={nilai}>
                          {label}
                        </option>
                      ))}
                    </Select>
                    {lainnya ? (
                      <Input
                        label="Sebutkan Jenis Kendala"
                        required
                        value={baris.jenis_lainnya}
                        error={errors[`${key}.jenis_lainnya`]}
                        onChange={(event) => ubahKendala(index, { jenis_lainnya: event.target.value })}
                        placeholder="Contoh: Perizinan, Akses Lokasi"
                      />
                    ) : (
                      // Slot kosong agar posisi isian di bawahnya tetap sama walau "Lainnya" tidak dipilih.
                      <div className="hidden sm:block" aria-hidden />
                    )}
                    <Textarea
                      label="Kendala & Alasan Keterlambatan"
                      required
                      value={baris.deskripsi}
                      error={errors[`${key}.deskripsi`]}
                      onChange={(event) => ubahKendala(index, { deskripsi: event.target.value })}
                      placeholder="Jelaskan kendala yang terjadi dan mengapa pekerjaan belum selesai."
                    />
                    <Textarea
                      label="Rencana Tindak Lanjut"
                      required
                      value={baris.tindak_lanjut}
                      error={errors[`${key}.tindak_lanjut`]}
                      onChange={(event) => ubahKendala(index, { tindak_lanjut: event.target.value })}
                      placeholder="Contoh: Menambah 4 tukang dan lembur hingga pukul 20.00 mulai besok."
                    />
                    {issues.length > 1 && (
                      <div className="sm:col-span-2">
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() => {
                            setIssues((current) => current.filter((_, i) => i !== index))
                            setErrors(tanpaErrorKendala)
                          }}
                          icon={<Trash2 className="size-4" />}
                        >
                          Hapus kendala
                        </Button>
                      </div>
                    )}
                  </li>
                )
              })}
            </ul>
            <Button
              variant="outline"
              size="sm"
              className="w-fit"
              icon={<Plus className="size-4" />}
              onClick={() => setIssues((current) => [...current, kendalaKosong(idDipilih.length === 1 ? idDipilih : [])])}
            >
              Tambah Kendala
            </Button>
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
      title={editId ? 'Keluar dari ubah draf?' : 'Keluar dari form progres?'}
      pesan={
        adaPerubahan
          ? editId
            ? 'Perubahan pada draf ini belum disimpan dan akan hilang jika keluar sekarang.'
            : 'Isian yang sudah Anda masukkan belum dikirim dan akan hilang jika keluar sekarang.'
          : 'Apakah Anda yakin ingin keluar? Draf tetap tersimpan dan dapat diubah atau dikirim nanti.'
      }
      labelKonfirmasi="Ya, Keluar"
      onConfirm={() => {
        const tujuan = tujuanKeluar
        setTujuanKeluar(null)
        keluar.current = true

        if (tujuan === KEMBALI) {
          // Lewati entri penjaga dan entri form itu sendiri, sama seperti menekan Kembali tanpa penjaga.
          window.history.go(-2)
        } else if (tujuan) {
          navigate(tujuan, { replace: adaPenjagaRiwayat() })
        }
      }}
      onClose={() => setTujuanKeluar(null)}
    />

    <AlertDialog
      open={payloadKirim !== null}
      icon={<Send className="size-5" aria-hidden />}
      title="Kirim progres ini?"
      labelKonfirmasi="Ya, Kirim"
      loading={simpan.isPending}
      onConfirm={() => {
        if (payloadKirim) simpan.mutate(payloadKirim, { onSettled: () => setPayloadKirim(null) })
      }}
      onClose={() => setPayloadKirim(null)}
    >
      Pastikan data sudah benar. Progres yang sudah dikirim tidak dapat diubah lagi.
    </AlertDialog>
    </>
  )
}
