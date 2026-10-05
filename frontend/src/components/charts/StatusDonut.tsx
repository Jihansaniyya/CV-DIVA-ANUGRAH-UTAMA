import type { ProjectStatus } from '@/types'
import { cn } from '@/utils/cn'
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'

const WARNA: Record<ProjectStatus, string> = {
  SELESAI: '#16a344',
  BERJALAN: '#f59e0b',
  TERLAMBAT: '#dc2626',
  BELUM_DIMULAI: '#94a3b8',
}

interface StatusDonutProps {
  data: { status: ProjectStatus; label: string; jumlah: number }[]
  tengah?: string
  keterangan?: string
  /** Donut lebih kecil dengan legenda di sampingnya. */
  compact?: boolean
}

/** Komposisi status proyek; status "Terlambat" hanya ditampilkan bila ada proyeknya. */
export function StatusDonut({ data, tengah, keterangan, compact = false }: StatusDonutProps) {
  const daftar = data.filter((item) => item.status !== 'TERLAMBAT' || item.jumlah > 0)
  const terisi = daftar.filter((item) => item.jumlah > 0)
  const total = daftar.reduce((jumlah, item) => jumlah + item.jumlah, 0)

  return (
    <div className={cn('flex items-center', compact ? 'flex-row gap-5' : 'flex-col gap-5')}>
      <div className={cn('relative shrink-0', compact ? 'size-36' : 'size-44')}>
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={terisi.length > 0 ? terisi : [{ status: 'BELUM_DIMULAI', label: 'Belum ada proyek', jumlah: 1 }]}
              dataKey="jumlah"
              nameKey="label"
              innerRadius="68%"
              outerRadius="100%"
              paddingAngle={terisi.length > 1 ? 2 : 0}
              stroke="none"
              startAngle={90}
              endAngle={-270}
            >
              {(terisi.length > 0 ? terisi : [{ status: 'BELUM_DIMULAI' as ProjectStatus }]).map((item) => (
                <Cell key={item.status} fill={terisi.length > 0 ? WARNA[item.status] : '#e2e8f0'} />
              ))}
            </Pie>
            {terisi.length > 0 && (
              <Tooltip
                formatter={(nilai, nama) => [`${Number(nilai)} proyek`, nama]}
                contentStyle={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 12 }}
              />
            )}
          </PieChart>
        </ResponsiveContainer>
        {tengah && (
          <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <p className={cn('leading-none font-semibold text-ink tabular-nums', compact ? 'text-2xl' : 'text-3xl')}>{tengah}</p>
            {keterangan && <p className="mt-1 text-[11px] text-muted">{keterangan}</p>}
          </div>
        )}
      </div>

      <ul className={cn('flex flex-col gap-2', compact ? 'min-w-0 flex-1' : 'w-full')}>
        {daftar.map((item) => (
          <li key={item.status} className={cn('flex items-center gap-2.5', compact ? 'text-[13px]' : 'text-sm')}>
            <span className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: WARNA[item.status] }} aria-hidden />
            <span className="flex-1 text-ink">{item.label}</span>
            <span className="font-semibold text-ink tabular-nums">{item.jumlah}</span>
            <span className="w-11 text-right text-xs text-muted tabular-nums">{total > 0 ? Math.round((item.jumlah / total) * 100) : 0}%</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
