import { Button } from '@/components/ui/Button'
import type { UploadLimits } from '@/types'
import { ukuranFile } from '@/utils/format'
import { perkecilFoto } from '@/utils/perkecilFoto'
import { ImagePlus, Trash2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

export interface FotoTerpilih {
  file: File
  preview: string
  caption: string
}

interface FileUploadProps {
  files: FotoTerpilih[]
  onChange: (files: FotoTerpilih[]) => void
  /** Batas dari server; selama belum dimuat dipakai batas aman 2 MB per foto dan 7,5 MB per pengiriman. */
  limits?: UploadLimits
  error?: string
  /** Foto baru di daftar lain pada pengiriman yang sama; ikut dihitung terhadap batas jumlah dan total ukuran. */
  fotoLain?: FotoTerpilih[]
  /** Tampilan ringkas untuk dipasang di dalam baris form. */
  ringkas?: boolean
}

const TIPE_DIIZINKAN = ['image/jpeg', 'image/jpg', 'image/png']
const BATAS_CADANGAN: UploadLimits = { foto_maks_kb: 2048, foto_maks_jumlah: 10, total_maks_kb: 7680, pekerjaan_maks: 10, kendala_maks: 5 }

const labelUkuran = (kb: number) => (kb >= 1024 ? `${Number((kb / 1024).toFixed(1)).toLocaleString('id-ID')} MB` : `${kb} KB`)

/** Unggah foto bukti pekerjaan dengan pratinjau sebelum submit. Foto besar diperkecil otomatis sebelum diunggah. */
export function FileUpload({ files, onChange, limits = BATAS_CADANGAN, error, fotoLain = [], ringkas = false }: FileUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [pesan, setPesan] = useState<string | null>(null)
  const [memproses, setMemproses] = useState(false)

  useEffect(() => () => files.forEach((item) => URL.revokeObjectURL(item.preview)), [files])

  const maksFoto = limits.foto_maks_jumlah
  const maksByte = limits.foto_maks_kb * 1024
  const totalByte = [...files, ...fotoLain].reduce((jumlah, item) => jumlah + item.file.size, 0)

  const tambah = async (daftar: FileList | null) => {
    if (!daftar || daftar.length === 0) return

    const masuk = Array.from(daftar)
    if (inputRef.current) inputRef.current.value = ''

    setMemproses(true)
    const diterima: FotoTerpilih[] = []
    let keluhan: string | null = null
    let total = totalByte

    for (const asli of masuk) {
      if (!TIPE_DIIZINKAN.includes(asli.type)) {
        keluhan = 'Foto harus berformat JPG, JPEG, atau PNG.'
        continue
      }

      if (files.length + fotoLain.length + diterima.length >= maksFoto) {
        keluhan = `Maksimal ${maksFoto} foto per pengiriman.`
        break
      }

      const file = await perkecilFoto(asli)

      if (file.size > maksByte) {
        keluhan = `Ukuran foto "${asli.name}" ${ukuranFile(file.size)} melebihi batas ${labelUkuran(limits.foto_maks_kb)} per foto.`
        continue
      }

      if (limits.total_maks_kb > 0 && total + file.size > limits.total_maks_kb * 1024) {
        keluhan = `Total ukuran foto melebihi batas ${labelUkuran(limits.total_maks_kb)} per pengiriman. Simpan dulu, lalu tambahkan foto lain lewat Ubah Draf.`
        continue
      }

      total += file.size
      diterima.push({ file, preview: URL.createObjectURL(file), caption: '' })
    }

    setMemproses(false)
    setPesan(keluhan)

    if (diterima.length > 0) {
      onChange([...files, ...diterima])
    }
  }

  const hapus = (index: number) => {
    URL.revokeObjectURL(files[index].preview)
    onChange(files.filter((_, i) => i !== index))
  }

  const ubahCaption = (index: number, caption: string) => {
    onChange(files.map((item, i) => (i === index ? { ...item, caption } : item)))
  }

  const input = (
    <input
      ref={inputRef}
      type="file"
      accept="image/jpeg,image/png,image/jpg"
      multiple
      className="hidden"
      onChange={(event) => void tambah(event.target.files)}
    />
  )
  const ketentuan = `Format JPG, JPEG, atau PNG. Foto besar diperkecil otomatis; maksimal ${labelUkuran(limits.foto_maks_kb)} per foto dan ${maksFoto} foto per pengiriman.`

  return (
    <div className="flex flex-col gap-3">
      {ringkas ? (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          {input}
          <Button
            variant="outline"
            size="sm"
            className="w-fit shrink-0"
            icon={<ImagePlus className="size-4" />}
            loading={memproses}
            onClick={() => inputRef.current?.click()}
          >
            {memproses ? 'Memproses foto...' : 'Pilih Foto'}
          </Button>
          <p className="text-[11px] text-muted">{ketentuan}</p>
        </div>
      ) : (
        <div className="rounded-xl border border-dashed border-line bg-surface/60 p-4 text-center">
          <ImagePlus className="mx-auto size-6 text-muted" aria-hidden />
          <p className="mt-2 text-xs text-muted">{ketentuan}</p>
          {input}
          <Button variant="outline" size="sm" className="mt-3" loading={memproses} onClick={() => inputRef.current?.click()}>
            {memproses ? 'Memproses foto...' : 'Pilih Foto'}
          </Button>
        </div>
      )}

      {(pesan || error) && <p className="text-[11px] font-medium text-danger">{pesan ?? error}</p>}

      {files.length > 0 && (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {files.map((item, index) => (
            <li key={item.preview} className="app-card overflow-hidden">
              <img src={item.preview} alt={item.file.name} className="h-28 w-full object-cover" />
              <div className="space-y-2 p-2">
                <p className="truncate text-[11px] text-muted">
                  {item.file.name} - {ukuranFile(item.file.size)}
                </p>
                <input
                  value={item.caption}
                  onChange={(event) => ubahCaption(index, event.target.value)}
                  placeholder="Keterangan foto"
                  className="w-full rounded-md border border-line px-2 py-1 text-xs"
                />
                <Button variant="ghost" size="sm" className="w-full text-danger" icon={<Trash2 className="size-3.5" />} onClick={() => hapus(index)}>
                  Hapus
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
