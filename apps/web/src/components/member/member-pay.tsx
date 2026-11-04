"use client";

import { Camera, CheckCircle2, Clock, Copy } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import useSWR, { useSWRConfig } from "swr";

import { useMember } from "@/components/member/use-member";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { birrToCents, centsToBirr, formatMoney } from "@/lib/format";
import type { Invoice, PaymentAccount } from "@/lib/types";

/** Pay an order by Telebirr or bank transfer: where to send it, then the proof. */
export function MemberPay({ id }: { id: string }) {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.pay;
  const router = useRouter();
  const { mutate } = useSWRConfig();
  const { signedOut } = useMember();
  const { data, mutate: reload } = useSWR<{ data: Invoice }>(signedOut ? null : `/me/member/invoices/${id}`, swrFetcher, { refreshInterval: 15_000 });
  const { data: shop } = useSWR<{ data: { accounts: PaymentAccount[] } }>(signedOut ? null : "/me/member/shop", swrFetcher);
  const { busy, error, general, run } = useFormAction();
  const [method, setMethod] = useState<"telebirr" | "bank_transfer">("telebirr");
  const [proof, setProof] = useState<File | null>(null);
  const preview = useMemo(() => (proof ? URL.createObjectURL(proof) : null), [proof]);
  const [copied, setCopied] = useState<string | null>(null);

  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview);
  }, [preview]);

  const invoice = data?.data;
  if (!invoice) return <div className="h-96 animate-pulse rounded-[10px] bg-surface" />;

  const transfers = (invoice.payments ?? []).filter((p) => p.method === "telebirr" || p.method === "bank_transfer");
  const waiting = transfers.find((p) => p.status === "awaiting_verification");
  const lastRejected = !waiting ? transfers.filter((p) => p.status === "rejected").at(-1) : undefined;
  const accounts = shop?.data.accounts ?? [];
  const shown = accounts.filter((a) => (method === "telebirr" ? a.kind === "telebirr" : a.kind === "bank"));

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const body = new FormData();
    body.append("method", method);
    body.append("reference", String(form.get("reference") ?? ""));
    body.append("amount_cents", String(birrToCents(String(form.get("amount") ?? "")) ?? 0));
    if (proof) body.append("proof", proof);
    run(async () => {
      await apiFetch(`/me/member/invoices/${id}/transfers`, { method: "POST", body });
      setProof(null);
      await Promise.all([reload(), mutate("/me/member")]);
    });
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="font-bib text-4xl">{t.title}</h1>
        <p className="tabular text-sm text-graphite">{format(t.order, { number: invoice.number })}</p>
      </div>

      <section className="rounded-[10px] border border-line bg-surface p-5">
        <p className="text-sm font-semibold text-graphite">{t.toPay}</p>
        <p className="font-bib tabular text-5xl">{formatMoney(invoice.balance_cents, locale)}</p>
        <p className="mt-1 text-sm text-graphite">{invoice.items?.map((i) => i.description).join(" + ")}</p>
      </section>

      {invoice.status === "paid" ? (
        <Alert tone="success">{t.paid}</Alert>
      ) : waiting ? (
        <section className="flex gap-3 rounded-[10px] border border-amber/40 bg-amber-soft p-5">
          <Clock aria-hidden className="h-6 w-6 shrink-0 text-amber" />
          <div>
            <p className="font-semibold text-amber">{t.waiting}</p>
            <p className="mt-1 text-sm">{t.waitingBody}</p>
            <p className="tabular mt-2 text-xs text-graphite">
              {dict.billing.methods[waiting.method]} · {waiting.reference} · {formatMoney(waiting.amount_cents, locale)}
            </p>
          </div>
        </section>
      ) : (
        <>
          {lastRejected && (
            <Alert tone="error">
              <span className="block font-semibold">{format(t.rejected, { reason: lastRejected.rejection_reason ?? "" })}</span>
              <span className="mt-1 block">{t.rejectedBody}</span>
            </Alert>
          )}
          <p className="text-sm text-graphite">{t.steps}</p>

          <div role="radiogroup" aria-label={t.method} className="grid grid-cols-2 gap-1 rounded-[6px] bg-surface-2 p-1">
            {(["telebirr", "bank_transfer"] as const).map((m) => (
              <button
                key={m}
                type="button"
                role="radio"
                aria-checked={method === m}
                onClick={() => setMethod(m)}
                className="min-h-11 cursor-pointer rounded-[5px] text-sm font-semibold text-graphite aria-checked:bg-surface aria-checked:text-ink aria-checked:shadow-sm"
              >
                {dict.billing.methods[m]}
              </button>
            ))}
          </div>

          {accounts.length === 0 ? (
            <Alert>{t.noAccounts}</Alert>
          ) : (
            <ul className="space-y-2">
              {shown.map((a) => (
                <li key={a.id} className="flex items-center gap-3 rounded-[10px] border border-line bg-surface p-4">
                  <span className="min-w-0 flex-1">
                    <span className="block text-xs text-graphite">{a.bank_name ?? dict.transfers.accounts.kinds.telebirr}</span>
                    <span className="font-bib tabular block text-2xl">{a.account_number}</span>
                    <span className="block text-sm">{a.account_name}</span>
                  </span>
                  <button
                    type="button"
                    onClick={() => navigator.clipboard?.writeText(a.account_number.replace(/\s/g, "")).then(() => setCopied(a.id))}
                    className="inline-flex min-h-10 cursor-pointer items-center gap-1.5 rounded-[6px] border border-line-strong px-3 text-sm font-semibold"
                  >
                    {copied === a.id ? <CheckCircle2 aria-hidden className="h-4 w-4 text-infield" /> : <Copy aria-hidden className="h-4 w-4" />}
                    {copied === a.id ? t.copied : t.copy}
                  </button>
                </li>
              ))}
            </ul>
          )}

          <form onSubmit={submit} className="space-y-4 rounded-[10px] border border-line bg-surface p-5" noValidate>
            {general && <Alert tone="error">{general}</Alert>}
            <Field label={t.reference} hint={t.referenceHint} error={error?.field("reference")}>
              {(p) => <Input {...p} name="reference" autoCapitalize="characters" autoComplete="off" className="font-mono uppercase" required />}
            </Field>
            <Field label={t.amount} error={error?.field("amount_cents")}>
              {(p) => <Input {...p} name="amount" inputMode="decimal" className="tabular" defaultValue={centsToBirr(invoice.balance_cents)} required />}
            </Field>
            <div className="space-y-2">
              <p className="text-sm font-semibold">{t.screenshot}</p>
              {preview && (
                // eslint-disable-next-line @next/next/no-img-element -- local preview of the chosen screenshot
                <img src={preview} alt="" className="max-h-64 rounded-[6px] border border-line" />
              )}
              <label className="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-[6px] border border-line-strong bg-surface px-4 text-sm font-semibold">
                <Camera aria-hidden className="h-4 w-4" />
                {t.screenshot}
                <input type="file" accept="image/*" className="sr-only" onChange={(e) => setProof(e.target.files?.[0] ?? null)} />
              </label>
              <p className={`text-xs ${error?.field("proof") ? "font-medium text-critical" : "text-graphite"}`}>{error?.field("proof") ?? t.screenshotHint}</p>
            </div>
            <Button type="submit" busy={busy} disabled={!proof} className="w-full min-h-12 text-base">
              {t.submit}
            </Button>
          </form>

          {transfers.length === 0 && invoice.paid_cents === 0 && (
            <button
              type="button"
              onClick={() =>
                run(async () => {
                  await apiFetch(`/me/member/orders/${id}`, { method: "DELETE" });
                  await mutate("/me/member");
                  router.replace("/app");
                })
              }
              className="block w-full cursor-pointer text-center text-sm font-semibold text-graphite hover:text-critical"
            >
              {t.cancel}
            </button>
          )}
        </>
      )}
    </div>
  );
}
