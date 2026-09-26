import { angka } from '@/utils/format'

interface BarisRingkasan {
  label: string
  nilai: number
  deviasi?: boolean
}

interface ReportSummaryProps {
  judul?: string
  baris: BarisRingkasan[]
  /** Sembunyikan saat dicetak bila angkanya sudah tercantum di tabel resmi. */
  hanyaLayar?: boolean
}

/** Tabel ringkasan rencana, realisasi, dan deviasi di bawah tabel laporan (format dokumen). */
export function ReportSummary({ judul = 'RINGKASAN PROGRES', baris, hanyaLayar = false }: ReportSummaryProps) {
  return (
    <div className={`report-section mt-5 ${hanyaLayar ? 'no-print' : ''}`}>
      <table className="report-table report-table-ringkas">
        <thead>
          <tr>
            <th colSpan={2} className="text-left">
              {judul}
            </th>
          </tr>
        </thead>
        <tbody>
          {baris.map((item) => (
            <tr key={item.label}>
              <td className="font-semibold">{item.label}</td>
              <td className={`num w-28 font-semibold ${item.deviasi ? (item.nilai < -0.005 ? 'text-danger' : 'text-success') : ''}`}>
                {angka(item.nilai, 2)}%
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
