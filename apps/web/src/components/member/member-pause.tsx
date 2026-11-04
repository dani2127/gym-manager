"use client";

import { Snowflake } from "lucide-react";
import { useState, type FormEvent } from "react";
import { useSWRConfig } from "swr";

import { addDays, openFreeze } from "@/components/memberships/freeze-panel";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { addisToday, formatDate } from "@/lib/format";
import type { Membership } from "@/lib/types";

const LIVE = new Set(["current", "upcoming", "frozen"]);

/** Membership tab: ask to pause, or see where a pause request stands. */
export function PauseCard({ memberships, onChanged }: { memberships: Membership[]; onChanged: () => void }) {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.freeze;
  const { mutate } = useSWRConfig();
  const { busy, general, run } = useFormAction();
  const [asking, setAsking] = useState(false);

  // The running membership first, else the next one.
  const m = [...memberships].filter((x) => LIVE.has(x.state) && x.freeze_policy?.enabled && x.freeze_policy.allowed).sort((a, b) => a.starts_on.localeCompare(b.starts_on))[0];
  if (!m || !m.freeze_policy) return null;
  const policy = m.freeze_policy;
  const current = openFreeze(m);
  const lastRejected = !current ? m.freezes?.filter((f) => f.status === "rejected").at(-1) : undefined;
  const daysLeft = policy.days_left;
  const canAsk = !current && policy.freezable && (daysLeft === null || daysLeft > 0) && (policy.freezes_left === null || policy.freezes_left > 0);

  if (!current && !canAsk) return null;

  const refresh = () => {
    onChanged();
    void mutate("/me/member");
  };

  function cancel(id: string) {
    run(async () => {
      await apiFetch(`/me/member/freezes/${id}`, { method: "DELETE" });
      refresh();
    });
  }

  return (
    <section className="space-y-3 rounded-[10px] border border-line bg-surface px-5 py-4">
      <h2 className="flex items-center gap-2 font-semibold">
        <Snowflake aria-hidden className="h-4 w-4 text-track" />
        {t.title}
      </h2>
      {general && <Alert tone="error">{general}</Alert>}

      {current ? (
        <>
          <p className="text-sm">
            {format(current.status === "requested" ? t.requested : current.status === "approved" ? t.approved : t.active, {
              from: formatDate(current.starts_on, locale),
              to: formatDate(current.ends_on, locale),
              back: formatDate(current.back_on, locale),
            })}
          </p>
          {current.status !== "active" && (
            <Button variant="secondary" busy={busy} onClick={() => cancel(current.id)}>
              {current.status === "requested" ? t.withdraw : t.callOff}
            </Button>
          )}
        </>
      ) : asking ? (
        <PauseForm membership={m} onDone={() => { setAsking(false); refresh(); }} onCancel={() => setAsking(false)} />
      ) : (
        <>
          {lastRejected && (
            <Alert tone="error">{format(t.rejected, { reason: lastRejected.rejection_reason ?? "" })}</Alert>
          )}
          <p className="text-sm text-graphite">
            {t.body}
            {daysLeft !== null && <span className="mt-1 block">{format(t.allowance, { days: String(daysLeft) })}</span>}
          </p>
          <Button variant="secondary" onClick={() => setAsking(true)}>
            {t.request}
          </Button>
        </>
      )}
    </section>
  );
}

function PauseForm({ membership: m, onDone, onCancel }: { membership: Membership; onDone: () => void; onCancel: () => void }) {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.freeze;
  const f = dict.freezes;
  const { busy, error, general, run } = useFormAction();
  const today = addisToday();
  const max = m.freeze_policy?.days_left ?? 365;
  const [startsOn, setStartsOn] = useState(addDays(m.starts_on > today ? m.starts_on : today, 1));
  const [days, setDays] = useState(String(Math.min(7, max)));
  const n = Number.parseInt(days, 10);
  const valid = Number.isFinite(n) && n >= 1 && n <= max;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "").trim();
    run(async () => {
      await apiFetch(`/me/member/memberships/${m.id}/freezes`, { json: { starts_on: startsOn, days: n, reason: reason || null } });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="space-y-3" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      {error?.field("membership") && <Alert tone="error">{error.field("membership")}</Alert>}
      <div className="grid grid-cols-[1fr_6rem] gap-3">
        <Field label={f.startsOn} error={error?.field("starts_on")}>
          {(p) => <Input {...p} type="date" value={startsOn} min={today} max={m.ends_on ?? undefined} onChange={(e) => setStartsOn(e.target.value)} required />}
        </Field>
        <Field label={f.days} error={error?.field("days")}>
          {(p) => <Input {...p} type="number" min={1} max={max} inputMode="numeric" className="tabular" value={days} onChange={(e) => setDays(e.target.value)} required />}
        </Field>
      </div>
      <Field label={f.reason} error={error?.field("reason")}>
        {(p) => <Input {...p} name="reason" maxLength={255} placeholder={f.reasonPlaceholder} />}
      </Field>
      {valid && <p className="text-sm text-graphite">{format(f.backOn, { date: formatDate(addDays(startsOn, n), locale) })}</p>}
      <div className="flex flex-wrap gap-3">
        <Button type="submit" busy={busy} disabled={!valid}>
          {t.send}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
      </div>
    </form>
  );
}
