"use client";

import { useEffect, useState } from "react";

import { InstallPrompt } from "@/components/member/install-prompt";
import { PassCard } from "@/components/member/pass-card";
import { useMember } from "@/components/member/use-member";
import { Alert } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import type { Dictionary } from "@/i18n/dictionaries";
import type { Locale } from "@/i18n/config";
import { apiFetch } from "@/lib/api";
import { formatDate, formatMoney } from "@/lib/format";
import { loadPass, savePass, type StoredPass } from "@/lib/member-pass";
import type { MemberHome as Home, Membership } from "@/lib/types";
import { useIsClient } from "@/lib/use-is-client";

function remaining(m: Membership, dict: Dictionary, locale: Locale) {
  const t = dict.memberApp.status;
  const parts = [m.plan.name];
  if (m.days_left !== null) parts.push(m.days_left === 1 ? t.lastDay : format(t.days, { n: String(m.days_left) }));
  if (m.visits_total !== null) parts.push(format(t.visits, { n: String(m.visits_remaining ?? 0), total: String(m.visits_total) }));
  if (m.days_left === null && m.ends_on) parts.push(format(t.until, { date: formatDate(m.ends_on, locale) }));
  return parts.join(" · ");
}

/** Pass tab: the door QR (works offline) and where the membership stands. */
export function MemberHome() {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp;
  const isClient = useIsClient();
  const { data, error, notMember } = useMember();
  const [fresh, setFresh] = useState<StoredPass | null>(null);
  const home = data?.data;

  // Online: refresh the stored pass (secret, clock offset, summary) for offline use.
  useEffect(() => {
    if (!home) return;
    let cancelled = false;
    apiFetch<{ data: { member_id: string; member_no: string; secret: string; window_seconds: number; server_time: number } }>("/me/member/pass")
      .then(({ data: p }) => {
        if (cancelled) return;
        const pass: StoredPass = {
          ...p,
          clock_offset: p.server_time - Date.now() / 1000,
          first_name: home.member.first_name,
          full_name: home.member.full_name,
          gym: home.organization.name,
          summary: home.current ? remaining(home.current, dict, locale) : null,
          saved_at: Date.now(),
        };
        savePass(pass);
        setFresh(pass);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [home, dict, locale]);

  if (notMember) return <Alert tone="error">{t.notMember}</Alert>;

  const pass = fresh ?? (isClient ? loadPass() : null);
  const offline = !home && Boolean(error) && Boolean(pass);
  const name = home?.member.first_name ?? pass?.first_name;

  return (
    <div className="space-y-5">
      <h1 className="font-bib text-4xl">{name ? format(t.pass.hello, { name }) : " "}</h1>

      {pass ? <PassCard pass={pass} offline={offline} /> : !home && !error ? <div className="aspect-[3/4] animate-pulse rounded-[12px] bg-surface" /> : <Alert>{t.pass.noPass}</Alert>}

      {home && <StatusCard home={home} />}
      <InstallPrompt />
    </div>
  );
}

function StatusCard({ home }: { home: Home }) {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.status;
  const m = home.current;
  const next = home.upcoming[0];

  return (
    <section className="space-y-3 rounded-[10px] border border-line bg-surface p-5">
      {m ? (
        <>
          <div className="flex items-center justify-between gap-3">
            <p className="font-semibold">{m.plan.name}</p>
            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${m.state === "frozen" ? "bg-surface-2 text-ink ring-1 ring-line ring-inset" : "bg-infield-soft text-infield"}`}>
              {m.state === "frozen" ? t.frozen : t.current}
            </span>
          </div>
          <p className="font-bib tabular text-3xl">
            {m.days_left !== null
              ? m.days_left === 1
                ? t.lastDay
                : format(t.days, { n: String(m.days_left) })
              : m.visits_total !== null
                ? format(t.visits, { n: String(m.visits_remaining ?? 0), total: String(m.visits_total) })
                : ""}
          </p>
          {m.days_left !== null && m.visits_total !== null && <p className="text-sm text-graphite">{format(t.visits, { n: String(m.visits_remaining ?? 0), total: String(m.visits_total) })}</p>}
          {m.ends_on && <p className="text-sm text-graphite">{format(t.until, { date: formatDate(m.ends_on, locale) })}</p>}
        </>
      ) : (
        <>
          <p className="font-semibold">{t.none}</p>
          <p className="text-sm text-graphite">{t.noneBody}</p>
        </>
      )}
      {next && (
        <p className="border-t border-line pt-3 text-sm">
          {next.status === "pending_payment" ? `${next.plan.name} · ${t.pending}` : format(t.upcoming, { plan: next.plan.name, date: formatDate(next.starts_on, locale) })}
        </p>
      )}
      {home.owed_cents > 0 && <p className="rounded-[6px] bg-amber-soft px-3 py-2 text-sm font-semibold text-amber">{format(t.owed, { amount: formatMoney(home.owed_cents, locale) })}</p>}
    </section>
  );
}
