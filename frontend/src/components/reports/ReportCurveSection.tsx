import { CurveSChart } from '@/components/charts/CurveSChart'
import type { CurveData, ReportMilestone } from '@/types'
import { angka, tanggal } from '@/utils/format'

function nilaiAtauStrip(nilai: number | null): string {
  return nilai === null ? '-' : angka(nilai, 2)
}

/** Kurva S rencana vs realisasi beserta titik dan tabel capaian milestone pada laporan. */
export function ReportCurveSection({ kurva, milestone }: { kurva: CurveData; milestone: ReportMilestone[] }) {
  return (
    <section className="report-section mt-6">
      <h3 className="mb-2 text-sm font-bold text-ink">KURVA S DAN MILESTONE PROYEK</h3>

      {kurva.titik.length > 0 && (
        <div className="report-chart">
          <CurveSChart data={kurva} tinggi={300} />
        </div>
      )}

      <div className="app-scroll-x mt-3">
        <table className="report-table min-w-max">
          <thead>
            <tr>
              <th>NO.</th>
              <th className="min-w-56">Milestone</th>
              <th>Periode</th>
              <th>Tanggal Target</th>
              <th>Target (%)</th>
              <th>Realisasi (%)</th>
              <th>Deviasi (%)</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {milestone.length === 0 ? (
              <tr>
                <td colSpan={8} className="text-center text-muted">
                  Belum ada milestone pada proyek ini.
                </td>
              </tr>
            ) : (
              milestone.map((item, index) => (
                <tr key={item.id}>
                  <td className="text-center">{index + 1}</td>
                  <td>
                    {item.nama}
                    {item.deskripsi && <p className="text-[11px] text-muted">{item.deskripsi}</p>}
                  </td>
                  <td className="text-center">{item.periode ?? '-'}</td>
                  <td className="text-center whitespace-nowrap">{tanggal(item.tanggal_target)}</td>
                  <td className="num">{angka(item.target_persentase, 2)}</td>
                  <td className="num">{nilaiAtauStrip(item.realisasi_persentase)}</td>
                  <td className={`num ${item.deviasi !== null && item.deviasi < 0 ? 'text-danger' : ''}`}>{nilaiAtauStrip(item.deviasi)}</td>
                  <td className="text-center">{item.status.replace('_', ' ')}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  )
}
