"use client";

import { ArrowLeft, Ban, Printer } from "lucide-react";
import Link from "next/link";
import { useState, type FormEvent } from "react";
import useSWR, { useSWRConfig } from "swr";

import { InvoiceStatusBadge, MembershipStateBadge } from "@/components/billing/badges";
import { openReceipt } from "@/components/billing/receipt";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, Select } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch, swrFetcher } from "@/lib/api";
import { birrToCents, centsToBirr, formatDate, formatDateTime, formatMoney } from "@/lib/format";
import { managesBranch } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Invoice } from "@/lib/types";

export function InvoiceDetail({ id }: { id: string }) {
  const { locale, dict } = useDictionary();
  const { me } = useSession();
  const t = dict.billing.invoices;
  const { data, error, mutate } = useSWR<{ data: Invoice }, ApiError>(`/invoices/${id}`, swrFetcher);
  const [printError, setPrintError] = useState(false);

  if (error) {
    return (
      <div className="space-y-4">
        <BackLink label={t.back} />
        <Alert tone="error">{error.status === 404 ? t.notFound : error.status === 403 ? dict.common.notAllowed : dict.common.error}</Alert>
      </div>
    );
  }
  if (!data) return <div className="h-96 animate-pulse rounded-[6px] bg-surface" />;

  const invoice = data.data;
  const print = (f: "a5" | "thermal") => openReceipt(invoice.id, f).catch(() => setPrintError(true));
  const update = (next: Invoice) => mutate({ data: next }, { revalidate: false });

  return (
    <div className="space-y-6">
      <BackLink label={t.back} />

      <section className="overflow-hidden rounded-[6px] border border-line bg-surface">
        <div className={`h-2 ${invoice.status === "void" ? "bg-line-strong" : invoice.status === "paid" ? "bg-infield" : "bg-amber"}`} aria-hidden />
        <div className="flex flex-wrap items-start justify-between gap-4 p-6">
          <div>
            <p className="font-bib tabular text-3xl sm:text-4xl">{invoice.number}</p>
            <p className="mt-1 text-sm text-graphite">
              {invoice.branch?.name} · {formatDateTime(invoice.issued_at, locale)}
              {invoice.created_by && ` · ${format(t.issuedBy, { name: invoice.created_by })}`}
            </p>
            {invoice.member && (
              <Link href={`/members/${invoice.member.id}`} className="mt-2 inline-block text-sm font-semibold hover:text-track-deep hover:underline">
                <span className="tabular text-graphite">#{invoice.member.member_no}</span> {invoice.member.full_name}
              </Link>
            )}
          </div>
          <div className="flex flex-col items-end gap-3">
            <InvoiceStatusBadge status={invoice.status} label={dict.billing.invoiceStatus[invoice.status]} />
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => print("a5")}>
                <Printer aria-hidden className="h-4 w-4" />
                {dict.billing.sale.printA5}
              </Button>
              <Button variant="secondary" onClick={() => print("thermal")}>
                {dict.billing.sale.printThermal}
              </Button>
            </div>
          </div>
        </div>
        {invoice.status === "void" && (
          <p className="border-t border-line bg-surface-2 px-6 py-3 text-sm text-graphite">
            {format(t.voided, { date: formatDateTime(invoice.voided_at, locale), reason: invoice.void_reason ?? "" })}
          </p>
        )}
      </section>

      {printError && <Alert tone="error">{dict.common.error}</Alert>}

      <div className="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div className="space-y-6">
          <Card title={t.items}>
            <ul className="divide-y divide-line text-sm">
              {invoice.items?.map((item, i) => (
                <li key={i} className="py-2.5">
                  <div className="flex justify-between gap-3">
                    <span className="font-medium">
                      {item.description}
                      {item.qty > 1 && <span className="text-graphite"> × {item.qty}</span>}
                    </span>
                    <span className="tabular">{formatMoney(item.unit_price_cents * item.qty, locale)}</span>
                  </div>
                  {item.discount_cents > 0 && (
                    <div className="flex justify-between gap-3 text-infield">
                      <span className="pl-3 text-xs">{item.discount?.name ?? dict.billing.sale.discount}</span>
                      <span className="tabular">−{formatMoney(item.discount_cents, locale)}</span>
                    </div>
                  )}
                </li>
              ))}
            </ul>
            <dl className="mt-3 space-y-1.5 border-t border-dashed border-line-strong pt-3 text-sm">
              {invoice.tax_name && (
                <>
                  <Line label={format(dict.billing.sale.before, { name: dict.billing.taxNames[invoice.tax_name] })} value={formatMoney(invoice.net_cents, locale)} muted />
                  <Line label={format(dict.billing.sale.taxLine, { name: dict.billing.taxNames[invoice.tax_name], rate: String(invoice.tax_rate) })} value={formatMoney(invoice.tax_cents, locale)} muted />
                </>
              )}
              <div className="flex items-end justify-between border-t-2 border-ink pt-2">
                <dt className="font-semibold">{dict.billing.sale.total}</dt>
                <dd className="font-bib tabular text-2xl">{formatMoney(invoice.total_cents, locale)}</dd>
              </div>
              <Line label={t.paid} value={formatMoney(invoice.paid_cents, locale)} />
              {invoice.balance_cents > 0 && <Line label={t.balanceDue} value={formatMoney(invoice.balance_cents, locale)} amber />}
            </dl>
          </Card>

          {invoice.memberships && invoice.memberships.length > 0 && (
            <Card title={t.membership}>
              {invoice.memberships.map((m) => (
                <div key={m.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                  <span>
                    <span className="font-semibold">{m.plan.name}</span>
                    <span className="tabular block text-xs text-graphite">
                      {formatDate(m.starts_on, locale)}
                      {m.ends_on && ` – ${formatDate(m.ends_on, locale)}`}
                    </span>
                  </span>
                  <MembershipStateBadge state={m.state} label={dict.billing.memberships.states[m.state]} />
                </div>
              ))}
            </Card>
          )}
        </div>

        <div className="space-y-6">
          <Card title={t.payments}>
            {invoice.payments?.length ? (
              <ul className="divide-y divide-line text-sm">
                {invoice.payments.map((p) => (
                  <li key={p.id} className="flex items-start justify-between gap-3 py-2">
                    <span>
                      <span className="font-medium">{dict.billing.methods[p.method]}</span>
                      {p.reference && <span className="font-mono text-xs text-graphite"> · {p.reference}</span>}
                      <span className="block text-xs text-graphite">
                        {formatDateTime(p.created_at, locale)}
                        {p.received_by && ` · ${format(t.receivedBy, { name: p.received_by })}`}
                      </span>
                    </span>
                    <span className={`tabular ${p.status === "refunded" ? "text-graphite line-through" : ""}`}>
                      {formatMoney(p.amount_cents, locale)}
                      {p.status === "refunded" && <span className="block text-right text-xs no-underline">{t.refunded_status}</span>}
                    </span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-graphite">{t.noPayments}</p>
            )}
            {invoice.balance_cents > 0 && <PaymentForm invoice={invoice} onPaid={update} />}
          </Card>

          {invoice.status !== "void" && managesBranch(me, invoice.branch?.id) && <VoidCard invoice={invoice} onVoided={update} />}
        </div>
      </div>
    </div>
  );
}

function BackLink({ label }: { label: string }) {
  return (
    <Link href="/invoices" className="inline-flex items-center gap-1.5 text-sm font-medium text-graphite hover:text-ink">
      <ArrowLeft aria-hidden className="h-4 w-4" />
      {label}
    </Link>
  );
}

function Line({ label, value, muted, amber }: { label: string; value: string; muted?: boolean; amber?: boolean }) {
  return (
    <div className={`flex justify-between gap-3 ${muted ? "text-graphite" : ""} ${amber ? "font-semibold text-amber" : ""}`}>
      <dt>{label}</dt>
      <dd className="tabular">{value}</dd>
    </div>
  );
}

function PaymentForm({ invoice, onPaid }: { invoice: Invoice; onPaid: (invoice: Invoice) => void }) {
  const { dict } = useDictionary();
  const t = dict.billing.invoices;
  const { mutate } = useSWRConfig();
  const { busy, error, general, run } = useFormAction();
  const [method, setMethod] = useState<"cash" | "pos_card">("cash");

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const amount = birrToCents(String(form.get("amount") ?? ""));
    const reference = String(form.get("reference") ?? "").trim();
    run(async () => {
      const { data } = await apiFetch<{ data: Invoice }>(`/invoices/${invoice.id}/payments`, {
        json: { method, amount_cents: amount ?? 0, reference: method === "pos_card" && reference ? reference : null },
      });
      onPaid(data);
      if (data.member) await mutate(`/members/${data.member.id}/memberships`);
    });
  }

  return (
    <form onSubmit={submit} className="mt-4 space-y-3 border-t border-line pt-4" noValidate>
      <h3 className="text-sm font-semibold">{t.takePayment}</h3>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label={dict.billing.sale.stepPayment}>
          {(p) => (
            <Select {...p} value={method} onChange={(e) => setMethod(e.target.value as "cash" | "pos_card")}>
              <option value="cash">{dict.billing.methods.cash}</option>
              <option value="pos_card">{dict.billing.methods.pos_card}</option>
            </Select>
          )}
        </Field>
        <Field label={t.amount} error={error?.field("amount_cents")}>
          {(p) => <Input {...p} name="amount" inputMode="decimal" className="tabular" defaultValue={centsToBirr(invoice.balance_cents)} />}
        </Field>
      </div>
      {method === "pos_card" && (
        <Field label={dict.billing.sale.slip} hint={dict.billing.sale.slipHint} error={error?.field("reference")}>
          {(p) => <Input {...p} name="reference" maxLength={64} />}
        </Field>
      )}
      <Button type="submit" busy={busy}>
        {t.recordPayment}
      </Button>
    </form>
  );
}

function VoidCard({ invoice, onVoided }: { invoice: Invoice; onVoided: (invoice: Invoice) => void }) {
  const { dict } = useDictionary();
  const t = dict.billing.invoices;
  const { mutate } = useSWRConfig();
  const { busy, error, general, run } = useFormAction();
  const [open, setOpen] = useState(false);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "");
    run(async () => {
      const { data } = await apiFetch<{ data: Invoice }>(`/invoices/${invoice.id}/void`, { json: { reason } });
      onVoided(data);
      if (data.member) await mutate(`/members/${data.member.id}/memberships`);
    });
  }

  return (
    <Card>
      {!open ? (
        <Button variant="danger" onClick={() => setOpen(true)}>
          <Ban aria-hidden className="h-4 w-4" />
          {t.void}
        </Button>
      ) : (
        <form onSubmit={submit} className="space-y-3" noValidate>
          {general && <Alert tone="error">{general}</Alert>}
          <p className="text-sm text-graphite">{t.voidHint}</p>
          <Field label={t.voidReason} error={error?.field("reason")}>
            {(p) => <Input {...p} name="reason" required maxLength={255} />}
          </Field>
          <div className="flex gap-3">
            <Button type="submit" variant="danger" busy={busy}>
              {t.voidConfirm}
            </Button>
            <Button type="button" variant="secondary" onClick={() => setOpen(false)}>
              {dict.common.cancel}
            </Button>
          </div>
        </form>
      )}
    </Card>
  );
}
