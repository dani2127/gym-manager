"use client";

import useSWR from "swr";

import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatMoney } from "@/lib/format";
import type { Takings } from "@/lib/types";

/** The day's money at a branch: total, split by method, what is still owed. */
export function TakingsStrip({ branchId, date }: { branchId: string; date?: string }) {
  const { locale, dict } = useDictionary();
  const t = dict.billing;
  const { data } = useSWR<Takings>(`/branches/${branchId}/takings${date ? `?date=${date}` : ""}`, swrFetcher, { refreshInterval: 60_000 });

  if (!data) return <div className="h-20 animate-pulse rounded-[6px] bg-surface-2" />;

  return (
    <div className="flex flex-wrap items-end gap-x-8 gap-y-3">
      <div>
        <p className="font-bib tabular text-4xl leading-none">{formatMoney(data.total_cents, locale)}</p>
        <p className="mt-1.5 text-xs text-graphite">
          {format(t.invoices.invoicesCount, { n: String(data.invoices_count) })}
          {data.unpaid_cents > 0 && <span className="font-semibold text-amber"> · {format(t.invoices.unpaidTotal, { amount: formatMoney(data.unpaid_cents, locale) })}</span>}
          {data.refunded_cents > 0 && ` · ${format(t.invoices.refunded, { amount: formatMoney(data.refunded_cents, locale) })}`}
        </p>
      </div>
      <dl className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
        {data.by_method.map((m) => (
          <div key={m.method} className="border-l-2 border-track pl-3">
            <dt className="text-xs font-semibold text-graphite">
              {t.methods[m.method]} · {m.count}
            </dt>
            <dd className="tabular font-semibold">{formatMoney(m.amount_cents, locale)}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
