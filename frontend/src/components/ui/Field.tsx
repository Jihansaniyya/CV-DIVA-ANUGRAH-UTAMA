import { cn } from '@/utils/cn'
import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'

interface LabelWrapProps {
  label?: string
  error?: string
  hint?: string
  required?: boolean
  className?: string
  /** id kontrol yang diberi label; label, petunjuk, dan pesan error dihubungkan ke kontrol ini. */
  htmlFor?: string
  children: ReactNode
}

/** id elemen petunjuk/error milik kontrol `id`, untuk aria-describedby. */
const idPetunjuk = (id: string) => `${id}-petunjuk`
const idError = (id: string) => `${id}-error`

/** Gabungkan aria-describedby milik kontrol dengan id petunjuk/error yang sedang tampil. */
function describedBy(id: string, { hint, error }: { hint?: string; error?: string }, bawaan?: string): string | undefined {
  const daftar = [bawaan, error ? idError(id) : hint ? idPetunjuk(id) : undefined].filter(Boolean)

  return daftar.length > 0 ? daftar.join(' ') : undefined
}

export function FieldWrap({ label, error, hint, required, className, htmlFor, children }: LabelWrapProps) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {label && (
        <label htmlFor={htmlFor} className="text-xs font-medium text-ink">
          {label}
          {required && (
            <span className="ml-0.5 text-primary" aria-hidden>
              *
            </span>
          )}
        </label>
      )}
      {children}
      {hint && !error && (
        <p id={htmlFor ? idPetunjuk(htmlFor) : undefined} className="text-[11px] text-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={htmlFor ? idError(htmlFor) : undefined} className="text-[11px] font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  )
}

/** id kontrol: id dari pemanggil bila ada, selain itu id unik otomatis. */
function useIdKontrol(id?: string): string {
  const otomatis = useId()

  return id ?? otomatis
}

const BASE =
  'w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink placeholder:text-muted/70 transition-colors disabled:bg-surface disabled:text-muted'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
  wrapClassName?: string
}

export function Input({ label, error, hint, required, wrapClassName, className, id, ...props }: InputProps) {
  const idKontrol = useIdKontrol(id)

  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName} htmlFor={idKontrol}>
      <input
        id={idKontrol}
        className={cn(BASE, error ? 'border-danger' : 'border-line focus:border-navy-light/45', className)}
        aria-invalid={Boolean(error)}
        required={required}
        {...props}
        aria-describedby={describedBy(idKontrol, { hint, error }, props['aria-describedby'])}
      />
    </FieldWrap>
  )
}

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string
  error?: string
  hint?: string
  wrapClassName?: string
  children: ReactNode
}

export function Select({ label, error, hint, required, wrapClassName, className, children, id, ...props }: SelectProps) {
  const idKontrol = useIdKontrol(id)

  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName} htmlFor={idKontrol}>
      <select
        id={idKontrol}
        className={cn(BASE, error ? 'border-danger' : 'border-line focus:border-navy-light/45', className)}
        aria-invalid={Boolean(error)}
        required={required}
        {...props}
        aria-describedby={describedBy(idKontrol, { hint, error }, props['aria-describedby'])}
      >
        {children}
      </select>
    </FieldWrap>
  )
}

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
  hint?: string
  wrapClassName?: string
}

export function Textarea({ label, error, hint, required, wrapClassName, className, id, ...props }: TextareaProps) {
  const idKontrol = useIdKontrol(id)

  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName} htmlFor={idKontrol}>
      <textarea
        id={idKontrol}
        rows={3}
        className={cn(BASE, 'resize-y', error ? 'border-danger' : 'border-line focus:border-navy-light/45', className)}
        aria-invalid={Boolean(error)}
        required={required}
        {...props}
        aria-describedby={describedBy(idKontrol, { hint, error }, props['aria-describedby'])}
      />
    </FieldWrap>
  )
}

/** Input tanggal memakai kontrol bawaan browser agar tetap nyaman di mobile. */
export function DatePicker(props: InputProps) {
  return <Input type="date" {...props} />
}
