/** Sisi terpanjang foto setelah diperkecil; sama dengan kompresi foto di backend (ProgressService). */
const SISI_MAKSIMUM = 1600
const KUALITAS_JPEG = 0.85
/** Foto kecil dikirim apa adanya. */
const BATAS_TANPA_KOMPRESI = 600 * 1024

async function muatGambar(file: File): Promise<{ sumber: CanvasImageSource; lebar: number; tinggi: number; tutup: () => void }> {
  if ('createImageBitmap' in window) {
    // imageOrientation menjaga foto ponsel tetap tegak seperti di galeri.
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })

    return { sumber: bitmap, lebar: bitmap.width, tinggi: bitmap.height, tutup: () => bitmap.close() }
  }

  const url = URL.createObjectURL(file)
  const img = new Image()
  img.src = url
  await img.decode()

  return { sumber: img, lebar: img.naturalWidth, tinggi: img.naturalHeight, tutup: () => URL.revokeObjectURL(url) }
}

/**
 * Perkecil foto sebelum diunggah (sisi terpanjang 1600 px, JPEG) agar foto kamera ponsel yang besar
 * tetap muat dalam batas unggah server. Bila foto sudah kecil, gagal diproses, atau hasilnya justru
 * lebih besar, berkas asli dipakai; backend tetap memvalidasi ukuran dan formatnya.
 */
export async function perkecilFoto(file: File): Promise<File> {
  if (!file.type.startsWith('image/') || file.size <= BATAS_TANPA_KOMPRESI) return file

  try {
    const gambar = await muatGambar(file)
    const skala = Math.min(1, SISI_MAKSIMUM / Math.max(gambar.lebar, gambar.tinggi))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(gambar.lebar * skala)
    canvas.height = Math.round(gambar.tinggi * skala)

    const ctx = canvas.getContext('2d')

    if (!ctx) {
      gambar.tutup()

      return file
    }

    // Latar putih agar bagian transparan PNG tidak menjadi hitam setelah diubah ke JPEG.
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(gambar.sumber, 0, 0, canvas.width, canvas.height)
    gambar.tutup()

    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', KUALITAS_JPEG))

    if (!blob || blob.size >= file.size) return file

    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified })
  } catch {
    return file
  }
}
