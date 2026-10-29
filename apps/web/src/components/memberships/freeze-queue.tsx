"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import useSWR from "swr";

import { FreezeActions } from "@/components/memberships/freeze-panel";
import { MemberAvatar } from "@/components/members/member-avatar";
import { Alert, PageHeader } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { getEcho } from "@/lib/echo";
import { formatDate } from "@/lib/format";
import { handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { FreezeReview } from "@/lib/types";

type List = { data: FreezeReview[]; meta: { requested: number } };

/** The desk's freezes: requests from the app first, then running and upcoming ones. */
export function FreezeQueue() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.freezes;
  const [tab, setTab] = useState<"open" | "done">("open");
  const allowed = handlesMoneyAt(me, branch?.id);
  const key = allowed && branch ? `/freezes?branch=${branch.id}${tab === "done" ? "&status=done" : ""}` : null;
  const { data, mutate } = useSWR<List>(key, swrFetcher, { refreshInterval: 30_000 });

  useEffect(() => {
    const echo = getEcho();
    if (!echo || !branch) return;
    echo.private(`branch.${branch.id}.desk`).listen(".freeze.requested", () => void mutate()).listen(".freeze.reviewed", () => void mutate());
    return () => echo.leave(`branch.${branch.id}.desk`);
  }, [branch, mutate]);

  if (!allowed) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader title={t.title} description={t.description} />
      <div className="space-y-5">
        <div role="tablist" className="inline-grid grid-cols-2 gap-1 rounded-[6px] bg-surface-2 p-1">
          {(["open", "done"] as const).map((name) => (
            <button
              key={name}
              type="button"
              role="tab"
              aria-selected={tab === name}
              onClick={() => setTab(name)}
              className="min-h-10 cursor-pointer rounded-[5px] px-5 text-sm font-semibold text-graphite aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-sm"
            >
              {t[name]}
              {name === "open" && data && tab === "open" && data.meta.requested > 0 && <span className="ml-2 rounded-full bg-track px-2 py-0.5 text-xs text-white">{data.meta.requested}</span>}
            </button>
          ))}
        </div>

        {!data ? (
          <div className="h-48 animate-pulse rounded-[6px] bg-surface" />
        ) : data.data.length === 0 ? (
          <p className="rounded-[6px] border border-line bg-surface p-6 text-sm text-graphite">{tab === "open" ? t.empty : t.emptyDone}</p>
        ) : (
          <ul className="divide-y divide-line rounded-[6px] border border-line bg-surface">
            {data.data.map((f) => (
              <FreezeRow key={f.id} freeze={f} onDone={() => mutate()} />
            ))}
          </ul>
        )}
      </div>
    </>
  );
}

function FreezeRow({ freeze: f, onDone }: { freeze: FreezeReview; onDone: () => void }) {
  const { locale, dict } = useDictionary();
  const t = dict.freezes;
  const tone = f.status === "requested" ? "bg-amber-soft text-amber" : f.status === "active" ? "bg-surface-2 text-ink ring-1 ring-line ring-inset" : "bg-surface-2 text-graphite";

  return (
    <li className="flex flex-wrap items-start gap-4 p-4">
      {f.member && <MemberAvatar member={f.member} size="md" />}
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          {f.member ? (
            <Link href={`/members/${f.member.id}`} className="font-semibold hover:text-track-deep">
              {f.member.full_name}
            </Link>
          ) : null}
          <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone}`}>{t.statuses[f.status]}</span>
          {f.over_limit && <span className="text-xs font-semibold text-amber">{t.overLimit}</span>}
        </div>
        <p className="text-sm">
          <span className="tabular font-semibold">{format(dict.billing.memberships.range, { from: formatDate(f.starts_on, locale), to: formatDate(f.ends_on, locale) })}</span>
          <span className="text-graphite"> · {format(t.dayCount, { n: String(f.days) })} · {f.membership.plan}</span>
        </p>
        <p className="text-xs text-graphite">
          {[
            format(t.backOn, { date: formatDate(f.back_on, locale) }),
            f.reason,
            f.from_member ? t.fromMember : f.requested_by ? format(t.byStaff, { name: f.requested_by }) : null,
            f.status === "rejected" && f.rejection_reason ? `${t.statuses.rejected}: ${f.rejection_reason}` : null,
          ]
            .filter(Boolean)
            .join(" · ")}
        </p>
      </div>
      {["requested", "approved", "active"].includes(f.status) && (
        <div className="w-full sm:w-auto">
          <FreezeActions freeze={f} onDone={onDone} />
        </div>
      )}
    </li>
  );
}
