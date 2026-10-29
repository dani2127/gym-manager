import type { InvoiceStatus, MembershipState } from "@/lib/types";

const PILL = "inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold";

const INVOICE_TONES: Record<InvoiceStatus, string> = {
  paid: "bg-infield-soft text-infield",
  partially_paid: "bg-amber-soft text-amber",
  issued: "bg-amber-soft text-amber",
  void: "bg-surface-2 text-graphite line-through ring-1 ring-line ring-inset",
};

export function InvoiceStatusBadge({ status, label }: { status: InvoiceStatus; label: string }) {
  return <span className={`${PILL} ${INVOICE_TONES[status]}`}>{label}</span>;
}

const MEMBERSHIP_TONES: Record<MembershipState, string> = {
  current: "bg-infield-soft text-infield",
  active: "bg-infield-soft text-infield",
  upcoming: "bg-track-soft text-track-deep",
  pending_payment: "bg-amber-soft text-amber",
  frozen: "bg-surface-2 text-ink ring-1 ring-line ring-inset",
  expired: "bg-surface-2 text-graphite ring-1 ring-line ring-inset",
  cancelled: "bg-surface-2 text-graphite line-through ring-1 ring-line ring-inset",
};

export function MembershipStateBadge({ state, label }: { state: MembershipState; label: string }) {
  return <span className={`${PILL} ${MEMBERSHIP_TONES[state]}`}>{label}</span>;
}
