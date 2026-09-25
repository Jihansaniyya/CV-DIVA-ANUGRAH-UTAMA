import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { qk, useWorkPlans } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { pesanError } from '@/lib/api'
import { projectService, type WorkPlanRowPayload } from '@/services/projectService'
import { angka } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'

type Draft = Record<string, number>

const kunci = (workItemId: number, periodId: number) => `${workItemId}:${periodId}`

/**
 * Matriks rencana pekerjaan: baris pekerjaan x kolom periode.
 * Admin mengisi target volume, sistem menghitung target bobot dan kumulatifnya.
 */
export function WorkPlanTab({ projectId }: { projectId: number }) {
  const { punyaPeran } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const adminMode = punyaPeran('ADMIN')

  const { data, isLoading, error, refetch } = useWorkPlans(projectId)
  const [draft, setDraft] = useState<Draft>({})

  useEffect(() => {
    if (!data) return

    const awal: Draft = {}
    data.baris.forEach((baris) => {
      baris.periode.forEach((sel) => {
        awal[kunci(baris.work_item_id, sel.period_id)] = sel.target_volume
      })
    })
    setDraft(awal)
  }, [data])

  const simpan = useMutation({
    mutationFn: (rows: WorkPlanRowPayload[]) => projectService.syncWorkPlans(projectId, rows),
    onSuccess: async () => {
      toast.sukses('Rencana pekerjaan berhasil disimpan.')
      await queryClient.invalidateQueries({ queryKey: qk.workPlans(projectId) })
      await queryClient.invalidateQueries({ queryKey: qk.curve(projectId) })
      await queryClient.invalidateQueries({ queryKey: qk.project(projectId) })
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  /** Ringkasan target bobot per periode dihitung ulang saat nilai diubah. */
  const ringkasan = useMemo(() => {
    if (!data) return []

    let kumulatif = 0

    return data.periode.map((period) => {
      const bobot = data.baris.reduce((total, baris) => {
        const volume = draft[kunci(baris.work_item_id, period.id)] ?? 0
        const persentase = baris.volume > 0 ? (volume / baris.volume) * 100 : 0

        return total + (persentase * baris.bobot) / 100
      }, 0)

      kumulatif += bobot

      return { period_id: period.id, nama: period.nama_periode, bobot, kumulatif }
    })
  }, [data, draft])

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data || data.baris.length === 0) {
    return <EmptyState judul="Belum ada pekerjaan" pesan="Tambahkan pekerjaan terlebih dahulu pada tab Pekerjaan." />
  }

  const kirim = () => {
    const rows: WorkPlanRowPayload[] = []

    data.baris.forEach((baris) => {
      data.periode.forEach((period) => {
        rows.push({
          work_item_id: baris.work_item_id,
          period_id: period.id,
          target_volume: draft[kunci(baris.work_item_id, period.id)] ?? 0,
        })
      })
    })

    simpan.mutate(rows)
  }

  return (
    <Card
      title="Rencana Pekerjaan per Periode"
      description="Target volume tiap periode menjadi dasar pembentukan Kurva S."
      action={
        adminMode ? (
          <Button size="sm" icon={<Save className="size-4" />} loading={simpan.isPending} onClick={kirim}>
            Simpan Rencana
          </Button>
        ) : undefined
      }
      bodyClassName="pt-0"
    >
      <div className="app-scroll-x -mx-4 sm:mx-0">
        <table className="report-table min-w-max">
          <thead>
            <tr>
              <th rowSpan={2} className="min-w-56 text-left">
                Uraian Pekerjaan
              </th>
              <th rowSpan={2}>Satuan</th>
              <th rowSpan={2}>Volume</th>
              <th rowSpan={2}>Bobot (%)</th>
              <th colSpan={data.periode.length}>Target Volume per Periode</th>
              <th rowSpan={2}>Sisa</th>
            </tr>
            <tr>
              {data.periode.map((period) => (
                <th key={period.id} className="min-w-24">
                  {period.nama_periode.replace('Minggu ', 'M-')}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.baris.map((baris) => {
              const totalDraft = data.periode.reduce((total, period) => total + (draft[kunci(baris.work_item_id, period.id)] ?? 0), 0)
              const sisa = baris.volume - totalDraft

              return (
                <tr key={baris.work_item_id}>
                  <td className="text-left">
                    {baris.uraian_pekerjaan}
                    {baris.kategori && <span className="block text-[10px] text-muted">{baris.kategori}</span>}
                  </td>
                  <td className="text-center">{baris.satuan}</td>
                  <td className="num">{angka(baris.volume, 2)}</td>
                  <td className="num">{angka(baris.bobot, 4)}</td>
                  {data.periode.map((period) => (
                    <td key={period.id} className="p-0">
                      <input
                        type="number"
                        step="0.001"
                        min="0"
                        disabled={!adminMode}
                        className="w-full border-0 bg-transparent px-2 py-1.5 text-right text-xs tabular-nums focus:bg-primary-light disabled:text-muted"
                        value={draft[kunci(baris.work_item_id, period.id)] || ''}
                        onChange={(event) =>
                          setDraft((current) => ({
                            ...current,
                            [kunci(baris.work_item_id, period.id)]: Number(event.target.value || 0),
                          }))
                        }
                        aria-label={`Target ${baris.uraian_pekerjaan} ${period.nama_periode}`}
                      />
                    </td>
                  ))}
                  <td className={`num ${sisa < -0.001 ? 'text-danger' : sisa > 0.001 ? 'text-warning' : 'text-success'}`}>
                    {angka(sisa, 2)}
                  </td>
                </tr>
              )
            })}
          </tbody>
          <tfoot>
            <tr className="font-semibold">
              <td colSpan={4} className="text-right">
                RENCANA MINGGUAN (%)
              </td>
              {ringkasan.map((item) => (
                <td key={item.period_id} className="num">
                  {angka(item.bobot, 3)}
                </td>
              ))}
              <td />
            </tr>
            <tr className="font-semibold">
              <td colSpan={4} className="text-right">
                RENCANA KOMULATIF (%)
              </td>
              {ringkasan.map((item) => (
                <td key={item.period_id} className="num">
                  {angka(item.kumulatif, 3)}
                </td>
              ))}
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      {adminMode && (
        <p className="mt-3 text-[11px] text-muted">
          Total target volume seluruh periode tidak boleh melebihi volume rencana pekerjaan. Kolom Sisa berwarna merah menandakan
          kelebihan target.
        </p>
      )}
    </Card>
  )
}
