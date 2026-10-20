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
import { Save, Wand2 } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'

type Draft = Record<string, number>

const kunci = (workItemId: number, periodId: number) => `${workItemId}:${periodId}`

/** Bobot rencana = (target volume / volume pekerjaan) x bobot pekerjaan. */
const bobotRencana = (targetVolume: number, volume: number, bobot: number) => (volume > 0 ? (targetVolume / volume) * bobot : 0)

const TOLERANSI = 0.0005

/**
 * Matriks rencana pekerjaan: baris pekerjaan x kolom periode.
 * Admin hanya mengisi target volume per periode. Bobot rencana mingguan,
 * rencana kumulatif, dan sisa volume dihitung sistem. Jumlah kolom periode
 * mengikuti periode proyek dari backend.
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

  const segarkanRencana = async () => {
    await queryClient.invalidateQueries({ queryKey: qk.workPlans(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.curve(projectId) })
    await queryClient.invalidateQueries({ queryKey: qk.project(projectId) })
    await queryClient.invalidateQueries({ queryKey: ['projects'] })
  }

  const simpan = useMutation({
    mutationFn: (rows: WorkPlanRowPayload[]) => projectService.syncWorkPlans(projectId, rows),
    onSuccess: async () => {
      toast.sukses('Rencana pekerjaan berhasil disimpan.')
      await segarkanRencana()
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  const bagiRata = useMutation({
    mutationFn: () => projectService.distributeWorkPlans(projectId),
    onSuccess: async (hasil) => {
      toast.sukses(hasil.message)
      await segarkanRencana()
    },
    onError: (err) => toast.gagal(pesanError(err)),
  })

  // Tombol bagi rata hanya muncul bila ada pekerjaan yang rencana tersimpannya masih kosong.
  const adaRencanaKosong =
    data?.baris.some((baris) => baris.volume > 0 && baris.periode.every((sel) => (sel.target_volume ?? 0) <= 0)) ?? false

  /** Ringkasan bobot rencana per periode dihitung ulang saat nilai diubah. */
  const ringkasan = useMemo(() => {
    if (!data) return []

    let kumulatif = 0

    return data.periode.map((period) => {
      const bobot = data.baris.reduce((total, baris) => {
        const volume = draft[kunci(baris.work_item_id, period.id)] ?? 0

        return total + bobotRencana(volume, baris.volume, baris.bobot)
      }, 0)

      kumulatif += bobot

      return { period_id: period.id, nama: period.nama_periode, bobot, kumulatif }
    })
  }, [data, draft])

  /** Total target volume, sisa, dan total bobot rencana per pekerjaan. */
  const totalBaris = useMemo(() => {
    const hasil: Record<number, { sisa: number; totalBobot: number }> = {}

    data?.baris.forEach((baris) => {
      const totalVolume = data.periode.reduce((total, period) => total + (draft[kunci(baris.work_item_id, period.id)] ?? 0), 0)

      hasil[baris.work_item_id] = {
        sisa: baris.volume - totalVolume,
        totalBobot: bobotRencana(totalVolume, baris.volume, baris.bobot),
      }
    })

    return hasil
  }, [data, draft])

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />
  if (!data || data.baris.length === 0) {
    return <EmptyState judul="Belum ada pekerjaan" pesan="Tambahkan pekerjaan terlebih dahulu pada tab Pekerjaan." />
  }

  const barisMelebihi = data.baris.filter((baris) => (totalBaris[baris.work_item_id]?.sisa ?? 0) < -TOLERANSI)
  const totalBobotRencana = ringkasan.at(-1)?.kumulatif ?? 0

  const kirim = () => {
    if (barisMelebihi.length > 0) {
      toast.gagal(`Total target volume "${barisMelebihi[0].uraian_pekerjaan}" melebihi volume pekerjaan. Rencana tidak dapat disimpan.`)

      return
    }

    const rows: WorkPlanRowPayload[] = []

    // Hanya periode aktif (Periode Mulai s/d Periode Selesai) yang dikirim.
    data.baris.forEach((baris) => {
      baris.periode
        .filter((sel) => sel.aktif)
        .forEach((sel) => {
          rows.push({
            work_item_id: baris.work_item_id,
            period_id: sel.period_id,
            target_volume: draft[kunci(baris.work_item_id, sel.period_id)] ?? 0,
          })
        })
    })

    simpan.mutate(rows)
  }

  return (
    <Card
      title="Rencana Pekerjaan per Periode"
      description={`Periode proyek M-I s/d ${data.periode.at(-1)?.nama_periode ?? '-'} (${data.periode.length} minggu). Admin mengisi target volume pada minggu aktif; bobot rencana, rencana kumulatif, dan Kurva S dihitung otomatis.`}
      action={
        adminMode ? (
          <div className="flex flex-wrap items-center gap-2">
            {adaRencanaKosong && (
              <Button
                size="sm"
                variant="outline"
                icon={<Wand2 className="size-4" />}
                loading={bagiRata.isPending}
                disabled={simpan.isPending}
                onClick={() => bagiRata.mutate()}
                title="Isi pekerjaan yang rencananya masih kosong dengan membagi volumenya rata ke periode aktif"
              >
                Bagi Rata Otomatis
              </Button>
            )}
            <Button
              size="sm"
              icon={<Save className="size-4" />}
              loading={simpan.isPending}
              disabled={barisMelebihi.length > 0 || bagiRata.isPending}
              onClick={kirim}
            >
              Simpan Rencana
            </Button>
          </div>
        ) : undefined
      }
      bodyClassName="pt-0"
    >
      <div className="app-scroll-x -mx-4 sm:mx-0">
        <table className="report-table w-auto! min-w-max">
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
              <th rowSpan={2}>
                Bobot
                <br />
                Rencana (%)
              </th>
            </tr>
            <tr>
              {data.periode.map((period) => (
                <th key={period.id} className="w-14 min-w-14 px-1!">
                  {period.nama_periode}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.baris.map((baris) => {
              const { sisa, totalBobot } = totalBaris[baris.work_item_id] ?? { sisa: baris.volume, totalBobot: 0 }

              return (
                <tr key={baris.work_item_id}>
                  <td className="text-left">
                    {baris.uraian_pekerjaan}
                    <span className="block text-[10px] text-muted">
                      {[baris.kategori, baris.periode_mulai && `${baris.periode_mulai} s/d ${baris.periode_selesai ?? baris.periode_mulai}`]
                        .filter(Boolean)
                        .join(' · ')}
                    </span>
                  </td>
                  <td className="text-center">{baris.satuan}</td>
                  <td className="num">{angka(baris.volume, 2)}</td>
                  <td className="num">{angka(baris.bobot, 2)}</td>
                  {data.periode.map((period) => {
                    const target = draft[kunci(baris.work_item_id, period.id)] ?? 0
                    const aktif = baris.periode.find((sel) => sel.period_id === period.id)?.aktif ?? false

                    if (!aktif) {
                      return (
                        <td
                          key={period.id}
                          className="bg-surface text-center text-muted"
                          title={`Di luar periode pekerjaan (${baris.periode_mulai ?? '-'} s/d ${baris.periode_selesai ?? '-'})`}
                          aria-label={`${period.nama_periode} tidak aktif untuk ${baris.uraian_pekerjaan}`}
                        >
                          -
                        </td>
                      )
                    }

                    return (
                      <td key={period.id} className="p-0 align-top">
                        <input
                          type="number"
                          step="0.0001"
                          min="0"
                          disabled={!adminMode}
                          className="w-14 [appearance:textfield] border-0 bg-transparent px-1.5 py-1.5 text-right text-xs tabular-nums focus:bg-primary-light disabled:text-muted [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                          value={target || ''}
                          onChange={(event) =>
                            setDraft((current) => ({
                              ...current,
                              [kunci(baris.work_item_id, period.id)]: Math.max(0, Number(event.target.value || 0)),
                            }))
                          }
                          aria-label={`Target ${baris.uraian_pekerjaan} ${period.nama_periode}`}
                        />
                        {target > 0 && (
                          <span className="block px-1.5 pb-1 text-right text-[10px] tabular-nums text-muted" title="Bobot rencana (%)">
                            {angka(bobotRencana(target, baris.volume, baris.bobot), 2)}%
                          </span>
                        )}
                      </td>
                    )
                  })}
                  <td className={`num ${sisa < -TOLERANSI ? 'text-danger' : sisa > TOLERANSI ? 'text-warning' : 'text-success'}`}>
                    {angka(sisa, 2)}
                    {sisa < -TOLERANSI && <span className="block text-[10px]">Melebihi volume</span>}
                  </td>
                  <td className="num">{angka(totalBobot, 2)}</td>
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
                  {angka(item.bobot, 2)}
                </td>
              ))}
              <td />
              <td className="num">{angka(totalBobotRencana, 2)}</td>
            </tr>
            <tr className="font-semibold">
              <td colSpan={4} className="text-right">
                RENCANA KOMULATIF (%)
              </td>
              {ringkasan.map((item) => (
                <td key={item.period_id} className="num">
                  {angka(item.kumulatif, 2)}
                </td>
              ))}
              <td />
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      <div className="mt-3 space-y-1 text-[11px] text-muted">
        <p>
          Bobot rencana per periode = (target volume / volume pekerjaan) × bobot pekerjaan. Total target volume seluruh periode harus sama
          dengan volume pekerjaan (Sisa = 0) dan tidak boleh melebihinya.
        </p>
        <p>
          Total bobot rencana {angka(totalBobotRencana, 2)}% dari total bobot pekerjaan {angka(data.total_bobot_pekerjaan, 2)}%.
        </p>
        {barisMelebihi.length > 0 && (
          <p className="font-medium text-danger">
            Target volume melebihi volume pekerjaan pada: {barisMelebihi.map((baris) => baris.uraian_pekerjaan).join(', ')}. Perbaiki
            sebelum menyimpan.
          </p>
        )}
      </div>
    </Card>
  )
}
