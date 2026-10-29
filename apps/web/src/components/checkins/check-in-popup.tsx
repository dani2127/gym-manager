"use client";

import { CheckCircle2, CircleAlert, ShieldCheck, XCircle } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";

import { LockerPicker } from "@/components/checkins/locker-picker";
import { MemberAvatar } from "@/components/members/member-avatar";
import { useFormAction } from "@/components/staff/use-form-action";
import { Button } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { CheckIn } from "@/lib/types";

const AUTO_CLOSE_SECONDS = 6;

const TONES = {
  allowed: { band: "bg-infield", text: "text-infield", Icon: CheckCircle2 },
  overridden: { band: "bg-infield", text: "text-infield", Icon: ShieldCheck },
  already_inside: { band: "bg-amber", text: "text-amber", Icon: CircleAlert },
  denied: { band: "bg-critical", text: "text-critical", Icon: XCircle },
} as const;

/** A short tone so the desk hears the result without looking: high for in, low for no. */
function beep(ok: boolean) {
  try {
    const AudioContextClass = window.AudioContext ?? (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
    const ctx = new AudioContextClass();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.frequency.value = ok ? 880 : 220;
    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + (ok ? 0.18 : 0.4));
    osc.connect(gain).connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.45);
    osc.onended = () => void ctx.close();
  } catch {
    // Sound is a nicety; some browsers block it until the page is clicked.
  }
}

/**
 * The desk pop-up: who it is, whether they may come in, and why not.
 * Entries close themselves; denials wait for the desk to decide.
 */
export function CheckInPopup({
  checkIn,
  onClose,
  onChanged,
  branchId,
  lockersEnabled = false,
}: {
  checkIn: CheckIn;
  onClose: () => void;
  onChanged: (checkIn: CheckIn) => void;
  branchId?: string;
  lockersEnabled?: boolean;
}) {
  const { locale, dict } = useDictionary();
  const t = dict.checkins;
  const tone = TONES[checkIn.result];
  const entered = checkIn.result === "allowed" || checkIn.result === "overridden";
  const lockerStep = lockersEnabled && Boolean(branchId) && entered && Boolean(checkIn.id) && !checkIn.checked_out_at;
  // Waits while a locker still has to be handed out.
  const closes = checkIn.result !== "denied" && !(lockerStep && !checkIn.locker);
  const [left, setLeft] = useState(AUTO_CLOSE_SECONDS);
  const [overriding, setOverriding] = useState(false);
  const { busy, error, general, run } = useFormAction();
  const doneRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    beep(entered || checkIn.result === "already_inside");
    doneRef.current?.focus();
  }, [checkIn.id, checkIn.result, entered]);

  useEffect(() => {
    if (!closes || overriding) return;
    const timer = setInterval(() => setLeft((n) => n - 1), 1000);
    return () => clearInterval(timer);
  }, [closes, overriding, checkIn.id]);

  useEffect(() => {
    if (left <= 0) onClose();
  }, [left, onClose]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => event.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  function override(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "");
    run(async () => {
      const { data } = await apiFetch<{ data: CheckIn }>(`/check-ins/${checkIn.id}/override`, { json: { reason } });
      setOverriding(false);
      setLeft(AUTO_CLOSE_SECONDS);
      onChanged(data);
    });
  }

  const m = checkIn.membership;
  const member = checkIn.member;
  const details = [
    m?.plan,
    m && m.days_left !== null ? (m.days_left === 1 ? t.popup.lastDay : format(t.popup.daysLeft, { n: String(m.days_left) })) : null,
    m && m.visits_total !== null ? format(t.popup.visitsLeft, { n: String(m.visits_remaining ?? 0), total: String(m.visits_total) }) : null,
  ].filter(Boolean);

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/55 p-4" role="dialog" aria-modal="true" aria-labelledby="check-in-result">
      <div className="w-full max-w-lg overflow-hidden rounded-[10px] bg-surface shadow-2xl">
        <div className={`flex items-center gap-3 px-6 py-4 text-white ${tone.band}`}>
          <tone.Icon aria-hidden className="h-8 w-8 shrink-0" />
          <h2 id="check-in-result" className="font-bib text-3xl">
            {t.results[checkIn.result]}
          </h2>
        </div>

        <div className="space-y-5 p-6">
          {member ? (
            <div className="flex items-center gap-5">
              <div className="[&>*]:h-32 [&>*]:w-32 [&>*]:text-4xl">
                <MemberAvatar member={member} size="lg" />
              </div>
              <div className="min-w-0">
                <p className="font-bib tabular text-5xl leading-none">{member.member_no}</p>
                <p className="mt-2 truncate text-xl font-bold">{member.full_name}</p>
                {details.length > 0 && <p className="mt-1 text-sm text-graphite">{details.join(" · ")}</p>}
                {m?.state === "upcoming" && <p className="mt-1 text-sm text-graphite">{formatDate(m.starts_on, locale)}</p>}
              </div>
            </div>
          ) : null}

          {checkIn.reason && checkIn.result !== "overridden" && (
            <p className={`text-base font-semibold ${tone.text}`}>{t.reasons[checkIn.reason as keyof typeof t.reasons] ?? checkIn.reason}</p>
          )}
          {checkIn.result === "overridden" && checkIn.override_reason && <p className="text-sm text-graphite">“{checkIn.override_reason}”</p>}

          {lockerStep && branchId && !overriding && <LockerPicker checkIn={checkIn} branchId={branchId} onChanged={onChanged} />}

          {overriding ? (
            <form onSubmit={override} className="space-y-3" noValidate>
              <label className="block">
                <span className="mb-1 block text-sm font-semibold">{t.popup.overrideReason}</span>
                <input
                  name="reason"
                  autoFocus
                  required
                  maxLength={255}
                  placeholder={t.popup.overridePlaceholder}
                  className="h-11 w-full rounded-[6px] border border-line-strong bg-surface px-3 text-sm outline-none focus:border-ink"
                />
              </label>
              {(error?.field("reason") || general) && <p className="text-xs font-medium text-critical">{error?.field("reason") ?? general}</p>}
              <div className="flex gap-3">
                <Button type="submit" busy={busy}>
                  {t.popup.confirm}
                </Button>
                <Button type="button" variant="secondary" onClick={() => setOverriding(false)}>
                  {dict.common.cancel}
                </Button>
              </div>
            </form>
          ) : (
            <div className="flex flex-wrap items-center gap-3">
              <Button ref={doneRef} onClick={onClose} variant={checkIn.overridable ? "secondary" : "primary"}>
                {t.popup.done}
                {closes && <span className="text-xs font-normal opacity-80">· {format(t.popup.autoClose, { n: String(Math.max(left, 0)) })}</span>}
              </Button>
              {checkIn.overridable && <Button onClick={() => setOverriding(true)}>{t.popup.letIn}</Button>}
              {member && (
                <Link href={`/members/${member.id}`} onClick={onClose} className="text-sm font-semibold text-track-deep hover:underline">
                  {t.popup.openProfile}
                </Link>
              )}
              {member && ["no_membership", "membership_expired", "no_visits_left"].includes(checkIn.reason ?? "") && (
                <Link href={`/members/${member.id}/sell`} onClick={onClose} className="text-sm font-semibold text-track-deep hover:underline">
                  {t.popup.sell}
                </Link>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
