"use client";

import { KeyRound } from "lucide-react";
import { useState } from "react";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Button } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import type { CheckIn, Locker } from "@/lib/types";

/** Whether the branch hands out lockers (the lockers.enabled setting). */
export function useLockersEnabled(branchId: string | undefined) {
  const { data } = useSWR<{ settings: Record<string, unknown> }>(branchId ? `/branches/${branchId}/settings` : null, swrFetcher);
  return Boolean(data?.settings["lockers.enabled"]);
}

/** In the pop-up: give the next free locker, pick one, change it or take the key back. */
export function LockerPicker({ checkIn, branchId, onChanged }: { checkIn: CheckIn; branchId: string; onChanged: (checkIn: CheckIn) => void }) {
  const { dict } = useDictionary();
  const t = dict.checkins.lockers;
  const [picking, setPicking] = useState(false);
  const { busy, error, general, run } = useFormAction();
  const { data } = useSWR<{ data: Locker[] }>(picking ? `/branches/${branchId}/lockers` : null, swrFetcher);
  const free = data?.data.filter((l) => l.status === "available" && !l.in_use_by) ?? [];

  const assign = (locker?: number) =>
    run(async () => {
      const res = await apiFetch<{ data: CheckIn }>(`/check-ins/${checkIn.id}/locker`, { json: locker ? { locker } : {} });
      setPicking(false);
      onChanged(res.data);
    });

  const message = error?.field("locker") ?? general;

  return (
    <div className="rounded-[6px] border border-line bg-surface-2 p-4">
      <div className="flex flex-wrap items-center gap-3">
        <KeyRound aria-hidden className="h-5 w-5 text-graphite" />
        {checkIn.locker ? (
          <>
            <span className="font-bib tabular text-3xl">{checkIn.locker.number}</span>
            <span className="text-sm text-graphite">{t.zones[checkIn.locker.zone]}</span>
            <span className="ml-auto flex gap-2">
              <Button variant="secondary" className="min-h-9 py-1" onClick={() => setPicking(true)}>
                {t.change}
              </Button>
              <Button
                variant="secondary"
                className="min-h-9 py-1"
                busy={busy}
                onClick={() =>
                  run(async () => {
                    const res = await apiFetch<{ data: CheckIn }>(`/check-ins/${checkIn.id}/locker`, { method: "DELETE" });
                    onChanged(res.data);
                  })
                }
              >
                {t.return}
              </Button>
            </span>
          </>
        ) : (
          <>
            <span className="text-sm font-semibold">{t.title}</span>
            <span className="ml-auto flex gap-2">
              <Button className="min-h-9 py-1" busy={busy} onClick={() => assign()}>
                {t.give}
              </Button>
              {!picking && (
                <Button variant="secondary" className="min-h-9 py-1" onClick={() => setPicking(true)}>
                  {t.pick}
                </Button>
              )}
            </span>
          </>
        )}
      </div>
      {picking && (
        <select
          aria-label={t.pickPlaceholder}
          defaultValue=""
          disabled={!data || busy}
          onChange={(e) => e.target.value && assign(Number(e.target.value))}
          className="mt-3 h-10 w-full rounded-[6px] border border-line-strong bg-surface px-3 text-sm"
        >
          <option value="">{data && free.length === 0 ? t.none : t.pickPlaceholder}</option>
          {free.map((l) => (
            <option key={l.id} value={l.id}>
              {format(t.short, { n: l.number })} · {t.zones[l.zone]}
            </option>
          ))}
        </select>
      )}
      {message && <p className="mt-2 text-xs font-medium text-critical">{message}</p>}
    </div>
  );
}
