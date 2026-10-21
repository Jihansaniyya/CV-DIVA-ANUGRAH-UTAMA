/**
 * Ambang warna progres:
 * - merah  : 0% sampai di bawah 40%
 * - kuning : 40% sampai di bawah 80%
 * - hijau  : 80% sampai 100%
 */
export const AMBANG_KUNING = 40
export const AMBANG_HIJAU = 80

/** Kelas warna isi bar dan teks di atasnya sesuai ambang merah/kuning/hijau. */
export function nadaLevel(nilai: number): { isi: string; teks: string } {
  if (nilai >= AMBANG_HIJAU) return { isi: 'bg-success', teks: 'text-white' }
  if (nilai >= AMBANG_KUNING) return { isi: 'bg-warning', teks: 'text-ink' }

  return { isi: 'bg-danger', teks: 'text-white' }
}

/** Kelas warna latar bar progres; sama dengan legenda pada tabel proyek. */
export const warnaLevel = (nilai: number) => nadaLevel(nilai).isi
