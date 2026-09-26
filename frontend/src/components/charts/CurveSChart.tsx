import type { CurveData } from '@/types'
import { angka, deviasi as teksDeviasi } from '@/utils/format'
import {
  CartesianGrid,
  Line,
  LineChart,
  ReferenceDot,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  type TooltipContentProps,
} from 'recharts'

const NAVY = '#071a52'
const MERAH = '#d71920'
const AMBER = '#f59e0b'
const TICK_PERSEN = [0, 20, 40, 60, 80, 100]

interface CurveSChartProps {
  data: CurveData
  tinggi?: number
  tampilkanMilestone?: boolean
}

interface Titik {
  nama: string
  rencana: number
  aktual: number | null
  deviasi: number | null
  periodId: number
}

function potong(teks: string, maks: number): string {
  return teks.length > maks ? `${teks.slice(0, maks - 1)}…` : teks
}

function TooltipKurva({ active, payload }: TooltipContentProps) {
  const titik = payload?.[0]?.payload as Titik | undefined

  if (!active || !titik) return null

  return (
    <div className="rounded-lg border border-line bg-white px-3 py-2 text-xs shadow-md">
      <p className="mb-1 font-semibold text-ink">{titik.nama}</p>
      <dl className="grid grid-cols-[auto_auto] gap-x-4 gap-y-0.5">
        <dt className="text-muted">Rencana</dt>
        <dd className="text-right font-medium text-ink tabular-nums">{angka(titik.rencana, 2)}%</dd>
        <dt className="text-muted">Realisasi</dt>
        <dd className="text-right font-medium text-ink tabular-nums">{titik.aktual === null ? '-' : `${angka(titik.aktual, 2)}%`}</dd>
        <dt className="text-muted">Deviasi</dt>
        <dd className={`text-right font-semibold tabular-nums ${titik.deviasi !== null && titik.deviasi < -0.005 ? 'text-danger' : 'text-ink'}`}>
          {teksDeviasi(titik.deviasi)}
        </dd>
      </dl>
    </div>
  )
}

/**
 * Kurva S: rencana kumulatif (putus-putus) vs realisasi kumulatif (tebal) per periode.
 * Milestone ditandai sebagai titik pada target persentasenya.
 */
export function CurveSChart({ data, tinggi = 320, tampilkanMilestone = true }: CurveSChartProps) {
  const titik: Titik[] = data.titik.map((item) => ({
    nama: item.nama_periode,
    rencana: item.rencana_kumulatif,
    aktual: item.aktual_kumulatif,
    deviasi: item.deviasi,
    periodId: item.period_id,
  }))

  const milestone = tampilkanMilestone
    ? data.milestones
        .map((item) => {
          const baris = titik.find((t) => t.periodId === item.period_id)

          return baris ? { x: baris.nama, y: item.target_persentase, nama: item.nama } : null
        })
        .filter((item): item is { x: string; y: number; nama: string } => item !== null)
    : []

  return (
    <div>
      <div style={{ width: '100%', height: tinggi }}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={titik} margin={{ top: 24, right: 16, bottom: 4, left: -8 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
            <XAxis
              dataKey="nama"
              tick={{ fontSize: 11, fill: '#64748b' }}
              tickLine={false}
              axisLine={{ stroke: '#cbd5e1' }}
              interval="preserveStartEnd"
              minTickGap={16}
              tickMargin={8}
            />
            <YAxis
              domain={[0, (maks: number) => Math.max(100, Math.ceil(maks))]}
              ticks={TICK_PERSEN}
              tickFormatter={(nilai: number) => `${nilai}%`}
              tick={{ fontSize: 11, fill: '#64748b' }}
              tickLine={false}
              axisLine={false}
              width={48}
            />
            <Tooltip content={TooltipKurva} cursor={{ stroke: '#cbd5e1', strokeDasharray: '4 4' }} />
            <Line
              type="monotone"
              dataKey="rencana"
              name="Rencana"
              stroke={NAVY}
              strokeWidth={2}
              strokeDasharray="6 4"
              dot={{ r: 2.5, fill: NAVY, strokeWidth: 0 }}
              activeDot={{ r: 5 }}
            />
            <Line
              type="monotone"
              dataKey="aktual"
              name="Realisasi"
              stroke={MERAH}
              strokeWidth={3}
              dot={{ r: 3.5, fill: MERAH, strokeWidth: 0 }}
              activeDot={{ r: 6 }}
              connectNulls={false}
            />
            {milestone.map((item) => (
              <ReferenceDot
                key={`${item.x}-${item.nama}`}
                x={item.x}
                y={item.y}
                r={6}
                fill={AMBER}
                stroke="#ffffff"
                strokeWidth={2}
                label={{ value: potong(item.nama, 26), position: 'top', offset: 10, fontSize: 10, fill: '#92400e' }}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>

      <ul className="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-1.5 text-xs text-muted">
        <li className="flex items-center gap-2">
          <svg width="22" height="6" aria-hidden>
            <line x1="0" y1="3" x2="22" y2="3" stroke={NAVY} strokeWidth="2" strokeDasharray="5 3" />
          </svg>
          Rencana kumulatif
        </li>
        <li className="flex items-center gap-2">
          <svg width="22" height="6" aria-hidden>
            <line x1="0" y1="3" x2="22" y2="3" stroke={MERAH} strokeWidth="3" />
          </svg>
          Realisasi kumulatif
        </li>
        {milestone.length > 0 && (
          <li className="flex items-center gap-2">
            <span className="size-2.5 rounded-full" style={{ backgroundColor: AMBER }} aria-hidden />
            Milestone
          </li>
        )}
      </ul>
    </div>
  )
}
