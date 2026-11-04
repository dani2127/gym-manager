"use client";

import { useState } from "react";
import useSWR from "swr";

import { CLASS_COLORS, DayStrip, classDay, classTime } from "@/components/classes/shared";
import { useMember } from "@/components/member/use-member";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { addisToday } from "@/lib/format";
import { useIsClient } from "@/lib/use-is-client";
import { useNow } from "@/lib/use-now";
import type { MemberClass } from "@/lib/types";

type Credits = { plan: string; left: number | null; included: boolean };

/** Classes tab: the coming days, book or join the waitlist, cancel. */
export function MemberClasses() {
  // "Today" depends on the clock, so this renders in the browser only.
  if (!useIsClient()) return <div className="h-64 animate-pulse rounded-[10px] bg-surface-2" />;
  return <ClassesBrowser />;
}

function ClassesBrowser() {
  const { dict } = useDictionary();
  const t = dict.memberApp.classes;
  const { signedOut } = useMember();
  const [day, setDay] = useState(addisToday());
  const { data, mutate } = useSWR<{ data: MemberClass[]; meta: { credits: Credits[] } }>(signedOut ? null : "/me/member/classes", swrFetcher, { refreshInterval: 60_000 });
  const sessions = (data?.data ?? []).filter((s) => classDay(s.starts_at) === day);
  const mine = (data?.data ?? []).filter((s) => s.my_booking);

  return (
    <div className="space-y-5">
      <div>
        <h1 className="font-bib text-4xl">{t.title}</h1>
        <p className="mt-1 text-sm text-graphite">{t.subtitle}</p>
      </div>

      {data?.meta.credits.map((c) => (
        <p key={c.plan} className="rounded-[6px] bg-surface-2 px-3 py-2 text-sm">
          {!c.included ? format(t.notIncluded, { plan: c.plan }) : c.left === null ? format(t.creditsUnlimited, { plan: c.plan }) : format(t.credits, { n: String(c.left), plan: c.plan })}
        </p>
      ))}

      {mine.length > 0 && (
        <section className="rounded-[10px] border border-line bg-surface px-4 py-3">
          <h2 className="text-sm font-semibold text-graphite">{t.myBookings}</h2>
          <ul className="mt-1 divide-y divide-line">
            {mine.map((s) => (
              <li key={s.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                <span>
                  <span className="block font-semibold">{s.class_type?.name}</span>
                  <span className="tabular text-xs text-graphite">
                    {new Intl.DateTimeFormat("en-GB", { weekday: "short", day: "numeric", timeZone: "Africa/Addis_Ababa" }).format(new Date(s.starts_at))} · {classTime(s.starts_at)}
                  </span>
                </span>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${s.my_booking?.status === "waitlisted" ? "bg-amber-soft text-amber" : "bg-infield-soft text-infield"}`}>
                  {s.my_booking?.status === "waitlisted" ? format(t.waiting, { n: String(s.my_booking.waitlist_position ?? "") }) : t.booked}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      <DayStrip value={day} onChange={setDay} />

      {!data ? (
        <div className="h-40 animate-pulse rounded-[10px] bg-surface-2" />
      ) : sessions.length === 0 ? (
        <p className="rounded-[10px] border border-line bg-surface p-5 text-sm text-graphite">{t.empty}</p>
      ) : (
        <ul className="space-y-3">
          {sessions.map((s) => (
            <ClassCard key={s.id} session={s} onChanged={() => mutate()} />
          ))}
        </ul>
      )}
    </div>
  );
}

function ClassCard({ session: s, onChanged }: { session: MemberClass; onChanged: () => void }) {
  const { dict } = useDictionary();
  const t = dict.memberApp.classes;
  const { busy, general, error, run } = useFormAction();
  const now = useNow();
  const full = s.spots_left === 0;
  const cancelled = s.status === "cancelled";
  const started = Date.parse(s.starts_at) <= now;
  // Cancelling inside the cutoff uses the credit; the server decides, this only warns.
  const soon = Date.parse(s.starts_at) - now < 2 * 3600_000;

  const book = () =>
    run(async () => {
      await apiFetch(`/me/member/classes/${s.id}/book`, { json: {} });
      onChanged();
    });
  const cancel = () =>
    run(async () => {
      await apiFetch(`/me/member/classes/${s.id}/booking`, { method: "DELETE" });
      onChanged();
    });

  return (
    <li className="overflow-hidden rounded-[10px] border border-line bg-surface" style={{ borderLeft: `6px solid ${CLASS_COLORS[s.class_type?.color ?? "track"]}` }}>
      <div className="flex items-start gap-3 px-4 py-3">
        <div className="min-w-0 flex-1">
          <p className="font-bib tabular text-2xl leading-none">{classTime(s.starts_at)}</p>
          <p className={`mt-1 font-semibold ${cancelled ? "line-through" : ""}`}>{s.class_type?.name}</p>
          <p className="text-xs text-graphite">{[s.trainer ? format(t.with, { name: s.trainer.name }) : null, s.room?.name, s.branch?.name].filter(Boolean).join(" · ")}</p>
        </div>
        <span className={`tabular shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${cancelled ? "bg-track-soft text-track-deep" : full ? "bg-amber-soft text-amber" : "bg-surface-2 text-ink"}`}>
          {cancelled ? dict.classes.cancelled : full ? t.full : format(t.left, { n: String(s.spots_left) })}
        </span>
      </div>
      {!cancelled && !started && (
        <div className="space-y-2 border-t border-line px-4 py-3">
          {(general || error?.field("member") || error?.field("booking")) && <Alert tone="error">{error?.field("member") ?? error?.field("booking") ?? general}</Alert>}
          {s.my_booking ? (
            <div className="flex items-center justify-between gap-3">
              <span className="text-sm font-semibold text-infield">
                {s.my_booking.status === "waitlisted" ? format(t.waiting, { n: String(s.my_booking.waitlist_position ?? "") }) : t.booked}
              </span>
              <Button variant="secondary" className="min-h-9" busy={busy} onClick={cancel}>
                {s.my_booking.status === "waitlisted" ? t.leave : t.cancel}
              </Button>
            </div>
          ) : s.can_book ? (
            <Button className="w-full" busy={busy} onClick={book}>
              {full ? t.join : t.book}
            </Button>
          ) : (
            s.reason && <p className="text-sm text-graphite">{s.reason}</p>
          )}
          {s.my_booking?.status === "booked" && soon && <p className="text-xs text-amber">{t.lateWarning}</p>}
        </div>
      )}
    </li>
  );
}
