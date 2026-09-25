import type { CurveData } from '@/types'
import { angka } from '@/utils/format'
import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ReferenceDot,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'

interface CurveSChartProps {
  data: CurveData
  tinggi?: number
  tampilkanMilestone?: boolean
}

/**
 * Kurva S: rencana kumulatif vs realisasi kumulatif per periode.
 * Milestone ditandai sebagai titik pada garis rencana saat laporan digenerate.
 */
export function CurveSChart({ data, tinggi = 320, tampilkanMilestone = true }: CurveSChartProps) {
  const titik = data.titik.map((item) => ({
    nama: item.nama_periode.replace('Minggu ', 'M-'),
    rencana: item.rencana_kumulatif,
    aktual: item.aktual_kumulatif,
    deviasi: item.deviasi,
    periodId: item.period_id,
  }))

  const milestone = tampilkanMilestone
    ? data.milestones
        .map((item) => {
          const index = titik.findIndex((baris) => baris.periodId === item.period_id)

          return index >= 0 ? { x: titik[index].nama, y: item.target_persentase, nama: item.nama } : null
        })
        .filter((item): item is { x: string; y: number; nama: string } => item !== null)
    : []

  return (
    <div style={{ width: '100%', height: tinggi }}>
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={titik} margin={{ top: 12, right: 12, bottom: 4, left: -12 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
          <XAxis dataKey="nama" tick={{ fontSize: 11, fill: '#64748b' }} tickLine={false} axisLine={{ stroke: '#e2e8f0' }} />
          <YAxis
            domain={[0, 100]}
            tickFormatter={(nilai: number) => `${nilai}%`}
            tick={{ fontSize: 11, fill: '#64748b' }}
            tickLine={false}
            axisLine={false}
          />
          <Tooltip
            formatter={(nilai, nama) => [nilai === null || nilai === undefined ? '-' : `${angka(Number(nilai), 2)}%`, nama]}
            labelFormatter={(label) => `Periode ${String(label)}`}
            contentStyle={{ borderRadius: 10, border: '1px solid #e2e8f0', fontSize: 12 }}
          />
          <Legend wrapperStyle={{ fontSize: 12 }} />
          <Line
            type="monotone"
            dataKey="rencana"
            name="Rencana"
            stroke="#071a52"
            strokeWidth={2}
            dot={{ r: 3, fill: '#071a52' }}
            activeDot={{ r: 5 }}
          />
          <Line
            type="monotone"
            dataKey="aktual"
            name="Realisasi"
            stroke="#d71920"
            strokeWidth={2}
            dot={{ r: 3, fill: '#d71920' }}
            activeDot={{ r: 5 }}
            connectNulls={false}
          />
          {milestone.map((item) => (
            <ReferenceDot
              key={`${item.x}-${item.nama}`}
              x={item.x}
              y={item.y}
              r={6}
              fill="#f59e0b"
              stroke="#ffffff"
              strokeWidth={2}
              label={{ value: item.nama, position: 'top', fontSize: 10, fill: '#b45309' }}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </div>
  )
}
