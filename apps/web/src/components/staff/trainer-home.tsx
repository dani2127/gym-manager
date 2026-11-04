"use client";

import { useState } from "react";
import useSWR from "swr";

import { Roster } from "@/components/classes/roster";
import { ClassSummary, classDay } from "@/components/classes/shared";
import { Alert, Card, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatDate } from "@/lib/format";
import { workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { ClassSession } from "@/lib/types";

/** A trainer's own classes for the coming week, with the roster and attendance. */
export function TrainerHome() {
  const { locale, dict } = useDictionary();
  const { me } = useSession();
  const t = dict.classes;
  const [picked, setPicked] = useState<string | null>(null);
  const allowed = workspaces(me).trainer;
  const { data, mutate } = useSWR<{ data: ClassSession[] }>(allowed ? "/class-sessions?mine=1&days=7" : null, swrFetcher, { refreshInterval: 30_000 });

  if (!allowed) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  const sessions = data?.data ?? [];
  const days = [...new Set(sessions.map((s) => classDay(s.starts_at)))];

  return (
    <>
      <PageHeader title={t.myClasses} description={dict.staff.trainer.body} />
      <div className="grid gap-4 lg:grid-cols-[1fr_1.1fr]">
        <Card>
          {!data ? (
            <div className="h-40 animate-pulse rounded-[6px] bg-surface-2" />
          ) : sessions.length === 0 ? (
            <p className="text-sm text-graphite">{t.myClassesEmpty}</p>
          ) : (
            <div className="space-y-5">
              {days.map((day) => (
                <section key={day}>
                  <h3 className="mb-1 text-xs font-semibold text-graphite">{formatDate(day, locale)}</h3>
                  <ul className="divide-y divide-line">
                    {sessions
                      .filter((s) => classDay(s.starts_at) === day)
                      .map((s) => (
                        <li key={s.id}>
                          <button type="button" aria-pressed={picked === s.id} onClick={() => setPicked(s.id)} className="w-full cursor-pointer rounded-[6px] px-2 py-3 text-left hover:bg-surface-2 aria-pressed:bg-surface-2">
                            <ClassSummary session={s} />
                          </button>
                        </li>
                      ))}
                  </ul>
                </section>
              ))}
            </div>
          )}
        </Card>
        {picked && (
          <Card>
            <Roster key={picked} sessionId={picked} canBook={false} canCancelClass={false} onChanged={() => mutate()} />
          </Card>
        )}
      </div>
    </>
  );
}
