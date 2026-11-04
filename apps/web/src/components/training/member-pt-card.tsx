"use client";

import { Plus } from "lucide-react";
import Link from "next/link";
import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { ScheduleForm, SessionRow } from "@/components/training/pt-sessions";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, Select } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { formatDate, formatMoney } from "@/lib/format";
import { handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Invoice, Member, PtEnrollment, PtPackage, TrainerProfile } from "@/lib/types";

/** On the member page: their PT packages and sessions, and selling a new package. */
export function PersonalTrainingCard({ member }: { member: Member }) {
  const { locale, dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.training;
  const { data, mutate } = useSWR<{ data: PtEnrollment[] }>(`/members/${member.id}/pt-enrollments`, swrFetcher);
  const [selling, setSelling] = useState(false);
  const [scheduling, setScheduling] = useState<string | null>(null);
  const canSell = handlesMoneyAt(me, branch?.id) && member.status !== "banned";
  const enrollments = (data?.data ?? []).filter((e) => e.status !== "cancelled");

  if (data && enrollments.length === 0 && !canSell) return null;

  return (
    <Card
      title={t.title}
      actions={
        canSell &&
        !selling && (
          <Button variant="secondary" className="min-h-9" onClick={() => setSelling(true)}>
            <Plus aria-hidden className="h-4 w-4" />
            {t.sell}
          </Button>
        )
      }
    >
      {selling && branch && <SellForm member={member} branchId={branch.id} onDone={() => { setSelling(false); mutate(); }} onCancel={() => setSelling(false)} />}
      {!data ? (
        <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
      ) : enrollments.length === 0 ? (
        !selling && <p className="text-sm text-graphite">{t.none}</p>
      ) : (
        <div className="space-y-4">
          {enrollments.map((e) => (
            <div key={e.id} className="rounded-[6px] border border-line p-4">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-semibold">{e.package}</p>
                  <p className="text-xs text-graphite">{[e.trainer?.name, e.ends_on ? format(t.until, { date: formatDate(e.ends_on, locale) }) : null].filter(Boolean).join(" · ")}</p>
                </div>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${e.status === "active" ? "bg-infield-soft text-infield" : "bg-surface-2 text-graphite"}`}>{t.statuses[e.status]}</span>
              </div>
              <p className="font-bib tabular mt-3 text-2xl">{format(t.left, { n: String(e.sessions_remaining), total: String(e.sessions_total) })}</p>
              {e.invoice && e.invoice.balance_cents > 0 && (
                <Link href={`/invoices/${e.invoice.id}`} className="mt-2 block rounded-[6px] bg-amber-soft px-3 py-2 text-sm font-medium text-amber">
                  {t.awaiting} · {formatMoney(e.invoice.balance_cents, locale)} · {e.invoice.number}
                </Link>
              )}
              {(e.upcoming ?? []).length > 0 && (
                <ul className="mt-3 divide-y divide-line border-t border-line">
                  {e.upcoming!.map((s) => (
                    <SessionRow key={s.id} session={s} onChanged={() => mutate()} />
                  ))}
                </ul>
              )}
              {e.status === "active" && e.sessions_remaining - e.sessions_scheduled > 0 && (
                <div className="mt-3 border-t border-line pt-3">
                  {scheduling === e.id ? (
                    <ScheduleForm enrollmentId={e.id} onDone={() => { setScheduling(null); mutate(); }} />
                  ) : (
                    <button type="button" onClick={() => setScheduling(e.id)} className="cursor-pointer text-sm font-semibold text-track-deep hover:underline">
                      + {t.schedule}
                    </button>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

function SellForm({ member, branchId, onDone, onCancel }: { member: Member; branchId: string; onDone: () => void; onCancel: () => void }) {
  const { locale, dict } = useDictionary();
  const t = dict.training;
  const { data: packages } = useSWR<{ data: PtPackage[] }>("/pt-packages", swrFetcher);
  const { data: trainers } = useSWR<{ data: TrainerProfile[] }>(`/branches/${branchId}/trainers`, swrFetcher);
  const active = (packages?.data ?? []).filter((p) => p.is_active);
  const [packageId, setPackageId] = useState("");
  const [trainerId, setTrainerId] = useState("");
  const [method, setMethod] = useState<"cash" | "pos_card" | "telebirr" | "bank_transfer" | "later">("cash");
  const [sold, setSold] = useState<Invoice | null>(null);
  const { busy, error, general, run } = useFormAction();
  const chosen = active.find((p) => p.id === (packageId || active[0]?.id));
  const trainer = chosen?.trainer?.id ?? trainerId;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!chosen) return;
    const reference = String(new FormData(event.currentTarget).get("reference") ?? "").trim();
    run(async () => {
      const res = await apiFetch<{ data: Invoice }>(`/branches/${branchId}/pt-sales`, {
        json: {
          member: member.id,
          package: chosen.id,
          trainer: trainer || null,
          payment: method === "later" ? null : { method, amount_cents: chosen.price_cents, reference: method === "cash" ? null : reference || null },
        },
      });
      setSold(res.data);
    });
  }

  if (sold)
    return (
      <div className="mb-4 space-y-3">
        <Alert tone="success">{format(t.sold, { number: sold.number })}</Alert>
        <Button onClick={onDone}>{dict.common.close}</Button>
      </div>
    );

  return (
    <form onSubmit={submit} className="mb-4 space-y-4 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t.package} error={error?.field("package")}>
          {(p) => (
            <Select {...p} value={chosen?.id ?? ""} onChange={(e) => setPackageId(e.target.value)}>
              {active.map((x) => (
                <option key={x.id} value={x.id}>
                  {x.name} · {formatMoney(x.price_cents, locale)}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.trainer} error={error?.field("trainer")}>
          {(p) => (
            <Select {...p} value={trainer} disabled={Boolean(chosen?.trainer)} onChange={(e) => setTrainerId(e.target.value)}>
              {!chosen?.trainer && <option value="">{t.chooseTrainer}</option>}
              {chosen?.trainer ? (
                <option value={chosen.trainer.id}>{chosen.trainer.name}</option>
              ) : (
                (trainers?.data ?? []).map((x) => (
                  <option key={x.id} value={x.id}>
                    {x.name}
                  </option>
                ))
              )}
            </Select>
          )}
        </Field>
        <Field label={t.method}>
          {(p) => (
            <Select {...p} value={method} onChange={(e) => setMethod(e.target.value as typeof method)}>
              {(["cash", "pos_card", "telebirr", "bank_transfer"] as const).map((m) => (
                <option key={m} value={m}>
                  {dict.billing.methods[m]}
                </option>
              ))}
              <option value="later">{t.payLater}</option>
            </Select>
          )}
        </Field>
        {method !== "cash" && method !== "later" && (
          <Field label={t.reference} error={error?.field("payment.reference")}>
            {(p) => <Input {...p} name="reference" className="font-mono uppercase" />}
          </Field>
        )}
      </div>
      {chosen && (
        <p className="text-sm">
          {format(t.packageLine, { sessions: String(chosen.sessions_count), days: String(chosen.validity_days) })} · <b className="tabular">{t.total} {formatMoney(chosen.price_cents, locale)}</b>
        </p>
      )}
      <div className="flex gap-3">
        <Button type="submit" busy={busy} disabled={!chosen || !trainer}>
          {t.confirmSale}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
      </div>
    </form>
  );
}
