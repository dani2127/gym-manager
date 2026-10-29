"use client";

import { ArrowLeft, Check, Printer, ReceiptText } from "lucide-react";
import Link from "next/link";
import { useState, type ReactNode } from "react";
import useSWR, { useSWRConfig } from "swr";

import { MemberAvatar } from "@/components/members/member-avatar";
import { membershipLeft } from "@/components/billing/membership-line";
import { planSummary } from "@/components/billing/plan-summary";
import { openReceipt } from "@/components/billing/receipt";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch, swrFetcher } from "@/lib/api";
import { addisToday, birrToCents, centsToBirr, formatDate, formatMoney } from "@/lib/format";
import { handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Fee, Invoice, Member, Membership, Plan, Quote } from "@/lib/types";

type Method = "cash" | "pos_card" | "transfer" | "later";

/**
 * The desk sale: pick a plan, start date, fees and discount, take the money.
 * Every change re-prices on the server, so the preview is exactly what the
 * invoice will say.
 */
export function SaleFlow({ memberId }: { memberId: string }) {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.billing.sale;

  const { data: memberData } = useSWR<{ data: Member }>(`/members/${memberId}`, swrFetcher);
  const { data: plansData } = useSWR<{ data: Plan[] }>("/plans?active=1", swrFetcher);
  const { data: feesData } = useSWR<{ data: Fee[] }>("/fees", swrFetcher);
  const [result, setResult] = useState<{ invoice: Invoice; change: number } | null>(null);

  if (!branch || !handlesMoneyAt(me, branch.id)) return <Alert tone="error">{dict.common.notAllowed}</Alert>;
  if (!memberData || !plansData || !feesData) return <div className="h-96 animate-pulse rounded-[6px] bg-surface" />;

  const member = memberData.data;

  return (
    <div className="space-y-6">
      {!result && (
        <Link href={`/members/${member.id}`} className="inline-flex items-center gap-1.5 text-sm font-medium text-graphite hover:text-ink">
          <ArrowLeft aria-hidden className="h-4 w-4" />
          {t.back}
        </Link>
      )}
      {result ? (
        <SaleDone invoice={result.invoice} change={result.change} member={member} />
      ) : (
        <SaleForm
          member={member}
          branchId={branch.id}
          plans={plansData.data}
          fees={feesData.data.filter((f) => f.is_active)}
          onSold={(invoice, change) => setResult({ invoice, change })}
        />
      )}
    </div>
  );
}

function SaleForm({
  member,
  branchId,
  plans,
  fees,
  onSold,
}: {
  member: Member;
  branchId: string;
  plans: Plan[];
  fees: Fee[];
  onSold: (invoice: Invoice, change: number) => void;
}) {
  const { locale, dict } = useDictionary();
  const t = dict.billing.sale;
  const { mutate } = useSWRConfig();
  const { busy, error: saleError, general, run } = useFormAction();

  const [planId, setPlanId] = useState<string | null>(plans[0]?.id ?? null);
  const [startsOn, setStartsOn] = useState<string | null>(null);
  // Null until the desk touches them: then the server's suggestions apply.
  const [chosenFees, setChosenFees] = useState<string[] | null>(null);
  const [chosenDiscount, setChosenDiscount] = useState<{ id: string | null } | null>(null);
  const [promoInput, setPromoInput] = useState("");
  const [promo, setPromo] = useState<string | null>(null);
  const [promoError, setPromoError] = useState<string | null>(null);
  const [checkingPromo, setCheckingPromo] = useState(false);
  const [proofChecked, setProofChecked] = useState(false);
  const [method, setMethod] = useState<Method>("cash");
  const [cashReceived, setCashReceived] = useState("");
  const [slip, setSlip] = useState("");
  const [channel, setChannel] = useState<"telebirr" | "bank_transfer">("telebirr");
  const [quote, setQuote] = useState<Quote | undefined>(undefined);

  // Until the desk chooses, follow the server: the registration fee for
  // newcomers and the group discount the member is entitled to. A quote for
  // another plan suggests nothing, so a stale discount is never sent.
  const quoteMatchesPlan = quote !== undefined && (quote.lines.find((l) => l.kind === "plan")?.id ?? null) === planId;
  const feeIds = chosenFees ?? quote?.suggested_fees ?? [];
  const discountId = chosenDiscount ? chosenDiscount.id : quoteMatchesPlan ? (quote.available_discounts.find((d) => d.automatic)?.id ?? null) : null;

  const payload = {
    member: member.id,
    plan: planId,
    fees: feeIds,
    starts_on: planId ? startsOn : null,
    ...(promo ? { promo_code: promo } : { discount: discountId }),
  };

  useSWR<Quote, ApiError>(["quote", branchId, JSON.stringify(payload)], () => apiFetch<Quote>(`/branches/${branchId}/sales/quote`, { json: payload }), {
    revalidateOnFocus: false,
    onSuccess: setQuote,
  });
  const { data: memberships } = useSWR<{ data: Membership[] }>(`/members/${member.id}/memberships`, swrFetcher);
  const current = memberships?.data.find((m) => m.state === "current");

  async function applyPromo(code: string) {
    setPromoError(null);
    setCheckingPromo(true);
    try {
      await apiFetch<Quote>(`/branches/${branchId}/sales/quote`, {
        json: { member: member.id, plan: planId, fees: feeIds, starts_on: payload.starts_on, promo_code: code },
      });
      setPromo(code);
    } catch (e) {
      setPromoError(e instanceof ApiError ? (e.field("promo_code") ?? e.message) : dict.common.error);
    } finally {
      setCheckingPromo(false);
    }
  }

  const selectedDiscount = promo ? quote?.discount : quote?.available_discounts.find((d) => d.id === discountId);
  const total = quote?.total_cents ?? 0;
  const received = birrToCents(cashReceived);
  const paying = method === "later" ? 0 : method === "cash" && received !== null ? Math.min(received, total) : total;
  const change = method === "cash" && received !== null ? Math.max(0, received - total) : 0;
  const owed = total - paying;
  const canSubmit = Boolean(quote) && (planId !== null || feeIds.length > 0) && !(method === "transfer" && !slip.trim()) && !(selectedDiscount?.requires_proof && !promo && !proofChecked);

  function choosePlan(id: string | null) {
    setPlanId(id);
    setStartsOn(null);
    setChosenDiscount(null);
    setPromo(null);
    setProofChecked(false);
  }

  function submit() {
    run(async () => {
      const { data } = await apiFetch<{ data: Invoice }>(`/branches/${branchId}/sales`, {
        json: {
          ...payload,
          discount_proof_checked: proofChecked,
          payment:
            method === "later"
              ? null
              : { method: method === "transfer" ? channel : method, amount_cents: paying, reference: method !== "cash" && slip.trim() ? slip.trim() : null },
        },
      });
      await Promise.all([mutate(`/members/${member.id}/memberships`), mutate(`/members/${member.id}`)]);
      onSold(data, change);
    });
  }

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem]">
      <div className="space-y-6">
        {/* Who */}
        <section className="flex items-center gap-4 rounded-[6px] border border-line bg-surface p-4">
          <MemberAvatar member={member} />
          <div className="min-w-0 flex-1">
            <h1 className="truncate text-xl font-bold tracking-tight">{t.title}</h1>
            <p className="truncate text-sm text-graphite">
              <span className="tabular font-semibold text-ink">#{member.member_no}</span> {member.full_name} ·{" "}
              {current ? format(t.current, { plan: current.plan.name, left: membershipLeft(current, dict, locale) }) : t.noCurrent}
            </p>
          </div>
        </section>

        {general && <Alert tone="error">{general}</Alert>}

        <Step number="01" title={t.stepPlan}>
          <div role="radiogroup" aria-label={t.stepPlan} className="grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-3">
            {plans.map((plan) => (
              <PlanTicket key={plan.id} plan={plan} selected={planId === plan.id} onSelect={() => choosePlan(plan.id)} />
            ))}
            <button
              type="button"
              role="radio"
              aria-checked={planId === null}
              onClick={() => choosePlan(null)}
              className="cursor-pointer rounded-[6px] border border-dashed border-line-strong p-4 text-left transition-colors hover:border-ink aria-checked:border-solid aria-checked:border-ink aria-checked:bg-surface"
            >
              <span className="block font-semibold">{t.noPlan}</span>
              <span className="mt-1 block text-xs text-graphite">{t.noPlanHint}</span>
            </button>
          </div>
          {saleError?.field("plan") && <p className="mt-2 text-xs font-medium text-critical">{saleError.field("plan")}</p>}
        </Step>

        {planId && (
          <Step number="02" title={t.stepStart}>
            <div className="flex flex-wrap items-end gap-4">
              <label className="block">
                <span className="mb-1 block text-sm font-semibold">{t.startsOn}</span>
                <Input
                  type="date"
                  className="w-48"
                  value={startsOn ?? quote?.starts_on ?? ""}
                  min={addisToday()}
                  onChange={(e) => setStartsOn(e.target.value || null)}
                />
              </label>
              <p className="pb-2.5 text-sm text-graphite">
                {quote?.ends_on ? format(t.runsUntil, { date: formatDate(quote.ends_on, locale) }) : quote ? t.noExpiry : ""}
              </p>
            </div>
            {quote && current && !startsOn && quote.starts_on === quote.suggested_start && quote.suggested_start > addisToday() && (
              <p className="mt-2 text-xs text-graphite">{format(t.renewalNote, { date: formatDate(current.ends_on, locale) })}</p>
            )}
            {saleError?.field("starts_on") && <p className="mt-2 text-xs font-medium text-critical">{saleError.field("starts_on")}</p>}
          </Step>
        )}

        {fees.length > 0 && (
          <Step number={planId ? "03" : "02"} title={t.stepFees}>
            <ul className="divide-y divide-line rounded-[6px] border border-line bg-surface">
              {fees.map((fee) => {
                const checked = feeIds.includes(fee.id);
                return (
                  <li key={fee.id}>
                    <label className="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2 text-sm">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => setChosenFees(checked ? feeIds.filter((x) => x !== fee.id) : [...feeIds, fee.id])}
                        className="h-4 w-4 accent-[var(--track)]"
                      />
                      <span className="flex-1 font-medium">{fee.name}</span>
                      <span className="tabular text-graphite">{formatMoney(fee.amount_cents, locale)}</span>
                    </label>
                  </li>
                );
              })}
            </ul>
          </Step>
        )}

        {planId && (
          <Step number={fees.length > 0 ? "04" : "03"} title={t.stepDiscount}>
            <div role="radiogroup" aria-label={t.stepDiscount} className="divide-y divide-line rounded-[6px] border border-line bg-surface">
              <DiscountOption
                label={t.noDiscount}
                checked={!promo && discountId === null}
                onSelect={() => {
                  setPromo(null);
                  setChosenDiscount({ id: null });
                }}
              />
              {quote?.available_discounts.map((d) => (
                <DiscountOption
                  key={d.id}
                  label={d.name}
                  tag={d.automatic ? t.automatic : d.requires_proof ? t.needsId : undefined}
                  amount={`−${formatMoney(d.amount_cents, locale)}`}
                  checked={!promo && discountId === d.id}
                  onSelect={() => {
                    setPromo(null);
                    setChosenDiscount({ id: d.id });
                    setProofChecked(false);
                  }}
                />
              ))}
              <div className="flex flex-wrap items-center gap-2 px-4 py-3">
                <span className="text-sm font-medium">{t.promoLabel}</span>
                {promo && quote?.discount ? (
                  <>
                    <span className="rounded-full bg-infield-soft px-2.5 py-0.5 font-mono text-xs font-semibold text-infield">{promo.toUpperCase()}</span>
                    <span className="tabular ml-auto text-sm text-infield">−{formatMoney(quote.discount.amount_cents, locale)}</span>
                    <button type="button" className="cursor-pointer text-xs font-semibold text-graphite hover:text-critical" onClick={() => setPromo(null)}>
                      {t.removePromo}
                    </button>
                  </>
                ) : (
                  <form
                    className="flex w-full gap-2 sm:ml-auto sm:w-auto"
                    onSubmit={(e) => {
                      e.preventDefault();
                      if (promoInput.trim()) applyPromo(promoInput.trim());
                    }}
                  >
                    <Input
                      aria-label={t.promoLabel}
                      placeholder={t.promoPlaceholder}
                      value={promoInput}
                      onChange={(e) => setPromoInput(e.target.value)}
                      className="h-9 min-w-0 flex-1 font-mono uppercase sm:w-36 sm:flex-none"
                      aria-invalid={promoError ? true : undefined}
                    />
                    <Button type="submit" variant="secondary" className="min-h-9 py-1" busy={checkingPromo}>
                      {t.apply}
                    </Button>
                  </form>
                )}
              </div>
              {promoError && <p className="px-4 pb-3 text-xs font-medium text-critical">{promoError}</p>}
            </div>
            {selectedDiscount?.requires_proof && !promo && (
              <label className="mt-3 flex items-center gap-2 rounded-[6px] border border-amber/40 bg-amber-soft px-4 py-3 text-sm font-semibold">
                <input type="checkbox" checked={proofChecked} onChange={(e) => setProofChecked(e.target.checked)} className="h-4 w-4 accent-[var(--track)]" />
                {t.proofCheck}
              </label>
            )}
            {saleError?.field("discount_proof_checked") && <p className="mt-2 text-xs font-medium text-critical">{saleError.field("discount_proof_checked")}</p>}
          </Step>
        )}

        <Step number={String((planId ? 3 : 1) + (fees.length > 0 ? 2 : 1)).padStart(2, "0")} title={t.stepPayment}>
          <div role="radiogroup" aria-label={t.stepPayment} className="grid grid-cols-2 gap-1 rounded-[6px] bg-surface-2 p-1 sm:grid-cols-4">
            {(["cash", "pos_card", "transfer", "later"] as const).map((m) => (
              <button
                key={m}
                type="button"
                role="radio"
                aria-checked={method === m}
                onClick={() => setMethod(m)}
                className="min-h-11 cursor-pointer rounded-[5px] text-sm font-semibold text-graphite transition-colors aria-checked:bg-surface aria-checked:text-ink aria-checked:shadow-sm"
              >
                {m === "transfer" ? dict.transfers.desk.transfer : dict.billing.methods[m]}
              </button>
            ))}
          </div>

          <div className="mt-4">
            {method === "transfer" && (
              <div className="grid max-w-xl gap-3 sm:grid-cols-[10rem_1fr]">
                <label className="block">
                  <span className="mb-1 block text-sm font-semibold">{dict.transfers.desk.channel}</span>
                  <select
                    value={channel}
                    onChange={(e) => setChannel(e.target.value as "telebirr" | "bank_transfer")}
                    className="h-10 w-full rounded-[6px] border border-line-strong bg-surface px-3 text-sm"
                  >
                    <option value="telebirr">{dict.billing.methods.telebirr}</option>
                    <option value="bank_transfer">{dict.billing.methods.bank_transfer}</option>
                  </select>
                </label>
                <label className="block">
                  <span className="mb-1 block text-sm font-semibold">{dict.transfers.desk.reference}</span>
                  <Input value={slip} onChange={(e) => setSlip(e.target.value)} maxLength={64} className="font-mono uppercase" aria-invalid={saleError?.field("payment.reference") || saleError?.field("reference") ? true : undefined} />
                  <span className={`mt-1 block text-xs ${saleError?.field("payment.reference") || saleError?.field("reference") ? "font-medium text-critical" : "text-graphite"}`}>
                    {saleError?.field("payment.reference") ?? saleError?.field("reference") ?? dict.transfers.desk.referenceHint}
                  </span>
                </label>
              </div>
            )}
            {method === "cash" && (
              <div className="space-y-3">
                <div className="flex flex-wrap items-end gap-2">
                  <label className="block">
                    <span className="mb-1 block text-sm font-semibold">{t.cashReceived}</span>
                    <Input
                      inputMode="decimal"
                      className="tabular w-40 text-lg"
                      placeholder={centsToBirr(total)}
                      value={cashReceived}
                      onChange={(e) => setCashReceived(e.target.value)}
                    />
                  </label>
                  {quickNotes(total).map((cents, i) => (
                    <button
                      key={cents}
                      type="button"
                      onClick={() => setCashReceived(centsToBirr(cents))}
                      className="tabular min-h-10 cursor-pointer rounded-[6px] border border-line-strong bg-surface px-3 text-sm font-semibold hover:border-ink"
                    >
                      {i === 0 ? t.exact : formatMoney(cents, locale).replace(/\.00/, "")}
                    </button>
                  ))}
                </div>
                {change > 0 && (
                  <p className="text-sm">
                    {t.change}: <span className="font-bib tabular text-2xl text-infield">{formatMoney(change, locale)}</span>
                  </p>
                )}
                {owed > 0 && received !== null && <Alert>{format(t.partNote, { amount: formatMoney(owed, locale) })}</Alert>}
              </div>
            )}
            {method === "pos_card" && (
              <label className="block max-w-xs">
                <span className="mb-1 block text-sm font-semibold">{t.slip}</span>
                <Input value={slip} onChange={(e) => setSlip(e.target.value)} maxLength={64} aria-invalid={saleError?.field("reference") ? true : undefined} />
                <span className={`mt-1 block text-xs ${saleError?.field("reference") ? "font-medium text-critical" : "text-graphite"}`}>
                  {saleError?.field("reference") ?? t.slipHint}
                </span>
              </label>
            )}
            {method === "later" && <p className="text-sm text-graphite">{t.laterNote}</p>}
            {saleError?.field("payment.amount_cents") && <p className="mt-2 text-xs font-medium text-critical">{saleError.field("payment.amount_cents")}</p>}
          </div>
        </Step>
      </div>

      <ReceiptPreview quote={quote} paying={paying} owed={owed}>
        <Button className="mt-5 w-full min-h-12 text-base" disabled={!canSubmit} busy={busy} onClick={submit}>
          {busy ? t.processing : paying > 0 ? format(t.charge, { amount: formatMoney(paying, locale) }) : t.issue}
        </Button>
      </ReceiptPreview>
    </div>
  );
}

/** Exact amount plus the next round notes up: 3,000 → 3,000 · 3,500 · 4,000 · 5,000. */
function quickNotes(total: number): number[] {
  if (total <= 0) return [];
  const birr = total / 100;
  const ups = [500, 1000, 5000].map((step) => Math.ceil(birr / step) * step * 100).filter((c) => c > total);
  return [total, ...new Set(ups)].slice(0, 4);
}

function Step({ number, title, children }: { number: string; title: string; children: ReactNode }) {
  return (
    <section>
      <h2 className="mb-3 flex items-baseline gap-3 text-base font-semibold">
        <span className="font-bib tabular text-sm text-track">{number}</span>
        {title}
      </h2>
      {children}
    </section>
  );
}

function PlanTicket({ plan, selected, onSelect }: { plan: Plan; selected: boolean; onSelect: () => void }) {
  const { locale, dict } = useDictionary();

  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      onClick={onSelect}
      className="group relative cursor-pointer overflow-hidden rounded-[6px] border border-line bg-surface p-3 pl-4 text-left sm:p-4 sm:pl-5 transition-colors hover:border-ink aria-checked:border-ink aria-checked:ring-2 aria-checked:ring-ink"
    >
      <span aria-hidden className="absolute inset-y-0 left-0 w-1.5 bg-line group-aria-checked:bg-track" />
      <span className="block font-semibold">{plan.name}</span>
      <span className="mt-0.5 block text-xs text-graphite">
        {planSummary(plan, dict)}
        {plan.access_scope === "all_branches" && ` · ${dict.billing.pricing.accessTypes.all_branches}`}
      </span>
      <span className="font-bib tabular mt-2 block text-xl sm:mt-3 sm:text-2xl">{formatMoney(plan.price_cents, locale).replace(/\.00/, "")}</span>
      {selected && <Check aria-hidden className="absolute top-3 right-3 h-4 w-4 text-track" />}
    </button>
  );
}

function DiscountOption({ label, tag, amount, checked, onSelect }: { label: string; tag?: string; amount?: string; checked: boolean; onSelect: () => void }) {
  return (
    <label className="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2 text-sm">
      <input type="radio" checked={checked} onChange={onSelect} className="h-4 w-4 accent-[var(--track)]" />
      <span className="flex-1 font-medium">
        {label}
        {tag && <span className="ml-2 rounded-full bg-surface-2 px-2 py-0.5 text-xs font-semibold text-graphite">{tag}</span>}
      </span>
      {amount && <span className="tabular text-infield">{amount}</span>}
    </label>
  );
}

/** The receipt as it will print, re-priced by the server on every change. */
function ReceiptPreview({ quote, paying, owed, children }: { quote: Quote | undefined; paying: number; owed: number; children: ReactNode }) {
  const { locale, dict } = useDictionary();
  const t = dict.billing.sale;

  return (
    <aside className="lg:sticky lg:top-6">
      <div className="overflow-hidden rounded-[6px] border border-line bg-surface shadow-[0_1px_0_var(--line)]">
        <div className="h-1.5 bg-track" aria-hidden />
        <div className="p-5">
          <h2 className="flex items-center gap-2 text-sm font-semibold text-graphite">
            <ReceiptText aria-hidden className="h-4 w-4" />
            {t.receipt}
          </h2>
          {!quote ? (
            <div className="mt-4 h-40 animate-pulse rounded-[6px] bg-surface-2" />
          ) : quote.lines.length === 0 ? (
            <p className="mt-4 text-sm text-graphite">{t.nothing}</p>
          ) : (
            <>
              <ul className="mt-4 space-y-2 text-sm">
                {quote.lines.map((line) => (
                  <li key={`${line.kind}-${line.id}`}>
                    <div className="flex justify-between gap-3">
                      <span>{line.description}</span>
                      <span className="tabular">{formatMoney(line.unit_price_cents, locale)}</span>
                    </div>
                    {line.discount_cents > 0 && (
                      <div className="flex justify-between gap-3 text-infield">
                        <span className="pl-3 text-xs">{quote.discount?.name ?? t.discount}</span>
                        <span className="tabular">−{formatMoney(line.discount_cents, locale)}</span>
                      </div>
                    )}
                  </li>
                ))}
              </ul>
              <dl className="mt-4 space-y-1.5 border-t border-dashed border-line-strong pt-3 text-sm">
                {quote.tax.enabled && (
                  <>
                    <Row label={format(t.before, { name: dict.billing.taxNames[quote.tax.name] })} value={formatMoney(quote.net_cents, locale)} muted />
                    <Row label={format(t.taxLine, { name: dict.billing.taxNames[quote.tax.name], rate: String(quote.tax.rate) })} value={formatMoney(quote.tax_cents, locale)} muted />
                  </>
                )}
              </dl>
              <div className="mt-3 flex items-end justify-between border-t-2 border-ink pt-3">
                <span className="text-sm font-semibold">{t.total}</span>
                <span className="font-bib tabular text-3xl leading-none">{formatMoney(quote.total_cents, locale)}</span>
              </div>
              {owed > 0 && quote.total_cents > 0 && (
                <dl className="mt-3 space-y-1 text-sm">
                  <Row label={t.toPay} value={formatMoney(paying, locale)} />
                  <Row label={t.owed} value={formatMoney(owed, locale)} amber />
                </dl>
              )}
              <p className="mt-3 text-xs text-graphite">
                {!quote.tax.enabled ? t.noTax : quote.tax.inclusive ? format(t.inclusiveNote, { name: dict.billing.taxNames[quote.tax.name] }) : format(t.exclusiveNote, { name: dict.billing.taxNames[quote.tax.name] })}
              </p>
            </>
          )}
          {children}
        </div>
      </div>
    </aside>
  );
}

function Row({ label, value, muted, amber }: { label: string; value: string; muted?: boolean; amber?: boolean }) {
  return (
    <div className={`flex justify-between gap-3 ${muted ? "text-graphite" : ""} ${amber ? "font-semibold text-amber" : ""}`}>
      <dt>{label}</dt>
      <dd className="tabular">{value}</dd>
    </div>
  );
}

function SaleDone({ invoice, change, member }: { invoice: Invoice; change: number; member: Member }) {
  const { locale, dict } = useDictionary();
  const t = dict.billing.sale;
  const [printError, setPrintError] = useState(false);
  const print = (formatName: "a5" | "thermal") => openReceipt(invoice.id, formatName).catch(() => setPrintError(true));

  return (
    <section className="mx-auto max-w-xl overflow-hidden rounded-[6px] border border-line bg-surface">
      <div className="h-2 bg-infield" aria-hidden />
      <div className="space-y-5 p-6 text-center sm:p-8">
        <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-infield-soft text-infield">
          <Check aria-hidden className="h-7 w-7" />
        </span>
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{t.doneTitle}</h1>
          <p className="mt-1 text-sm text-graphite">
            {format(t.doneBody, { number: invoice.number })} · {dict.billing.invoiceStatus[invoice.status]} · {member.full_name}
          </p>
        </div>
        <p className="font-bib tabular text-5xl">{formatMoney(invoice.total_cents, locale)}</p>
        {change > 0 && <Alert tone="success">{format(t.changeDue, { amount: formatMoney(change, locale) })}</Alert>}
        {invoice.balance_cents > 0 && <Alert>{format(dict.billing.memberships.owed, { amount: formatMoney(invoice.balance_cents, locale), number: invoice.number })}</Alert>}
        {printError && <Alert tone="error">{dict.common.error}</Alert>}
        <div className="flex flex-wrap justify-center gap-3">
          <Button onClick={() => print("a5")}>
            <Printer aria-hidden className="h-4 w-4" />
            {t.printA5}
          </Button>
          <Button variant="secondary" onClick={() => print("thermal")}>
            {t.printThermal}
          </Button>
        </div>
        <div className="flex flex-wrap justify-center gap-5 text-sm font-semibold">
          <Link href={`/invoices/${invoice.id}`} className="text-track-deep hover:underline">
            {t.viewInvoice}
          </Link>
          <Link href={`/members/${member.id}`} className="text-track-deep hover:underline">
            {t.backToMember}
          </Link>
        </div>
      </div>
    </section>
  );
}
