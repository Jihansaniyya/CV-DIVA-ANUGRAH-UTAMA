import type { ProgressDetail } from '@/types'
import { cn } from '@/utils/cn'

const BATAS_TAMPIL = 3

/**
 * Nama pekerjaan yang dilaporkan pada satu laporan progres. Paling banyak tiga nama ditampilkan,
 * sisanya diringkas menjadi "+N lainnya" agar baris tabel tetap ringkas.
 */
export function DaftarPekerjaan({ detail, className }: { detail: ProgressDetail[] | undefined; className?: string }) {
  const nama = (detail ?? []).map((item) => item.uraian_pekerjaan ?? '-')

  if (nama.length === 0) return <span className={cn('text-muted', className)}>-</span>

  const tampil = nama.slice(0, BATAS_TAMPIL)
  const sisa = nama.length - tampil.length

  return (
    <ul className={cn('flex flex-col gap-0.5', className)} title={nama.join('\n')}>
      {tampil.map((item, index) => (
        <li key={`${item}-${index}`} className="line-clamp-1">
          {item}
        </li>
      ))}
      {sisa > 0 && <li className="text-muted">+{sisa} lainnya</li>}
    </ul>
  )
}
