import type { ProgressDetail } from '@/types'
import { cn } from '@/utils/cn'

const BATAS_TAMPIL = 3

/**
 * Nama pekerjaan yang dilaporkan pada satu laporan progres. Paling banyak tiga nama ditampilkan,
 * sisanya diringkas menjadi "+N lainnya" agar baris tabel tetap ringkas. Bila lebih dari satu pekerjaan,
 * setiap nama diberi penanda dan garis pemisah agar batas antarpekerjaan jelas.
 */
export function DaftarPekerjaan({ detail, className }: { detail: ProgressDetail[] | undefined; className?: string }) {
  const nama = (detail ?? []).map((item) => item.uraian_pekerjaan ?? '-')

  if (nama.length === 0) return <span className={cn('text-muted', className)}>-</span>

  const tampil = nama.slice(0, BATAS_TAMPIL)
  const sisa = nama.length - tampil.length
  const banyak = nama.length > 1

  return (
    <ul className={cn('flex flex-col', banyak && 'divide-y divide-line', className)} title={nama.join('\n')}>
      {tampil.map((item, index) => (
        <li key={`${item}-${index}`} className={cn('line-clamp-1', banyak && 'py-1 first:pt-0 last:pb-0')}>
          {banyak && <span className="mr-1.5 inline-block size-1.5 -translate-y-px rounded-full bg-navy-light align-middle" aria-hidden />}
          {item}
        </li>
      ))}
      {sisa > 0 && <li className="py-1 last:pb-0 text-muted">+{sisa} lainnya</li>}
    </ul>
  )
}
