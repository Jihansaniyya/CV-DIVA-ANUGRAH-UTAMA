import { cn } from '@/utils/cn'
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

interface LabelWrapProps {
  label?: string
  error?: string
  hint?: string
  required?: boolean
  className?: string
  children: ReactNode
}

export function FieldWrap({ label, error, hint, required, className, children }: LabelWrapProps) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {label && (
        <label className="text-xs font-medium text-ink">
          {label}
          {required && <span className="ml-0.5 text-primary">*</span>}
        </label>
      )}
      {children}
      {hint && !error && <p className="text-[11px] text-muted">{hint}</p>}
      {error && <p className="text-[11px] font-medium text-danger">{error}</p>}
    </div>
  )
}

const BASE =
  'w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink placeholder:text-muted/70 transition-colors focus:border-primary disabled:bg-surface disabled:text-muted'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
  wrapClassName?: string
}

export function Input({ label, error, hint, required, wrapClassName, className, ...props }: InputProps) {
  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName}>
      <input
        className={cn(BASE, error ? 'border-danger' : 'border-line', className)}
        aria-invalid={Boolean(error)}
        required={required}
        {...props}
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

export function Select({ label, error, hint, required, wrapClassName, className, children, ...props }: SelectProps) {
  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName}>
      <select className={cn(BASE, error ? 'border-danger' : 'border-line', className)} required={required} {...props}>
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

export function Textarea({ label, error, hint, required, wrapClassName, className, ...props }: TextareaProps) {
  return (
    <FieldWrap label={label} error={error} hint={hint} required={required} className={wrapClassName}>
      <textarea
        rows={3}
        className={cn(BASE, 'resize-y', error ? 'border-danger' : 'border-line', className)}
        required={required}
        {...props}
      />
    </FieldWrap>
  )
}

/** Input tanggal memakai kontrol bawaan browser agar tetap nyaman di mobile. */
export function DatePicker(props: InputProps) {
  return <Input type="date" {...props} />
}
