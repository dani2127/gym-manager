"use client";

import { Check } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import useSWR from "swr";

import { planSummary } from "@/components/billing/plan-summary";
import { useMember } from "@/components/member/use-member";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch, swrFetcher } from "@/lib/api";
import { formatDate, formatMoney } from "@/lib/format";
import type { PaymentAccount, Plan } from "@/lib/types";

type Shop = { plans: Plan[]; accounts: PaymentAccount[]; open_order: string | null };
type MemberQuote = {
  lines: { kind: string; description: string; unit_price_cents: number; discount_cents: number; total_cents: number }[];
  discount: { name: string; kind: string } | null;
  tax: { enabled: boolean; name: "VAT" | "TOT"; rate: number; inclusive: boolean };
  tax_cents: number;
  total_cents: number;
  starts_on: string | null;
};

/** Renew from the app: pick a plan, see the exact price, then pay by transfer. */
export function MemberBuy() {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.buy;
  const router = useRouter();
  const { signedOut } = useMember();
  const { data: shop } = useSWR<{ data: Shop }>(signedOut ? null : "/me/member/shop", swrFetcher);
  const [planId, setPlanId] = useState<string | null>(null);
  const [promoInput, setPromoInput] = useState("");
  const [promo, setPromo] = useState<string | null>(null);
  const { busy, general, error, run } = useFormAction();
  const plan = planId ?? shop?.data.plans[0]?.id ?? null;

  const { data: quote, error: quoteError } = useSWR<{ data: MemberQuote }, ApiError>(
    plan ? ["member-quote", plan, promo] : null,
    () => apiFetch<{ data: MemberQuote }>("/me/member/shop/quote", { json: { plan, promo_code: promo } }),
    { keepPreviousData: true, revalidateOnFocus: false },
  );

  // An unpaid order already exists: finish paying that one.
  const openOrder = shop?.data.open_order;
  useEffect(() => {
    if (openOrder) router.replace(`/app/pay/${openOrder}`);
  }, [openOrder, router]);

  const q = quote?.data;

  return (
    <div className="space-y-5">
      <h1 className="font-bib text-4xl">{t.title}</h1>
      {general && <Alert tone="error">{general}</Alert>}

      <section>
        <h2 className="mb-2 text-sm font-semibold text-graphite">{t.choose}</h2>
        <div role="radiogroup" aria-label={t.choose} className="space-y-2">
          {!shop
            ? [0, 1, 2].map((i) => <div key={i} className="h-20 animate-pulse rounded-[10px] bg-surface" />)
            : shop.data.plans.map((p) => (
                <button
                  key={p.id}
                  type="button"
                  role="radio"
                  aria-checked={plan === p.id}
                  onClick={() => setPlanId(p.id)}
                  className="flex w-full cursor-pointer items-center gap-4 rounded-[10px] border border-line bg-surface p-4 text-left aria-checked:border-ink aria-checked:ring-2 aria-checked:ring-ink"
                >
                  <span className="min-w-0 flex-1">
                    <span className="block font-semibold">{p.name}</span>
                    <span className="block text-xs text-graphite">{planSummary(p, dict)}</span>
                  </span>
                  <span className="font-bib tabular text-xl">{formatMoney(p.price_cents, locale).replace(/\.00/, "")}</span>
                  {plan === p.id && <Check aria-hidden className="h-5 w-5 text-track" />}
                </button>
              ))}
        </div>
      </section>

      <form
        className="flex gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          setPromo(promoInput.trim() || null);
        }}
      >
        <Input aria-label={t.promo} placeholder={t.promo} value={promoInput} onChange={(e) => setPromoInput(e.target.value)} className="font-mono uppercase" />
        <Button type="submit" variant="secondary">
          {t.apply}
        </Button>
      </form>
      {quoteError?.field("promo_code") && <p className="-mt-3 text-xs font-medium text-critical">{quoteError.field("promo_code")}</p>}

      {q && (
        <section className="space-y-2 rounded-[10px] border border-line bg-surface p-5">
          {q.discount && <p className="text-sm font-semibold text-infield">{format(t.discount, { name: q.discount.name })}</p>}
          {q.lines.some((l) => l.kind === "fee") && <p className="text-sm text-graphite">{t.registration}</p>}
          <div className="flex items-end justify-between border-t border-line pt-3">
            <span className="text-sm font-semibold">{t.total}</span>
            <span className="font-bib tabular text-3xl">{formatMoney(q.total_cents, locale)}</span>
          </div>
          <p className="text-xs text-graphite">
            {q.tax.enabled && format(t.includes, { name: dict.billing.taxNames[q.tax.name], rate: String(q.tax.rate) })}
            {q.starts_on && ` · ${format(t.starts, { date: formatDate(q.starts_on, locale) })}`}
          </p>
        </section>
      )}
      <p className="text-xs text-graphite">{t.studentNote}</p>

      <Button
        className="w-full min-h-12 text-base"
        busy={busy}
        disabled={!q}
        onClick={() =>
          run(async () => {
            const res = await apiFetch<{ data: { id: string } }>("/me/member/orders", { json: { plan, promo_code: promo } });
            router.push(`/app/pay/${res.data.id}`);
          })
        }
      >
        {t.order}
      </Button>
      {error?.field("plan") && <p className="text-xs font-medium text-critical">{error.field("plan")}</p>}
    </div>
  );
}
