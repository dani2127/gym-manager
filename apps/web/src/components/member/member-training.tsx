"use client";

import useSWR from "swr";

import { classDay, classTime } from "@/components/classes/shared";
import { useMember } from "@/components/member/use-member";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatDate } from "@/lib/format";
import type { PtEnrollment } from "@/lib/types";

/** Membership tab: personal training packages, sessions left and the next ones. */
export function MemberTraining() {
  const { locale, dict } = useDictionary();
  const t = dict.memberApp.training;
  const { signedOut } = useMember();
  const { data } = useSWR<{ data: PtEnrollment[] }>(signedOut ? null : "/me/member/training", swrFetcher);
  const list = (data?.data ?? []).filter((e) => e.status === "active" || e.status === "pending_payment");

  if (list.length === 0) return null;

  return (
    <section className="space-y-3 rounded-[10px] border border-line bg-surface px-5 py-4">
      <h2 className="text-sm font-semibold text-graphite">{t.title}</h2>
      {list.map((e) => (
        <div key={e.id} className="space-y-2">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="font-semibold">{e.package}</p>
              {e.trainer && <p className="text-sm text-graphite">{format(t.with, { name: e.trainer.name })}</p>}
            </div>
            <span className="font-bib tabular text-2xl">
              {e.sessions_remaining}/{e.sessions_total}
            </span>
          </div>
          {e.trainer_profile?.bio && <p className="rounded-[6px] bg-surface-2 px-3 py-2 text-sm">{e.trainer_profile.bio}</p>}
          {e.trainer_profile && e.trainer_profile.specialties.length > 0 && (
            <div className="flex flex-wrap gap-1.5">
              {e.trainer_profile.specialties.map((s) => (
                <span key={s} className="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-medium">
                  {s}
                </span>
              ))}
            </div>
          )}
          <p className="text-sm text-graphite">
            {e.status === "pending_payment" ? t.awaiting : `${format(t.left, { n: String(e.sessions_remaining), total: String(e.sessions_total) })}${e.ends_on ? ` · ${format(t.until, { date: formatDate(e.ends_on, locale) })}` : ""}`}
          </p>
          <div>
            <p className="text-xs font-semibold text-graphite">{t.next}</p>
            {(e.upcoming ?? []).length === 0 ? (
              <p className="text-sm text-graphite">{t.none}</p>
            ) : (
              <ul className="divide-y divide-line">
                {e.upcoming!.map((s) => (
                  <li key={s.id} className="flex justify-between py-2 text-sm">
                    <span>{formatDate(classDay(s.starts_at), locale)}</span>
                    <span className="tabular font-semibold">{classTime(s.starts_at)}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      ))}
    </section>
  );
}
