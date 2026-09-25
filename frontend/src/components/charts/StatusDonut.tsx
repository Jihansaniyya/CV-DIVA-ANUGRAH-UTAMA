import type { ProjectStatus } from '@/types'
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'

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
}

export function StatusDonut({ data, tengah, keterangan }: StatusDonutProps) {
  const terisi = data.filter((item) => item.jumlah > 0)

  return (
    <div className="relative" style={{ width: '100%', height: 250 }}>
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie data={terisi} dataKey="jumlah" nameKey="label" innerRadius="58%" outerRadius="82%" paddingAngle={2} stroke="none">
            {terisi.map((item) => (
              <Cell key={item.status} fill={WARNA[item.status]} />
            ))}
          </Pie>
          <Tooltip formatter={(nilai, nama) => [`${Number(nilai)} proyek`, nama]} contentStyle={{ borderRadius: 10, fontSize: 12 }} />
          <Legend wrapperStyle={{ fontSize: 12 }} />
        </PieChart>
      </ResponsiveContainer>
      {tengah && (
        <div className="pointer-events-none absolute inset-x-0 top-[38%] -translate-y-1/2 text-center">
          <p className="text-2xl font-semibold text-ink">{tengah}</p>
          {keterangan && <p className="text-[11px] text-muted">{keterangan}</p>}
        </div>
      )}
    </div>
  )
}
