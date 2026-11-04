"use client";

import { format, useDictionary } from "@/i18n/dictionary-context";
import { addisToday } from "@/lib/format";
import type { ClassColor, ClassSession } from "@/lib/types";

export const CLASS_COLORS: Record<ClassColor, string> = {
  track: "#c2412d",
  infield: "#2e6b3c",
  amber: "#b7791f",
  sky: "#2b6cb0",
  plum: "#7b3f8f",
  graphite: "#565a56",
};

const TZ = "Africa/Addis_Ababa";

/** "18:00" in Addis Ababa time. */
export function classTime(iso: string): string {
  return new Intl.DateTimeFormat("en-GB", { hour: "2-digit", minute: "2-digit", hour12: false, timeZone: TZ }).format(new Date(iso));
}

/** "2026-11-03" in Addis Ababa time, to group classes by day. */
export function classDay(iso: string): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: TZ }).format(new Date(iso));
}

export function addDaysTo(day: string, n: number): string {
  const d = new Date(`${day}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

export function weekdayOf(day: string): number {
  return new Date(`${day}T12:00:00Z`).getUTCDay();
}

/** Today, tomorrow, then "Thu 5" for the next days. */
export function DayStrip({ value, onChange, days = 7 }: { value: string; onChange: (day: string) => void; days?: number }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const today = addisToday();

  return (
    <div role="tablist" className="flex gap-1.5 overflow-x-auto pb-1">
      {Array.from({ length: days }, (_, i) => addDaysTo(today, i)).map((day, i) => (
        <button
          key={day}
          type="button"
          role="tab"
          aria-selected={value === day}
          onClick={() => onChange(day)}
          className="flex min-w-16 shrink-0 cursor-pointer flex-col items-center rounded-[6px] border border-line bg-surface px-3 py-2 text-xs font-semibold text-graphite aria-selected:border-ink aria-selected:bg-ink aria-selected:text-surface"
        >
          <span>{i === 0 ? t.today : i === 1 ? t.tomorrow : t.weekdaysShort[weekdayOf(day)]}</span>
          <span className="font-bib tabular text-lg leading-tight">{Number(day.slice(8))}</span>
        </button>
      ))}
    </div>
  );
}

/** Coloured edge, name, time, trainer and room, and how full it is. */
export function ClassSummary({ session, compact = false }: { session: ClassSession; compact?: boolean }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const color = CLASS_COLORS[session.class_type?.color ?? "track"];
  const cancelled = session.status === "cancelled";

  return (
    <div className="flex items-stretch gap-3">
      <span aria-hidden className="w-1.5 shrink-0 rounded-full" style={{ background: color, opacity: cancelled ? 0.35 : 1 }} />
      <div className="min-w-0 flex-1">
        <p className={`font-semibold ${cancelled ? "text-graphite line-through" : ""}`}>
          <span className="tabular mr-2">{classTime(session.starts_at)}</span>
          {session.class_type?.name}
        </p>
        <p className="text-xs text-graphite">
          {[session.trainer?.name, session.room?.name, compact ? null : format(t.minutes, { n: String(Math.round((Date.parse(session.ends_at) - Date.parse(session.starts_at)) / 60000)) })].filter(Boolean).join(" · ")}
        </p>
      </div>
      <div className="shrink-0 text-right text-xs">
        {cancelled ? (
          <span className="font-semibold text-critical">{t.cancelled}</span>
        ) : (
          <>
            <span className="tabular block font-semibold text-ink">{format(t.booked, { n: String(session.booked_count), total: String(session.capacity) })}</span>
            {session.waitlist_count > 0 && <span className="tabular block text-amber">{format(t.waiting, { n: String(session.waitlist_count) })}</span>}
          </>
        )}
      </div>
    </div>
  );
}
