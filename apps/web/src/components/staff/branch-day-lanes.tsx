"use client";

import { useSyncExternalStore } from "react";

import { format, useDictionary } from "@/i18n/dictionary-context";
import type { Branch } from "@/lib/types";

const AXIS_START = 5 * 60; // 05:00
const AXIS_END = 23 * 60; // 23:00
const TICKS = [6, 9, 12, 15, 18, 21];

/** Minutes since midnight and weekday in Addis Ababa, whatever the device's zone. */
function addisNow() {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: "Africa/Addis_Ababa",
    weekday: "short",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).formatToParts(new Date());
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? "0";
  return {
    minutes: Number(get("hour")) * 60 + Number(get("minute")),
    weekday: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"].indexOf(get("weekday")),
  };
}

// Re-render once a minute so the now-line moves.
function subscribe(onChange: () => void) {
  const id = window.setInterval(onChange, 60_000);
  return () => window.clearInterval(id);
}
const snapshot = () => Math.floor(Date.now() / 60_000);

const toMinutes = (time: string) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
const pos = (minutes: number) => `${((Math.min(Math.max(minutes, AXIS_START), AXIS_END) - AXIS_START) / (AXIS_END - AXIS_START)) * 100}%`;

/**
 * Branches as lanes on one clock: each lane shows today's opening window, the
 * part already run, and a text status; a shared line marks now.
 */
export function BranchDayLanes({ branches }: { branches: Branch[] }) {
  const { dict } = useDictionary();
  const t = dict.adminPage;
  useSyncExternalStore(subscribe, snapshot, () => 0);
  const now = addisNow();

  return (
    <div className="overflow-x-auto">
      <div className="min-w-[36rem]">
        <div className="grid grid-cols-[9rem_1fr_11rem] gap-4 pb-2 text-xs text-graphite">
          <span />
          <div className="relative h-4">
            {TICKS.map((h) => (
              <span key={h} className="absolute -translate-x-1/2 tabular" style={{ left: pos(h * 60) }}>
                {String(h).padStart(2, "0")}:00
              </span>
            ))}
          </div>
          <span />
        </div>

        <div className="relative">
          <ul className="divide-y divide-line border-y border-line">
            {branches.map((branch) => {
              const today = branch.opening_hours?.find((h) => h.weekday === now.weekday);
              const open = today && !today.is_closed && today.opens_at && today.closes_at ? [toMinutes(today.opens_at), toMinutes(today.closes_at)] : null;
              const inactive = branch.status !== "active";

              let status: { text: string; tone: string };
              if (inactive) status = { text: t.inactive, tone: "text-graphite" };
              else if (!open) status = { text: t.closedDay, tone: "text-graphite" };
              else if (now.minutes < open[0]) status = { text: format(t.opensAt, { time: today!.opens_at! }), tone: "text-amber" };
              else if (now.minutes < open[1]) status = { text: format(t.openUntil, { time: today!.closes_at! }), tone: "text-infield" };
              else status = { text: t.closedNow, tone: "text-graphite" };

              return (
                <li key={branch.id} className="grid h-14 grid-cols-[9rem_1fr_11rem] items-center gap-4">
                  <span className="truncate text-sm font-semibold">{branch.name}</span>
                  <div className="relative h-3 rounded-full bg-surface-2 ring-1 ring-line ring-inset">
                    {open && !inactive && (
                      <>
                        <span
                          className="absolute inset-y-0 rounded-full bg-track-soft ring-1 ring-track/30 ring-inset"
                          style={{ left: pos(open[0]), width: `calc(${pos(open[1])} - ${pos(open[0])})` }}
                        />
                        <span
                          className="absolute inset-y-0 rounded-full bg-track"
                          style={{ left: pos(open[0]), width: `calc(${pos(Math.min(now.minutes, open[1]))} - ${pos(open[0])})` }}
                        />
                      </>
                    )}
                  </div>
                  <span className={`text-sm font-medium ${status.tone}`}>{status.text}</span>
                </li>
              );
            })}
          </ul>
          {/* Shared now-line across every lane */}
          <div className="pointer-events-none absolute inset-y-0 grid w-full grid-cols-[9rem_1fr_11rem] gap-4" aria-hidden>
            <span />
            <div className="relative">
              <span className="absolute inset-y-[-6px] w-0.5 -translate-x-1/2 bg-ink" style={{ left: pos(now.minutes) }} />
            </div>
            <span />
          </div>
        </div>
      </div>
    </div>
  );
}
