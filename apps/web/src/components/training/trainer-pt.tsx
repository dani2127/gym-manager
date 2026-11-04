"use client";

import { useState } from "react";
import useSWR from "swr";

import { classDay } from "@/components/classes/shared";
import { MemberAvatar } from "@/components/members/member-avatar";
import { ScheduleForm, SessionRow } from "@/components/training/pt-sessions";
import { Card } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { PtEnrollment, PtSession } from "@/lib/types";

/** A trainer's PT clients and this week's sessions, with private notes. */
export function TrainerPt() {
  const { locale, dict } = useDictionary();
  const t = dict.training;
  const { data: clients, mutate: reloadClients } = useSWR<{ data: PtEnrollment[] }>("/pt-clients", swrFetcher);
  const { data: sessions, mutate: reloadSessions } = useSWR<{ data: PtSession[] }>("/pt-sessions?mine=1&days=7", swrFetcher, { refreshInterval: 60_000 });
  const [scheduling, setScheduling] = useState<string | null>(null);
  const reload = () => {
    reloadClients();
    reloadSessions();
  };
  const list = (sessions?.data ?? []).filter((s) => s.status !== "cancelled");
  const days = [...new Set(list.map((s) => classDay(s.starts_at)))];

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card title={t.thisWeek}>
        {!sessions ? (
          <div className="h-32 animate-pulse rounded-[6px] bg-surface-2" />
        ) : list.length === 0 ? (
          <p className="text-sm text-graphite">{t.noUpcoming}</p>
        ) : (
          <div className="space-y-4">
            {days.map((day) => (
              <section key={day}>
                <h3 className="text-xs font-semibold text-graphite">{formatDate(day, locale)}</h3>
                <ul className="divide-y divide-line">
                  {list
                    .filter((s) => classDay(s.starts_at) === day)
                    .map((s) => (
                      <SessionRow key={s.id} session={s} showMember notes onChanged={reload} />
                    ))}
                </ul>
              </section>
            ))}
          </div>
        )}
      </Card>
      <Card title={t.clients}>
        {!clients ? (
          <div className="h-32 animate-pulse rounded-[6px] bg-surface-2" />
        ) : clients.data.length === 0 ? (
          <p className="text-sm text-graphite">{t.clientsEmpty}</p>
        ) : (
          <ul className="divide-y divide-line">
            {clients.data.map((e) => (
              <li key={e.id} className="py-3">
                <div className="flex items-center gap-3">
                  {e.member && <MemberAvatar member={e.member} size="sm" />}
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold">{e.member?.full_name}</p>
                    <p className="text-xs text-graphite">
                      {e.package}
                      {e.ends_on ? ` · ${format(t.until, { date: formatDate(e.ends_on, locale) })}` : ` · ${t.awaiting}`}
                    </p>
                  </div>
                  <span className="font-bib tabular text-lg">
                    {e.sessions_remaining}/{e.sessions_total}
                  </span>
                </div>
                {e.status === "active" && e.sessions_remaining - e.sessions_scheduled > 0 && (
                  <div className="mt-2 pl-12">
                    {scheduling === e.id ? (
                      <ScheduleForm enrollmentId={e.id} onDone={() => { setScheduling(null); reload(); }} />
                    ) : (
                      <button type="button" onClick={() => setScheduling(e.id)} className="cursor-pointer text-sm font-semibold text-track-deep hover:underline">
                        + {t.schedule}
                      </button>
                    )}
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
