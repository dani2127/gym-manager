"use client";

import { Plus } from "lucide-react";
import Link from "next/link";
import useSWR from "swr";

import { InvoiceStatusBadge, MembershipStateBadge } from "@/components/billing/badges";
import { membershipLeft } from "@/components/billing/membership-line";
import { FreezePanel } from "@/components/memberships/freeze-panel";
import { Card } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatDate, formatMoney } from "@/lib/format";
import { handlesMoney, handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Invoice, Member, Membership, Paginated } from "@/lib/types";

const LIVE = new Set(["current", "upcoming", "pending_payment", "frozen"]);

/** Memberships on the member profile: what they have now, what is queued, and history. */
export function MembershipsCard({ member }: { member: Member }) {
  const { locale, dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.billing.memberships;
  const { data, mutate } = useSWR<{ data: Membership[] }>(`/members/${member.id}/memberships`, swrFetcher);

  const memberships = data?.data ?? [];
  const live = memberships.filter((m) => LIVE.has(m.state)).sort((a, b) => a.starts_on.localeCompare(b.starts_on));
  const past = memberships.filter((m) => !LIVE.has(m.state));
  const canSell = handlesMoneyAt(me, branch?.id) && member.status !== "banned";

  return (
    <Card
      title={t.title}
      actions={
        canSell && (
          <Link
            href={`/members/${member.id}/sell`}
            className="inline-flex min-h-9 items-center gap-1.5 rounded-[6px] bg-track px-3 text-sm font-semibold text-lane hover:bg-track-deep"
          >
            <Plus aria-hidden className="h-4 w-4" />
            {live.some((m) => m.state === "current" || m.state === "frozen") ? t.renew : t.sell}
          </Link>
        )
      }
    >
      {!data ? (
        <div className="h-24 animate-pulse rounded-[6px] bg-surface-2" />
      ) : memberships.length === 0 ? (
        <p className="text-sm text-graphite">{t.none}</p>
      ) : (
        <div className="space-y-4">
          {live.map((m) => (
            <LiveMembership key={m.id} membership={m} onChanged={() => mutate()} />
          ))}
          {past.length > 0 && (
            <div>
              <h3 className="mb-2 text-xs font-semibold text-graphite">{t.history}</h3>
              <ul className="divide-y divide-line rounded-[6px] border border-line text-sm">
                {past.map((m) => (
                  <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                    <span className="font-medium">{m.plan.name}</span>
                    <span className="flex items-center gap-3 text-xs text-graphite">
                      <span className="tabular">{dateRange(m, locale, dict)}</span>
                      <MembershipStateBadge state={m.state} label={t.states[m.state]} />
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </Card>
  );
}

function dateRange(m: Membership, locale: "en" | "am", dict: ReturnType<typeof useDictionary>["dict"]) {
  return m.ends_on
    ? format(dict.billing.memberships.range, { from: formatDate(m.starts_on, locale), to: formatDate(m.ends_on, locale) })
    : formatDate(m.starts_on, locale);
}

function LiveMembership({ membership: m, onChanged }: { membership: Membership; onChanged: () => void }) {
  const { locale, dict } = useDictionary();
  const { me } = useSession();
  const t = dict.billing.memberships;
  const owed = m.invoice && m.invoice.balance_cents > 0 ? m.invoice : null;

  // Share of the time pass already used, drawn as a lane.
  const totalDays = m.ends_on ? Math.round((Date.parse(m.ends_on) - Date.parse(m.starts_on)) / 86_400_000) + 1 : null;
  const used = m.state === "current" && totalDays && m.days_left !== null ? Math.min(1, Math.max(0, (totalDays - m.days_left) / totalDays)) : m.state === "upcoming" ? 0 : null;
  const visitsUsed = m.visits_total ? (m.visits_total - (m.visits_remaining ?? 0)) / m.visits_total : null;
  const progress = visitsUsed ?? used;

  return (
    <div className="rounded-[6px] border border-line p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-semibold">{m.plan.name}</p>
          <p className="tabular mt-0.5 text-xs text-graphite">{dateRange(m, locale, dict)}</p>
        </div>
        <MembershipStateBadge state={m.state} label={t.states[m.state]} />
      </div>
      {m.state !== "pending_payment" && (
        <>
          <p className="font-bib tabular mt-3 text-2xl">{membershipLeft(m, dict, locale)}</p>
          {progress !== null && (
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-surface-2" aria-hidden>
              <div className="h-full rounded-full bg-track" style={{ width: `${Math.round((1 - progress) * 100)}%` }} />
            </div>
          )}
        </>
      )}
      {m.status !== "pending_payment" && <FreezePanel membership={m} onChanged={onChanged} />}
      {owed && (
        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-[6px] bg-amber-soft px-3 py-2 text-sm text-amber">
          <span className="font-medium">{format(t.owed, { amount: formatMoney(owed.balance_cents, locale), number: owed.number })}</span>
          {handlesMoney(me) && (
            <Link href={`/invoices/${owed.id}`} className="font-semibold underline-offset-2 hover:underline">
              {t.takePayment}
            </Link>
          )}
        </div>
      )}
    </div>
  );
}

/** The member's last invoices, for staff who handle money. */
export function MemberInvoicesCard({ member }: { member: Member }) {
  const { locale, dict } = useDictionary();
  const { me } = useSession();
  const t = dict.billing.memberships;
  const allowed = handlesMoney(me);
  const { data } = useSWR<Paginated<Invoice>>(allowed ? `/invoices?member=${member.id}&per_page=5` : null, swrFetcher);

  if (!allowed) return null;

  return (
    <Card title={t.invoices}>
      {!data ? (
        <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
      ) : data.data.length === 0 ? (
        <p className="text-sm text-graphite">{t.noInvoices}</p>
      ) : (
        <ul className="divide-y divide-line text-sm">
          {data.data.map((invoice) => (
            <li key={invoice.id}>
              <Link href={`/invoices/${invoice.id}`} className="flex items-center justify-between gap-3 py-2 hover:text-track-deep">
                <span>
                  <span className="tabular block font-semibold">{invoice.number}</span>
                  <span className="text-xs text-graphite">{formatDate(invoice.issued_at, locale)}</span>
                </span>
                <span className="flex flex-col items-end gap-1">
                  <span className="tabular">{formatMoney(invoice.total_cents, locale)}</span>
                  <InvoiceStatusBadge status={invoice.status} label={dict.billing.invoiceStatus[invoice.status]} />
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
