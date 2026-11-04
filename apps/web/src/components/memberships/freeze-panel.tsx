"use client";

import { Snowflake } from "lucide-react";
import { useState, type FormEvent } from "react";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input, Toggle } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { addisToday, formatDate } from "@/lib/format";
import { handlesMoneyAt, managesBranch } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { FreezePolicy, Membership, MembershipFreeze } from "@/lib/types";

const OPEN = new Set(["requested", "approved", "active"]);

/** Adds days to a YYYY-MM-DD date. */
export function addDays(date: string, days: number): string {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

export function openFreeze(m: Membership): MembershipFreeze | undefined {
  return m.freezes?.find((f) => OPEN.has(f.status));
}

/** What the plan still allows, in one line. */
export function allowanceLine(policy: FreezePolicy, dict: ReturnType<typeof useDictionary>["dict"]): string {
  const t = dict.freezes;
  if (!policy.allowed) return t.notAllowed;
  if (policy.max_days === null) return t.noLimit;
  if (policy.max_freezes === null) return format(t.allowanceDays, { days: String(policy.days_left), max: String(policy.max_days) });
  return format(t.allowance, { days: String(policy.days_left), max: String(policy.max_days), freezes: String(policy.freezes_left), maxFreezes: String(policy.max_freezes) });
}

/** On a live membership at the desk: the freeze in play, or a way to start one. */
export function FreezePanel({ membership: m, onChanged }: { membership: Membership; onChanged: () => void }) {
  const { locale, dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.freezes;
  const [open, setOpen] = useState(false);
  const policy = m.freeze_policy;
  const current = openFreeze(m);
  const desk = handlesMoneyAt(me, branch?.id);

  if (!policy || !policy.enabled || !desk) return null;

  if (current) {
    return (
      <div className="mt-3 rounded-[6px] border border-line bg-surface-2/60 px-3 py-2.5 text-sm">
        <p className="flex items-center gap-2 font-semibold">
          <Snowflake aria-hidden className="h-4 w-4 text-track" />
          {current.status === "active"
            ? format(t.frozenNow, { date: formatDate(current.ends_on, locale) })
            : current.status === "approved"
              ? format(t.upcoming, { from: formatDate(current.starts_on, locale), to: formatDate(current.ends_on, locale) })
              : format(t.requested, { from: formatDate(current.starts_on, locale), to: formatDate(current.ends_on, locale), days: String(current.days) })}
        </p>
        <p className="mt-0.5 text-xs text-graphite">
          {[current.status === "active" ? null : format(t.backOn, { date: formatDate(current.back_on, locale) }), current.reason, current.over_limit ? t.overLimit : null].filter(Boolean).join(" · ")}
        </p>
        <div className="mt-2">
          <FreezeActions freeze={current} onDone={onChanged} />
        </div>
      </div>
    );
  }

  if (!policy.freezable) return null;

  return open ? (
    <FreezeForm membership={m} policy={policy} onDone={() => { setOpen(false); onChanged(); }} onCancel={() => setOpen(false)} />
  ) : (
    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-graphite">
      <span>{allowanceLine(policy, dict)}</span>
      {(policy.allowed || managesBranch(me, branch?.id)) && (
        <Button variant="secondary" className="min-h-9" onClick={() => setOpen(true)}>
          <Snowflake aria-hidden className="h-4 w-4" />
          {t.freeze}
        </Button>
      )}
    </div>
  );
}

function FreezeForm({ membership: m, policy, onDone, onCancel }: { membership: Membership; policy: FreezePolicy; onDone: () => void; onCancel: () => void }) {
  const { locale, dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.freezes;
  const { busy, error, general, run } = useFormAction();
  const today = addisToday();
  const [startsOn, setStartsOn] = useState(m.starts_on > today ? m.starts_on : today);
  const [days, setDays] = useState(String(Math.max(1, Math.min(7, policy.days_left ?? 7))));
  const [override, setOverride] = useState(false);
  const n = Number.parseInt(days, 10);
  const valid = Number.isFinite(n) && n >= 1;
  const overLimit = !policy.allowed || (policy.days_left !== null && valid && n > policy.days_left) || (policy.freezes_left !== null && policy.freezes_left < 1);
  const canOverride = managesBranch(me, branch?.id);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "").trim();
    run(async () => {
      await apiFetch(`/memberships/${m.id}/freezes`, { json: { starts_on: startsOn, days: n, reason: reason || null, override: overLimit && override } });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="mt-3 space-y-3 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      <p className="font-semibold">{t.freezeTitle}</p>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="grid gap-3 sm:grid-cols-[1fr_7rem]">
        <Field label={t.startsOn} error={error?.field("starts_on")}>
          {(p) => <Input {...p} type="date" value={startsOn} min={today} max={m.ends_on ?? undefined} onChange={(e) => setStartsOn(e.target.value)} required />}
        </Field>
        <Field label={t.days} error={error?.field("days")}>
          {(p) => <Input {...p} name="days" type="number" min={1} max={365} inputMode="numeric" className="tabular" value={days} onChange={(e) => setDays(e.target.value)} required />}
        </Field>
      </div>
      <Field label={t.reason} error={error?.field("reason")}>
        {(p) => <Input {...p} name="reason" maxLength={255} placeholder={t.reasonPlaceholder} />}
      </Field>
      <p className="text-xs text-graphite">
        {[valid ? format(t.backOn, { date: formatDate(addDays(startsOn, n), locale) }) : null, valid && m.ends_on ? format(t.newEnd, { date: formatDate(addDays(m.ends_on, n), locale) }) : null, allowanceLine(policy, dict)]
          .filter(Boolean)
          .join(" · ")}
      </p>
      {error?.field("membership") && <Alert tone="error">{error.field("membership")}</Alert>}
      {overLimit && canOverride && (
        <div className="rounded-[6px] border border-amber/40 bg-amber-soft px-4">
          <Toggle label={t.override} checked={override} onChange={setOverride} />
        </div>
      )}
      <div className="flex flex-wrap gap-3">
        <Button type="submit" busy={busy} disabled={!valid || (overLimit && !(canOverride && override))}>
          {t.save}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
      </div>
    </form>
  );
}

/** Approve / reject a request, cancel an upcoming freeze or end a running one. */
export function FreezeActions({ freeze, onDone }: { freeze: MembershipFreeze; onDone: () => void }) {
  const { dict } = useDictionary();
  const t = dict.freezes;
  const { busy, error, general, run } = useFormAction();
  const [rejecting, setRejecting] = useState(false);

  const act = (path: string, init: Parameters<typeof apiFetch>[1] = { json: {} }) =>
    run(async () => {
      await apiFetch(path, init);
      onDone();
    });

  function reject(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "");
    act(`/freezes/${freeze.id}/reject`, { json: { reason } });
  }

  return (
    <div className="space-y-2">
      {(general || error?.field("freeze") || error?.field("membership") || error?.field("days")) && (
        <Alert tone="error">{general ?? error?.field("freeze") ?? error?.field("membership") ?? error?.field("days")}</Alert>
      )}
      {rejecting ? (
        <form onSubmit={reject} className="space-y-2" noValidate>
          <label className="block text-sm font-semibold">
            {t.rejectReason}
            <Input name="reason" autoFocus className="mt-1" aria-invalid={error?.field("reason") ? true : undefined} />
          </label>
          {error?.field("reason") && <p className="text-xs font-medium text-critical">{error.field("reason")}</p>}
          <div className="flex gap-2">
            <Button type="submit" variant="danger" busy={busy}>
              {t.confirmReject}
            </Button>
            <Button type="button" variant="secondary" onClick={() => setRejecting(false)}>
              {dict.common.cancel}
            </Button>
          </div>
        </form>
      ) : (
        <div className="flex flex-wrap gap-2">
          {freeze.status === "requested" && (
            <>
              <Button className="min-h-9" busy={busy} onClick={() => act(`/freezes/${freeze.id}/approve`)}>
                {t.approve}
              </Button>
              <Button variant="secondary" className="min-h-9" disabled={busy} onClick={() => setRejecting(true)}>
                {t.reject}
              </Button>
            </>
          )}
          {freeze.status === "approved" && (
            <Button variant="secondary" className="min-h-9" busy={busy} onClick={() => act(`/freezes/${freeze.id}`, { method: "DELETE" })}>
              {t.cancel}
            </Button>
          )}
          {freeze.status === "active" && (
            <Button variant="secondary" className="min-h-9" busy={busy} onClick={() => act(`/freezes/${freeze.id}/end`)}>
              {t.endNow}
            </Button>
          )}
        </div>
      )}
    </div>
  );
}
