"use client";

import { FileText } from "lucide-react";
import useSWR from "swr";

import { InvoiceStatusBadge, MembershipStateBadge } from "@/components/billing/badges";
import { useMember } from "@/components/member/use-member";
import { useDictionary } from "@/i18n/dictionary-context";
import { API_URL, swrFetcher } from "@/lib/api";
import { formatDate, formatDateTime, formatMoney } from "@/lib/format";
import type { Invoice, Membership } from "@/lib/types";

/** Membership tab: memberships, receipts (PDF) and recent visits. */
export function MemberMembership() {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.membership;
  const { signedOut } = useMember();
  const { data: memberships } = useSWR<{ data: Membership[] }>(signedOut ? null : "/me/member/memberships", swrFetcher);
  const { data: invoices } = useSWR<{ data: Invoice[] }>(signedOut ? null : "/me/member/invoices", swrFetcher);
  const { data: visits } = useSWR<{ data: { id: string; branch: string; checked_in_at: string }[] }>(signedOut ? null : "/me/member/visits", swrFetcher);

  return (
    <div className="space-y-6">
      <h1 className="font-bib text-4xl">{t.title}</h1>

      <Section title={t.memberships} empty={t.noMemberships} items={memberships?.data}>
        {(m) => (
          <li key={m.id} className="flex items-center justify-between gap-3 py-3">
            <span>
              <span className="block font-semibold">{m.plan.name}</span>
              <span className="tabular block text-xs text-graphite">
                {formatDate(m.starts_on, locale)}
                {m.ends_on && ` – ${formatDate(m.ends_on, locale)}`}
              </span>
            </span>
            <MembershipStateBadge state={m.state} label={dict.billing.memberships.states[m.state]} />
          </li>
        )}
      </Section>

      <Section title={t.invoices} empty={t.noInvoices} items={invoices?.data}>
        {(invoice) => (
          <li key={invoice.id}>
            <a
              href={`${API_URL}/api/v1/me/member/invoices/${invoice.id}/receipt`}
              target="_blank"
              rel="noopener"
              className="flex items-center gap-3 py-3 hover:text-track-deep"
            >
              <FileText aria-hidden className="h-5 w-5 shrink-0 text-graphite" />
              <span className="min-w-0 flex-1">
                <span className="tabular block font-semibold">{invoice.number}</span>
                <span className="block text-xs text-graphite">{formatDate(invoice.issued_at, locale)}</span>
              </span>
              <span className="flex flex-col items-end gap-1">
                <span className="tabular text-sm">{formatMoney(invoice.total_cents, locale)}</span>
                <InvoiceStatusBadge status={invoice.status} label={dict.billing.invoiceStatus[invoice.status]} />
              </span>
            </a>
          </li>
        )}
      </Section>

      <Section title={t.visits} empty={t.noVisits} items={visits?.data}>
        {(v) => (
          <li key={v.id} className="flex justify-between gap-3 py-2.5 text-sm">
            <span>{v.branch}</span>
            <span className="tabular text-graphite">{formatDateTime(v.checked_in_at, locale)}</span>
          </li>
        )}
      </Section>
    </div>
  );
}

function Section<T>({ title, empty, items, children }: { title: string; empty: string; items: T[] | undefined; children: (item: T) => React.ReactNode }) {
  return (
    <section className="rounded-[10px] border border-line bg-surface px-5 py-4">
      <h2 className="text-sm font-semibold text-graphite">{title}</h2>
      {!items ? (
        <div className="mt-3 h-12 animate-pulse rounded-[6px] bg-surface-2" />
      ) : items.length === 0 ? (
        <p className="mt-2 text-sm text-graphite">{empty}</p>
      ) : (
        <ul className="divide-y divide-line">{items.map(children)}</ul>
      )}
    </section>
  );
}
