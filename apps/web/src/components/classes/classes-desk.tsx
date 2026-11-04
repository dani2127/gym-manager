"use client";

import { useState } from "react";
import useSWR from "swr";

import { Roster } from "@/components/classes/roster";
import { ClassSummary, DayStrip, classDay } from "@/components/classes/shared";
import { Alert, Card, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { addisToday } from "@/lib/format";
import { handlesMoneyAt, managesBranch } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { ClassSession } from "@/lib/types";

/** The branch's classes by day; pick one to see the roster. */
export function ClassesDesk() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.classes;
  const [day, setDay] = useState(addisToday());
  const [picked, setPicked] = useState<string | null>(null);
  const desk = handlesMoneyAt(me, branch?.id);
  const { data, mutate } = useSWR<{ data: ClassSession[] }>(branch && desk ? `/class-sessions?branch=${branch.id}&from=${day}&days=1` : null, swrFetcher, { refreshInterval: 30_000 });

  if (!desk) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  const sessions = (data?.data ?? []).filter((s) => classDay(s.starts_at) === day);
  const selected = picked && sessions.some((s) => s.id === picked) ? picked : null;

  return (
    <>
      <PageHeader title={t.title} description={t.description} />
      <div className="space-y-4">
        <DayStrip value={day} onChange={(d) => { setDay(d); setPicked(null); }} />
        <div className="grid gap-4 lg:grid-cols-[1fr_1.1fr]">
          <Card>
            {!data ? (
              <div className="h-40 animate-pulse rounded-[6px] bg-surface-2" />
            ) : sessions.length === 0 ? (
              <p className="text-sm text-graphite">{t.noClasses}</p>
            ) : (
              <ul className="-my-2 divide-y divide-line">
                {sessions.map((s) => (
                  <li key={s.id}>
                    <button
                      type="button"
                      aria-pressed={selected === s.id}
                      onClick={() => setPicked(s.id)}
                      className="w-full cursor-pointer rounded-[6px] px-2 py-3 text-left hover:bg-surface-2 aria-pressed:bg-surface-2"
                    >
                      <ClassSummary session={s} />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Card>
          {selected && (
            <Card>
              <Roster key={selected} sessionId={selected} canBook canCancelClass={managesBranch(me, branch?.id)} onChanged={() => mutate()} />
            </Card>
          )}
        </div>
      </div>
    </>
  );
}
