import { Card } from '@/components/ui/Card'
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/State'
import { useProgressList } from '@/hooks/queries'
import { pesanError } from '@/lib/api'
import { tanggalSingkat } from '@/utils/format'
import { Link } from 'react-router-dom'

/** Seluruh foto bukti pekerjaan dari laporan progres proyek. */
export function DocumentationTab({ projectId }: { projectId: number }) {
  const { data, isLoading, error, refetch } = useProgressList({ project_id: projectId, per_page: 50 })

  if (isLoading) return <LoadingState />
  if (error) return <ErrorState pesan={pesanError(error)} onRetry={() => void refetch()} />

  const foto = (data?.data ?? []).flatMap((laporan) =>
    (laporan.foto ?? []).map((item) => ({ ...item, laporanId: laporan.id, tanggal: laporan.tanggal_laporan })),
  )

  return (
    <Card title="Dokumentasi Pekerjaan" description={`${foto.length} foto bukti pekerjaan`}>
      {foto.length === 0 ? (
        <EmptyState judul="Belum ada dokumentasi" pesan="Foto yang diunggah QS pada laporan progres akan tampil di sini." />
      ) : (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {foto.map((item) => (
            <li key={item.id} className="app-card overflow-hidden">
              <a href={item.url} target="_blank" rel="noreferrer">
                <img
                  src={item.url}
                  alt={item.caption ?? 'Foto progres'}
                  className="h-36 w-full object-cover transition hover:opacity-90"
                  loading="lazy"
                />
              </a>
              <div className="p-2">
                <p className="line-clamp-2 text-xs text-ink">{item.caption ?? 'Tanpa keterangan'}</p>
                <p className="mt-1 text-[10px] text-muted">
                  {tanggalSingkat(item.tanggal)}
                  {' - '}
                  <Link to={`/progres/${item.laporanId}`} className="text-primary hover:underline">
                    lihat laporan
                  </Link>
                </p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}
