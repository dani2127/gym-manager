"use client";

import { LogOut, Radio } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import useSWR, { useSWRConfig } from "swr";

import { CheckInPopup } from "@/components/checkins/check-in-popup";
import { useLockersEnabled } from "@/components/checkins/locker-picker";
import { QrScanner } from "@/components/checkins/qr-scanner";
import { MemberAvatar } from "@/components/members/member-avatar";
import { Card } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { getEcho } from "@/lib/echo";
import type { CheckIn, CheckInList } from "@/lib/types";

const RESULT_TONE: Record<CheckIn["result"], string> = {
  allowed: "bg-infield-soft text-infield",
  overridden: "bg-infield-soft text-infield",
  already_inside: "bg-amber-soft text-amber",
  denied: "bg-critical-soft text-critical",
};

function timeOf(iso: string | undefined) {
  return iso ? new Intl.DateTimeFormat("en-GB", { hour: "2-digit", minute: "2-digit", timeZone: "Africa/Addis_Ababa" }).format(new Date(iso)) : "";
}

/** Live check-in console: scanner, who is inside, and the latest scans. */
export function DeskConsole({ branchId }: { branchId: string }) {
  const { dict } = useDictionary();
  const t = dict.checkins;
  const { mutate } = useSWRConfig();
  const [popup, setPopup] = useState<CheckIn | null>(null);
  const [busy, setBusy] = useState(false);
  const [live, setLive] = useState(false);
  const lockersEnabled = useLockersEnabled(branchId);

  const insideKey = `/branches/${branchId}/check-ins?scope=inside`;
  const recentKey = `/branches/${branchId}/check-ins?scope=recent`;
  const refresh = live ? 30_000 : 5_000;
  const { data: inside } = useSWR<CheckInList>(insideKey, swrFetcher, { refreshInterval: refresh });
  const { data: recent } = useSWR<CheckInList>(recentKey, swrFetcher, { refreshInterval: refresh });

  const reload = useCallback(() => Promise.all([mutate(insideKey), mutate(recentKey)]), [mutate, insideKey, recentKey]);

  // Scans from other screens (the entrance kiosk) arrive over the desk channel.
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const channel = echo.private(`branch.${branchId}.desk`);
    const pusher = (echo.connector as unknown as { pusher: { connection: { state: string; bind: (e: string, f: () => void) => void; unbind: (e: string, f: () => void) => void } } }).pusher;
    const onState = () => setLive(pusher.connection.state === "connected");
    pusher.connection.bind("state_change", onState);
    onState();
    channel
      .listen(".check-in.scanned", (event: { check_in: CheckIn }) => {
        void reload();
        if (event.check_in.method === "kiosk") setPopup(event.check_in);
      })
      .listen(".check-in.overridden", () => void reload())
      .listen(".check-in.checked_out", () => void reload())
      .listen(".check-in.locker", () => void reload())
      .listen(".payment.submitted", () => void mutate(`/payments/transfers?branch=${branchId}`));
    return () => {
      pusher.connection.unbind("state_change", onState);
      echo.leave(`branch.${branchId}.desk`);
    };
  }, [branchId, reload, mutate]);

  const scan = useCallback(
    async (code: string) => {
      setBusy(true);
      try {
        const { data } = await apiFetch<{ data: CheckIn }>(`/branches/${branchId}/check-ins/scan`, { json: { code } });
        setPopup(data);
        void reload();
      } catch {
        setPopup({ id: null, result: "denied", reason: "invalid_code", overridable: false, member: null, membership: null });
      } finally {
        setBusy(false);
      }
    },
    [branchId, reload],
  );

  async function checkOut(id: string) {
    await apiFetch(`/check-ins/${id}/check-out`, { method: "POST" });
    void reload();
  }

  const meta = inside?.meta;
  const full = meta?.capacity ? meta.inside / meta.capacity : 0;

  return (
    <>
      <div className="grid items-start gap-6 xl:grid-cols-[1.15fr_1fr]">
        <Card
          title={t.desk.scanTitle}
          actions={
            <span className={`inline-flex items-center gap-1.5 text-xs font-semibold ${live ? "text-infield" : "text-graphite"}`}>
              <Radio aria-hidden className="h-3.5 w-3.5" />
              {live ? t.desk.live : t.desk.polling}
            </span>
          }
        >
          <QrScanner onCode={scan} busy={busy} />
        </Card>

        <div className="space-y-6">
          <Card title={t.desk.insideTitle}>
            <div className="flex flex-wrap items-end gap-x-6 gap-y-2">
              <p className="font-bib tabular text-6xl leading-none">
                {meta?.inside ?? "–"}
                {meta?.capacity ? <span className="ml-2 text-2xl text-graphite">{format(t.desk.capacity, { n: String(meta.capacity) })}</span> : null}
              </p>
              <p className="pb-1 text-sm text-graphite">
                {meta && format(t.desk.visitsToday, { n: String(meta.visits_today) })}
                {meta && meta.denied_today > 0 && <> · {format(t.desk.deniedToday, { n: String(meta.denied_today) })}</>}
              </p>
            </div>
            {meta?.capacity ? (
              <div className="mt-3 h-2 overflow-hidden rounded-full bg-surface-2" aria-hidden>
                <div className={`h-full rounded-full ${full > 0.9 ? "bg-critical" : full > 0.7 ? "bg-amber" : "bg-infield"}`} style={{ width: `${Math.min(100, full * 100)}%` }} />
              </div>
            ) : null}
            {inside && inside.data.length === 0 ? (
              <p className="mt-4 text-sm text-graphite">{t.desk.empty}</p>
            ) : (
              <ul className="mt-4 max-h-80 divide-y divide-line overflow-y-auto">
                {inside?.data.map((c) => (
                  <li key={c.id} className="flex items-center gap-3 py-2">
                    {c.member && <MemberAvatar member={c.member} size="sm" />}
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-semibold">{c.member?.full_name}</span>
                      <span className="tabular block text-xs text-graphite">
                        #{c.member?.member_no} · {format(t.desk.since, { time: timeOf(c.checked_in_at) })}
                        {c.locker && <> · {format(t.lockers.short, { n: c.locker.number })}</>}
                      </span>
                    </span>
                    <button
                      type="button"
                      onClick={() => c.id && checkOut(c.id)}
                      className="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-[6px] border border-line-strong px-3 text-xs font-semibold hover:border-ink"
                    >
                      <LogOut aria-hidden className="h-3.5 w-3.5" />
                      {t.desk.checkOut}
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card title={t.desk.recentTitle}>
            {recent && recent.data.length === 0 ? (
              <p className="text-sm text-graphite">{t.desk.recentEmpty}</p>
            ) : (
              <ul className="divide-y divide-line">
                {recent?.data.slice(0, 8).map((c) => (
                  <li key={c.id}>
                    <button type="button" onClick={() => setPopup(c)} className="flex w-full cursor-pointer items-center gap-3 py-2 text-left hover:bg-surface-2">
                      <span className="tabular w-11 shrink-0 text-xs text-graphite">{timeOf(c.checked_in_at)}</span>
                      <span className="min-w-0 flex-1 truncate text-sm font-medium">{c.member?.full_name}</span>
                      <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${RESULT_TONE[c.result]}`}>
                        {c.result === "denied" && c.reason ? t.reasons[c.reason as keyof typeof t.reasons]?.split(/[.。]/)[0] : t.results[c.result]}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>

      {popup && (
        <CheckInPopup
          key={`${popup.id}-${popup.result}`}
          checkIn={popup}
          branchId={branchId}
          lockersEnabled={lockersEnabled}
          onClose={() => setPopup(null)}
          onChanged={(changed) => {
            setPopup(changed);
            void reload();
          }}
        />
      )}
    </>
  );
}
