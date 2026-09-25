import { cn } from '@/utils/cn'
import { Loader2 } from 'lucide-react'
import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Variant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
type Size = 'sm' | 'md' | 'lg'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
  loading?: boolean
  icon?: ReactNode
  block?: boolean
}

const VARIANT: Record<Variant, string> = {
  primary: 'bg-primary text-white hover:bg-primary-dark disabled:bg-primary/50',
  secondary: 'bg-navy text-white hover:bg-navy-light disabled:bg-navy/50',
  outline: 'border border-line bg-white text-ink hover:bg-surface disabled:text-muted',
  ghost: 'text-muted hover:bg-surface hover:text-ink',
  danger: 'bg-danger text-white hover:bg-[#b91c1c]',
}

const SIZE: Record<Size, string> = {
  sm: 'h-8 px-3 text-xs gap-1.5',
  md: 'h-10 px-4 text-sm gap-2',
  lg: 'h-11 px-5 text-sm gap-2',
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  icon,
  block = false,
  className,
  children,
  disabled,
  type = 'button',
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      className={cn(
        'inline-flex items-center justify-center rounded-lg font-medium transition-colors disabled:cursor-not-allowed',
        VARIANT[variant],
        SIZE[size],
        block && 'w-full',
        className,
      )}
      {...props}
    >
      {loading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : icon}
      {children}
    </button>
  )
}
