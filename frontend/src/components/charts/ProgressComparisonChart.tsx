import { angka } from '@/utils/format'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'

interface ProgressComparisonChartProps {
  data: { nama_proyek: string; rencana: number; aktual: number }[]
}

/** Perbandingan progres rencana dan aktual antar proyek. */
export function ProgressComparisonChart({ data }: ProgressComparisonChartProps) {
  const titik = data.map((item) => ({
    ...item,
    nama: item.nama_proyek.length > 22 ? `${item.nama_proyek.slice(0, 22)}...` : item.nama_proyek,
  }))

  return (
    <div style={{ width: '100%', height: 280 }}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={titik} margin={{ top: 8, right: 8, bottom: 4, left: -16 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
          <XAxis dataKey="nama" tick={{ fontSize: 10, fill: '#64748b' }} tickLine={false} axisLine={{ stroke: '#e2e8f0' }} interval={0} angle={-12} textAnchor="end" height={54} />
          <YAxis domain={[0, 100]} tickFormatter={(nilai: number) => `${nilai}%`} tick={{ fontSize: 11, fill: '#64748b' }} tickLine={false} axisLine={false} />
          <Tooltip formatter={(nilai, nama) => [`${angka(Number(nilai), 2)}%`, nama]} contentStyle={{ borderRadius: 10, fontSize: 12 }} />
          <Legend wrapperStyle={{ fontSize: 12 }} />
          <Bar dataKey="rencana" name="Rencana" fill="#071a52" radius={[4, 4, 0, 0]} maxBarSize={28} />
          <Bar dataKey="aktual" name="Realisasi" fill="#d71920" radius={[4, 4, 0, 0]} maxBarSize={28} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}
