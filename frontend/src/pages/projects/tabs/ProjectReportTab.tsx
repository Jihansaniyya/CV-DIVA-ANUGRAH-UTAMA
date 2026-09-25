import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { useReportDocuments } from '@/hooks/queries'
import { waktu } from '@/utils/format'
import { Download, FileSpreadsheet, FileText } from 'lucide-react'
import { Link } from 'react-router-dom'

const JENIS = [
  { key: 'harian', label: 'Laporan Harian', deskripsi: 'Rekap laporan progres QS per tanggal.' },
  { key: 'mingguan', label: 'Laporan Mingguan', deskripsi: 'Realisasi minggu lalu, minggu ini, dan s/d minggu ini.' },
  { key: 'bulanan', label: 'Laporan Bulanan', deskripsi: 'Jadwal rencana, realisasi, dan deviasi per minggu.' },
  { key: 'milestone', label: 'Laporan Milestone', deskripsi: 'Capaian tahapan penting pada kurva progres.' },
]

export function ProjectReportTab({ projectId }: { projectId: number }) {
  const { data: dokumen } = useReportDocuments(projectId)

  return (
    <div className="flex flex-col gap-4">
      <Card title="Jenis Laporan" description="Pilih jenis laporan untuk melihat pratinjau dan mengekspornya.">
        <ul className="grid gap-3 sm:grid-cols-2">
          {JENIS.map((item) => (
            <li key={item.key} className="app-card flex items-center justify-between gap-3 p-3">
              <div className="min-w-0">
                <p className="text-sm font-medium text-ink">{item.label}</p>
                <p className="text-[11px] text-muted">{item.deskripsi}</p>
              </div>
              <Link to={`/laporan?project_id=${projectId}&jenis=${item.key}`}>
                <Button variant="outline" size="sm">
                  Lihat
                </Button>
              </Link>
            </li>
          ))}
        </ul>
      </Card>

      <Card title="Dokumen yang Pernah Dibuat" bodyClassName="pt-0">
        {!dokumen || dokumen.length === 0 ? (
          <EmptyState judul="Belum ada dokumen" pesan="Dokumen hasil export Excel atau Word akan tercatat di sini." />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Jenis</Th>
                  <Th>Format</Th>
                  <Th>Dibuat</Th>
                  <Th>Oleh</Th>
                  <Th align="center">Unduh</Th>
                </tr>
              </thead>
              <tbody>
                {dokumen.map((item) => (
                  <tr key={item.id} className="hover:bg-surface/60">
                    <Td className="font-medium">{item.tipe_laporan}</Td>
                    <Td>
                      <span className="inline-flex items-center gap-1.5 text-muted">
                        {item.format === 'EXCEL' ? (
                          <FileSpreadsheet className="size-4" aria-hidden />
                        ) : (
                          <FileText className="size-4" aria-hidden />
                        )}
                        {item.format}
                      </span>
                    </Td>
                    <Td className="text-muted">{waktu(item.digenerate_pada)}</Td>
                    <Td className="text-muted">{item.dibuat_oleh ?? '-'}</Td>
                    <Td align="center">
                      <a
                        href={item.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                      >
                        <Download className="size-3.5" aria-hidden />
                        Unduh
                      </a>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>
    </div>
  )
}
