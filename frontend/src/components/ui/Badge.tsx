import type { ProjectStatus, ReportStatus } from '@/types'
import { cn } from '@/utils/cn'
import type { ReactNode } from 'react'

type Tone = 'success' | 'warning' | 'danger' | 'neutral' | 'info'

const TONE: Record<Tone, string> = {
  success: 'bg-success-soft text-success',
  warning: 'bg-warning-soft text-[#b45309]',
  danger: 'bg-danger-soft text-danger',
  neutral: 'bg-surface text-muted',
  info: 'bg-[#e8edfb] text-navy',
}

export function Badge({ tone = 'neutral', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium whitespace-nowrap',
        TONE[tone],
        className,
      )}
    >
      {children}
    </span>
  )
}

const STATUS_PROYEK: Record<ProjectStatus, { label: string; tone: Tone }> = {
  BELUM_DIMULAI: { label: 'Belum Dimulai', tone: 'neutral' },
  BERJALAN: { label: 'Berjalan', tone: 'warning' },
  SELESAI: { label: 'Selesai', tone: 'success' },
  TERLAMBAT: { label: 'Terlambat', tone: 'danger' },
}

export function StatusBadge({ status }: { status: ProjectStatus }) {
  const info = STATUS_PROYEK[status] ?? STATUS_PROYEK.BELUM_DIMULAI

  return (
    <Badge tone={info.tone}>
      <span className="size-1.5 rounded-full bg-current" aria-hidden />
      {info.label}
    </Badge>
  )
}

export function ReportStatusBadge({ status }: { status: ReportStatus }) {
  return <Badge tone={status === 'DIKIRIM' ? 'success' : 'neutral'}>{status === 'DIKIRIM' ? 'Dikirim' : 'Draf'}</Badge>
}

/** Warna teks deviasi: merah bila tertinggal, hijau bila mendahului, netral bila sesuai rencana. */
export function warnaDeviasi(nilai: number | null | undefined): string {
  if (nilai === null || nilai === undefined) return 'text-muted'
  if (nilai < -0.005) return 'text-danger'
  if (nilai > 0.005) return 'text-success'

  return 'text-ink'
}

/** Badge deviasi: positif berarti realisasi mendahului rencana. */
export function DeviationBadge({ nilai }: { nilai: number | null }) {
  if (nilai === null) {
    return <Badge tone="neutral">Belum ada data</Badge>
  }

  const tone: Tone = nilai < -0.005 ? 'danger' : nilai > 0.005 ? 'success' : 'info'
  const prefix = nilai > 0 ? '+' : ''

  return (
    <Badge tone={tone}>
      {prefix}
      {nilai.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%
    </Badge>
  )
}
