import { Button } from '@/components/ui/Button'
import { ukuranFile } from '@/utils/format'
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
  maxFiles?: number
  maxSizeMb?: number
  error?: string
}

const TIPE_DIIZINKAN = ['image/jpeg', 'image/jpg', 'image/png']

/** Unggah foto bukti pekerjaan dengan pratinjau sebelum submit. */
export function FileUpload({ files, onChange, maxFiles = 10, maxSizeMb = 5, error }: FileUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [pesan, setPesan] = useState<string | null>(null)

  useEffect(() => () => files.forEach((item) => URL.revokeObjectURL(item.preview)), [files])

  const tambah = (daftar: FileList | null) => {
    if (!daftar) return

    const diterima: FotoTerpilih[] = []
    let keluhan: string | null = null

    Array.from(daftar).forEach((file) => {
      if (!TIPE_DIIZINKAN.includes(file.type)) {
        keluhan = 'Foto harus berformat JPG, JPEG, atau PNG.'

        return
      }

      if (file.size > maxSizeMb * 1024 * 1024) {
        keluhan = `Ukuran foto maksimal ${maxSizeMb} MB.`

        return
      }

      if (files.length + diterima.length >= maxFiles) {
        keluhan = `Maksimal ${maxFiles} foto per laporan.`

        return
      }

      diterima.push({ file, preview: URL.createObjectURL(file), caption: '' })
    })

    setPesan(keluhan)

    if (diterima.length > 0) {
      onChange([...files, ...diterima])
    }

    if (inputRef.current) inputRef.current.value = ''
  }

  const hapus = (index: number) => {
    URL.revokeObjectURL(files[index].preview)
    onChange(files.filter((_, i) => i !== index))
  }

  const ubahCaption = (index: number, caption: string) => {
    onChange(files.map((item, i) => (i === index ? { ...item, caption } : item)))
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="rounded-xl border border-dashed border-line bg-surface/60 p-4 text-center">
        <ImagePlus className="mx-auto size-6 text-muted" aria-hidden />
        <p className="mt-2 text-xs text-muted">Format JPG, JPEG, atau PNG. Maksimal {maxSizeMb} MB per foto.</p>
        <input
          ref={inputRef}
          type="file"
          accept="image/jpeg,image/png,image/jpg"
          multiple
          className="hidden"
          onChange={(event) => tambah(event.target.files)}
        />
        <Button variant="outline" size="sm" className="mt-3" onClick={() => inputRef.current?.click()}>
          Pilih Foto
        </Button>
      </div>

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
