import { format, parseISO } from 'date-fns'
import { id } from 'date-fns/locale'

/** Angka desimal gaya Indonesia (1.234,56). */
export function angka(nilai: number | null | undefined, desimal = 2): string {
  if (nilai === null || nilai === undefined || Number.isNaN(nilai)) {
    return '-'
  }

  return nilai.toLocaleString('id-ID', {
    minimumFractionDigits: desimal,
    maximumFractionDigits: desimal,
  })
}

export function persen(nilai: number | null | undefined, desimal = 2): string {
  if (nilai === null || nilai === undefined) {
    return '-'
  }

  return `${angka(nilai, desimal)}%`
}

/** Deviasi bertanda: positif berarti realisasi mendahului rencana. */
export function deviasi(nilai: number | null | undefined, desimal = 2): string {
  if (nilai === null || nilai === undefined) {
    return '-'
  }

  return `${nilai > 0.005 ? '+' : ''}${angka(nilai, desimal)}%`
}

export function rupiah(nilai: number | null | undefined): string {
  if (nilai === null || nilai === undefined) {
    return '-'
  }

  return nilai.toLocaleString('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 2,
  })
}

export function tanggal(nilai: string | null | undefined, pola = 'dd MMMM yyyy'): string {
  if (!nilai) {
    return '-'
  }

  try {
    return format(parseISO(nilai), pola, { locale: id })
  } catch {
    return nilai
  }
}

export function tanggalSingkat(nilai: string | null | undefined): string {
  return tanggal(nilai, 'dd MMM yyyy')
}

export function rentangTanggal(dari: string | null | undefined, sampai: string | null | undefined): string {
  return `${tanggalSingkat(dari)} s/d ${tanggalSingkat(sampai)}`
}

export function waktu(nilai: string | null | undefined): string {
  return tanggal(nilai, 'dd MMM yyyy HH:mm')
}

export function hariIni(): string {
  return format(new Date(), 'yyyy-MM-dd')
}

const ROMAWI = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII']

export function romawi(angkaBulat: number): string {
  return ROMAWI[angkaBulat] ?? String(angkaBulat)
}

export function ukuranFile(byte: number | null | undefined): string {
  if (!byte) {
    return '-'
  }

  const satuan = ['B', 'KB', 'MB']
  let nilai = byte
  let index = 0

  while (nilai >= 1024 && index < satuan.length - 1) {
    nilai /= 1024
    index += 1
  }

  return `${angka(nilai, index === 0 ? 0 : 1)} ${satuan[index]}`
}
