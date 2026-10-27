"use client";

import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes } from "react";
import { useId } from "react";

const BUTTON_STYLES = {
  primary: "bg-track text-lane hover:bg-track-deep",
  secondary: "border border-line-strong bg-surface text-ink hover:border-ink",
  danger: "border border-critical/40 bg-surface text-critical hover:bg-critical-soft",
} as const;

export function Button({
  variant = "primary",
  busy = false,
  className = "",
  children,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: keyof typeof BUTTON_STYLES; busy?: boolean }) {
  return (
    <button
      {...props}
      disabled={props.disabled || busy}
      aria-busy={busy || undefined}
      className={`inline-flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-[6px] px-4 py-2 text-sm font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-55 ${BUTTON_STYLES[variant]} ${className}`}
    >
      {children}
    </button>
  );
}

/** Label + control + hint/error, with ids wired for screen readers. */
export function Field({
  label,
  hint,
  error,
  children,
}: {
  label: string;
  hint?: string;
  error?: string;
  children: (props: { id: string; "aria-invalid"?: boolean; "aria-describedby"?: string }) => ReactNode;
}) {
  const id = useId();
  const describedBy = error || hint ? `${id}-desc` : undefined;

  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm font-semibold">
        {label}
      </label>
      {children({ id, "aria-invalid": error ? true : undefined, "aria-describedby": describedBy })}
      {(error || hint) && (
        <p id={describedBy} className={`text-xs ${error ? "font-medium text-critical" : "text-graphite"}`}>
          {error ?? hint}
        </p>
      )}
    </div>
  );
}

const CONTROL =
  "h-10 w-full rounded-[6px] border border-line-strong bg-surface px-3 text-sm text-ink outline-none transition-colors placeholder:text-graphite/70 focus:border-ink disabled:bg-surface-2 disabled:text-graphite aria-invalid:border-critical";

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`${CONTROL} ${props.className ?? ""}`} />;
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={`${CONTROL} ${props.className ?? ""}`} />;
}

export function Toggle({
  label,
  description,
  checked,
  disabled,
  onChange,
}: {
  label: string;
  description?: string;
  checked: boolean;
  disabled?: boolean;
  onChange: (checked: boolean) => void;
}) {
  const id = useId();

  return (
    <div className="flex items-start justify-between gap-4 py-3">
      <label htmlFor={id} className="text-sm">
        <span className="font-medium">{label}</span>
        {description && <span className="mt-1 block text-xs leading-relaxed text-graphite">{description}</span>}
      </label>
      <input
        id={id}
        type="checkbox"
        role="switch"
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
        className="relative h-6 w-11 shrink-0 cursor-pointer appearance-none rounded-full bg-line-strong transition-colors duration-150 before:absolute before:top-0.5 before:left-0.5 before:h-5 before:w-5 before:rounded-full before:bg-white before:shadow-sm before:transition-transform before:duration-150 checked:bg-infield checked:before:translate-x-5 disabled:cursor-not-allowed disabled:opacity-50"
      />
    </div>
  );
}

export function Card({ title, actions, children }: { title?: string; actions?: ReactNode; children: ReactNode }) {
  return (
    <section className="rounded-[6px] border border-line bg-surface p-5">
      {(title || actions) && (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          {title && <h2 className="text-base font-semibold">{title}</h2>}
          {actions}
        </div>
      )}
      {children}
    </section>
  );
}

export function Alert({ tone = "info", children }: { tone?: "info" | "error" | "success"; children: ReactNode }) {
  const styles = {
    info: "border-line bg-surface-2 text-ink",
    error: "border-critical/30 bg-critical-soft text-critical",
    success: "border-infield/30 bg-infield-soft text-infield",
  }[tone];

  return (
    <div role={tone === "error" ? "alert" : "status"} className={`rounded-[6px] border px-4 py-3 text-sm ${styles}`}>
      {children}
    </div>
  );
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
        {description && <p className="mt-1 text-sm text-graphite">{description}</p>}
      </div>
      {actions}
    </div>
  );
}
