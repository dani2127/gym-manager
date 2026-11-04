"use client";

import { CheckCircle2, XCircle } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import useSWR from "swr";

import { MemberAvatar } from "@/components/members/member-avatar";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Input, PageHeader } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { API_URL, apiFetch, swrFetcher } from "@/lib/api";
import { getEcho } from "@/lib/echo";
import { birrToCents, centsToBirr, formatDateTime, formatMoney } from "@/lib/format";
import { handlesMoney, handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { TransferReview } from "@/lib/types";

type Queue = { data: TransferReview[]; meta: { waiting: number } };

/** The desk's list of Telebirr and bank transfers to check. */
export function TransferQueue() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.transfers.queue;
  const [tab, setTab] = useState<"waiting" | "reviewed">("waiting");
  const allowed = handlesMoney(me) && handlesMoneyAt(me, branch?.id);
  const key = allowed && branch ? `/payments/transfers?branch=${branch.id}${tab === "reviewed" ? "&status=reviewed" : ""}` : null;
  const { data, mutate } = useSWR<Queue>(key, swrFetcher, { refreshInterval: 20_000 });

  // New transfers arrive live on the branch's desk channel.
  useEffect(() => {
    const echo = getEcho();
    if (!echo || !branch) return;
    echo.private(`branch.${branch.id}.desk`).listen(".payment.submitted", () => void mutate()).listen(".payment.reviewed", () => void mutate());
    return () => echo.leave(`branch.${branch.id}.desk`);
  }, [branch, mutate]);

  if (!allowed) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader title={t.title} description={t.description} />
      <div className="space-y-5">
        <div role="tablist" className="inline-grid grid-cols-2 gap-1 rounded-[6px] bg-surface-2 p-1">
          {(["waiting", "reviewed"] as const).map((name) => (
            <button
              key={name}
              type="button"
              role="tab"
              aria-selected={tab === name}
              onClick={() => setTab(name)}
              className="min-h-10 cursor-pointer rounded-[5px] px-5 text-sm font-semibold text-graphite aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-sm"
            >
              {t[name]}
              {name === "waiting" && data && tab === "waiting" && data.meta.waiting > 0 && <span className="ml-2 rounded-full bg-track px-2 py-0.5 text-xs text-white">{data.meta.waiting}</span>}
            </button>
          ))}
        </div>

        {!data ? (
          <div className="h-64 animate-pulse rounded-[6px] bg-surface" />
        ) : data.data.length === 0 ? (
          <p className="rounded-[6px] border border-line bg-surface p-6 text-sm text-graphite">{tab === "waiting" ? t.empty : t.emptyReviewed}</p>
        ) : (
          <div className="grid gap-4 xl:grid-cols-2">
            {data.data.map((p) => (
              <TransferCard key={p.id} payment={p} onDone={() => mutate()} />
            ))}
          </div>
        )}
      </div>
    </>
  );
}

function TransferCard({ payment: p, onDone }: { payment: TransferReview; onDone: () => void }) {
  const { locale, dict } = useDictionary();
  const t = dict.transfers.queue;
  const { busy, error, general, run } = useFormAction();
  const [mode, setMode] = useState<"idle" | "reject" | "amount">("idle");
  const proof = p.proof_url ? `${API_URL}/api/v1${p.proof_url}` : null;
  const waiting = p.status === "awaiting_verification";

  function approve(amount?: number) {
    run(async () => {
      await apiFetch(`/payments/${p.id}/approve`, { json: amount ? { amount_cents: amount } : {} });
      onDone();
    });
  }

  function reject(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "");
    run(async () => {
      await apiFetch(`/payments/${p.id}/reject`, { json: { reason } });
      onDone();
    });
  }

  return (
    <article className="grid overflow-hidden rounded-[6px] border border-line bg-surface sm:grid-cols-[11rem_1fr]">
      <a href={proof ?? undefined} target="_blank" rel="noopener" className="block bg-surface-2" aria-label={t.viewProof}>
        {proof && (
          // eslint-disable-next-line @next/next/no-img-element -- private screenshot served by the API with the staff session
          <img src={proof} alt="" className="h-56 w-full object-cover object-top sm:h-full" />
        )}
      </a>
      <div className="space-y-3 p-4">
        <div className="flex items-center gap-3">
          {p.member && <MemberAvatar member={p.member} size="sm" />}
          <div className="min-w-0">
            <p className="truncate font-semibold">{p.member?.full_name}</p>
            <p className="tabular text-xs text-graphite">
              #{p.member?.member_no} · {format(t.invoice, { number: p.invoice.number })}
            </p>
          </div>
        </div>
        <dl className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt className="text-xs font-semibold text-graphite">{t.amount}</dt>
            <dd className="font-bib tabular text-2xl">{formatMoney(p.amount_cents, locale)}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold text-graphite">{dict.billing.methods[p.method]}</dt>
            <dd className="font-mono text-base font-semibold break-all">{p.reference}</dd>
          </div>
        </dl>
        <p className="text-xs text-graphite">
          {format(t.sent, { time: formatDateTime(p.submitted_at, locale) })}
          {waiting && p.invoice.balance_cents !== p.amount_cents && ` · ${format(t.owed, { amount: formatMoney(p.invoice.balance_cents, locale) })}`}
        </p>

        {(general || error?.field("amount_cents") || error?.field("payment")) && (
          <Alert tone="error">{error?.field("amount_cents") ?? error?.field("payment") ?? general}</Alert>
        )}

        {!waiting ? (
          <p className={`flex items-start gap-1.5 text-sm font-semibold ${p.status === "completed" ? "text-infield" : "text-critical"}`}>
            {p.status === "completed" ? <CheckCircle2 aria-hidden className="mt-0.5 h-4 w-4" /> : <XCircle aria-hidden className="mt-0.5 h-4 w-4" />}
            {p.status === "completed" ? format(t.approved, { name: p.verified_by ?? "" }) : format(t.rejected, { name: p.verified_by ?? "", reason: p.rejection_reason ?? "" })}
          </p>
        ) : mode === "reject" ? (
          <form onSubmit={reject} className="space-y-2" noValidate>
            <label className="block text-sm font-semibold">
              {t.rejectReason}
              <Input name="reason" autoFocus placeholder={t.rejectPlaceholder} className="mt-1" aria-invalid={error?.field("reason") ? true : undefined} />
            </label>
            {error?.field("reason") && <p className="text-xs font-medium text-critical">{error.field("reason")}</p>}
            <div className="flex gap-2">
              <Button type="submit" variant="danger" busy={busy}>
                {t.confirmReject}
              </Button>
              <Button type="button" variant="secondary" onClick={() => setMode("idle")}>
                {dict.common.cancel}
              </Button>
            </div>
          </form>
        ) : mode === "amount" ? (
          <form
            onSubmit={(e) => {
              e.preventDefault();
              approve(birrToCents(String(new FormData(e.currentTarget).get("amount") ?? "")) ?? undefined);
            }}
            className="flex gap-2"
          >
            <Input name="amount" inputMode="decimal" defaultValue={centsToBirr(p.amount_cents)} className="tabular" aria-label={t.amount} />
            <Button type="submit" busy={busy}>
              {t.approve}
            </Button>
          </form>
        ) : (
          <div className="flex flex-wrap items-center gap-2">
            <Button busy={busy} onClick={() => approve()}>
              <CheckCircle2 aria-hidden className="h-4 w-4" />
              {t.approve}
            </Button>
            <Button variant="danger" onClick={() => setMode("reject")}>
              {t.reject}
            </Button>
            <button type="button" onClick={() => setMode("amount")} className="cursor-pointer text-xs font-semibold text-graphite hover:text-ink">
              {t.approveAmount}
            </button>
          </div>
        )}
      </div>
    </article>
  );
}
