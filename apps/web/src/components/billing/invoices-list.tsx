"use client";

import Link from "next/link";
import { useState } from "react";
import useSWRInfinite from "swr/infinite";

import { InvoiceStatusBadge } from "@/components/billing/badges";
import { TakingsStrip } from "@/components/billing/takings";
import { useDebounced } from "@/components/members/use-debounced";
import { Alert, Button, Card, Input, PageHeader, Select } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { addisToday, formatDate, formatDateTime, formatMoney } from "@/lib/format";
import { handlesMoney, handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Invoice, Paginated } from "@/lib/types";

/** Invoices at the selected branch, filtered by day and status, with the day's takings. */
export function InvoicesList() {
  const { locale, dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.billing.invoices;
  const [date, setDate] = useState(addisToday());
  const [status, setStatus] = useState("");
  const [query, setQuery] = useState("");
  const search = useDebounced(query.trim());

  const params = new URLSearchParams({ per_page: "25" });
  if (branch) params.set("branch", branch.id);
  if (search) params.set("search", search);
  else if (date) params.set("date", date);
  if (status) params.set("status", status);

  const allowed = handlesMoney(me) && handlesMoneyAt(me, branch?.id);
  const { data, size, setSize, isValidating } = useSWRInfinite<Paginated<Invoice>>(
    (index, previous) => (!allowed || (previous && index >= previous.meta.last_page) ? null : `/invoices?${params.toString()}&page=${index + 1}`),
    swrFetcher,
  );

  if (!allowed || !branch) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  const invoices = data?.flatMap((page) => page.data) ?? [];
  const last = data?.[data.length - 1]?.meta;

  return (
    <>
      <PageHeader title={t.title} description={branch.name} />
      <div className="space-y-6">
        {!search && (
          <Card title={`${t.takings} · ${formatDate(date, locale)}`}>
            <TakingsStrip branchId={branch.id} date={date} />
          </Card>
        )}

        <div className="flex flex-wrap items-end gap-3">
          <Input type="search" aria-label={t.search} placeholder={t.search} value={query} onChange={(e) => setQuery(e.target.value)} className="w-56" />
          <label className="block">
            <span className="sr-only">{t.date}</span>
            <Input type="date" value={date} max={addisToday()} onChange={(e) => setDate(e.target.value)} disabled={Boolean(search)} className="w-44" />
          </label>
          <label className="block">
            <span className="sr-only">{t.statusFilter}</span>
            <Select value={status} onChange={(e) => setStatus(e.target.value)} className="w-40">
              <option value="">{t.all}</option>
              <option value="unpaid">{t.unpaid}</option>
              <option value="paid">{dict.billing.invoiceStatus.paid}</option>
              <option value="void">{dict.billing.invoiceStatus.void}</option>
            </Select>
          </label>
        </div>

        <div className="overflow-hidden rounded-[6px] border border-line bg-surface">
          {!data ? (
            <div className="h-48 animate-pulse bg-surface-2" />
          ) : invoices.length === 0 ? (
            <p className="p-6 text-sm text-graphite">{t.empty}</p>
          ) : (
            <table className="w-full text-sm">
              <thead className="border-b border-line bg-surface-2 text-left text-xs font-semibold text-graphite">
                <tr>
                  <th className="px-4 py-2.5">{t.number}</th>
                  <th className="hidden px-4 py-2.5 sm:table-cell">{t.member}</th>
                  <th className="hidden px-4 py-2.5 md:table-cell">{t.issued}</th>
                  <th className="px-4 py-2.5 text-right">{t.total}</th>
                  <th className="px-4 py-2.5 text-right">{t.statusFilter}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {invoices.map((invoice) => (
                  <tr key={invoice.id} className="hover:bg-surface-2">
                    <td className="px-4 py-3">
                      <Link href={`/invoices/${invoice.id}`} className="tabular font-semibold hover:text-track-deep hover:underline">
                        {invoice.number}
                      </Link>
                      <span className="block text-xs text-graphite sm:hidden">{invoice.member?.full_name ?? t.walkIn}</span>
                    </td>
                    <td className="hidden px-4 py-3 sm:table-cell">
                      {invoice.member ? (
                        <>
                          <span className="tabular text-graphite">#{invoice.member.member_no}</span> {invoice.member.full_name}
                        </>
                      ) : (
                        <span className="text-graphite">{t.walkIn}</span>
                      )}
                    </td>
                    <td className="tabular hidden px-4 py-3 text-graphite md:table-cell">{formatDateTime(invoice.issued_at, locale)}</td>
                    <td className="tabular px-4 py-3 text-right">
                      {formatMoney(invoice.total_cents, locale)}
                      {invoice.balance_cents > 0 && <span className="block text-xs font-semibold text-amber">{formatMoney(invoice.balance_cents, locale)}</span>}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <InvoiceStatusBadge status={invoice.status} label={dict.billing.invoiceStatus[invoice.status]} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
        {last && size < last.last_page && (
          <Button variant="secondary" busy={isValidating} onClick={() => setSize(size + 1)}>
            {t.loadMore}
          </Button>
        )}
      </div>
    </>
  );
}
