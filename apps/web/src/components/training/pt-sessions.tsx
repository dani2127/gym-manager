"use client";

import { useState, type FormEvent } from "react";

import { classDay, classTime } from "@/components/classes/shared";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { addisToday, formatDate } from "@/lib/format";
import { useNow } from "@/lib/use-now";
import type { PtSession } from "@/lib/types";

/** Date, time and length for a new session on a package. */
export function ScheduleForm({ enrollmentId, onDone }: { enrollmentId: string; onDone: () => void }) {
  const { dict } = useDictionary();
  const t = dict.training;
  const { busy, error, general, run } = useFormAction();

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const f = new FormData(form);
    run(async () => {
      await apiFetch(`/pt-enrollments/${enrollmentId}/sessions`, { json: { starts_at: `${f.get("date")}T${f.get("time")}`, duration_min: Number(f.get("minutes")) } });
      form.reset();
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="space-y-2" noValidate>
      {(general || error?.field("starts_at")) && <Alert tone="error">{error?.field("starts_at") ?? general}</Alert>}
      <div className="flex flex-wrap items-end gap-2">
        <Field label={t.when}>{(p) => <Input {...p} name="date" type="date" min={addisToday()} required className="w-40" />}</Field>
        <Field label="&nbsp;">{(p) => <Input {...p} name="time" type="time" defaultValue="17:00" required className="w-28" aria-label={t.when} />}</Field>
        <Field label={t.length}>{(p) => <Input {...p} name="minutes" type="number" min={15} max={180} defaultValue={60} className="w-20" />}</Field>
        <Button type="submit" variant="secondary" busy={busy}>
          {t.add}
        </Button>
      </div>
    </form>
  );
}

/** One PT session: when, with whom, and the actions that fit its state. */
export function SessionRow({ session: s, showMember, notes, onChanged }: { session: PtSession; showMember?: boolean; notes?: boolean; onChanged: () => void }) {
  const { locale, dict } = useDictionary();
  const t = dict.training;
  const { busy, general, run } = useFormAction();
  const now = useNow();
  const [editing, setEditing] = useState(false);
  const canMark = Date.parse(s.starts_at) - 15 * 60_000 <= now;

  const act = (path: string, json: object) =>
    run(async () => {
      await apiFetch(path, { json });
      onChanged();
    });

  function saveNotes(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const value = String(new FormData(event.currentTarget).get("notes") ?? "");
    run(async () => {
      await apiFetch(`/pt-sessions/${s.id}/notes`, { method: "PATCH", json: { notes: value || null } });
      setEditing(false);
      onChanged();
    });
  }

  return (
    <li className="py-3">
      <div className="flex flex-wrap items-center gap-3">
        <div className="min-w-0 flex-1">
          <p className="font-semibold">
            <span className="tabular mr-2">{classTime(s.starts_at)}</span>
            {showMember ? s.member?.full_name : formatDate(classDay(s.starts_at), locale)}
          </p>
          <p className="text-xs text-graphite">
            {[showMember ? null : s.trainer?.name, format(t.minutes, { n: String(Math.round((Date.parse(s.ends_at) - Date.parse(s.starts_at)) / 60000)) })].filter(Boolean).join(" · ")}
          </p>
        </div>
        {s.status === "scheduled" ? (
          <span className="flex gap-1">
            {canMark ? (
              <>
                <Button className="min-h-8 px-3 py-1" busy={busy} onClick={() => act(`/pt-sessions/${s.id}/status`, { status: "completed" })}>
                  {t.done}
                </Button>
                <Button variant="secondary" className="min-h-8 px-3 py-1" disabled={busy} onClick={() => act(`/pt-sessions/${s.id}/status`, { status: "no_show" })}>
                  {t.noShow}
                </Button>
              </>
            ) : (
              <Button variant="secondary" className="min-h-8 px-3 py-1" busy={busy} onClick={() => act(`/pt-sessions/${s.id}/cancel`, {})}>
                {t.cancel}
              </Button>
            )}
          </span>
        ) : (
          <span className="flex items-center gap-1">
            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${s.status === "completed" ? "bg-infield-soft text-infield" : s.status === "no_show" ? "bg-track-soft text-track-deep" : "bg-surface-2 text-graphite"}`}>{t.sessionStatuses[s.status]}</span>
            {s.status !== "cancelled" && (
              <button type="button" className="cursor-pointer px-2 text-xs text-graphite hover:underline" onClick={() => act(`/pt-sessions/${s.id}/status`, { status: "scheduled" })}>
                {t.undo}
              </button>
            )}
          </span>
        )}
      </div>
      {general && <div className="mt-2"><Alert tone="error">{general}</Alert></div>}
      {notes && (
        <div className="mt-2">
          {editing ? (
            <form onSubmit={saveNotes} className="space-y-2">
              <textarea name="notes" defaultValue={s.trainer_notes ?? ""} rows={3} maxLength={2000} className="w-full rounded-[6px] border border-line-strong bg-surface p-2 text-sm" aria-label={t.notes} />
              <div className="flex gap-2">
                <Button type="submit" className="min-h-8 px-3 py-1" busy={busy}>
                  {t.saveNotes}
                </Button>
                <Button type="button" variant="secondary" className="min-h-8 px-3 py-1" onClick={() => setEditing(false)}>
                  {dict.common.cancel}
                </Button>
              </div>
            </form>
          ) : (
            <button type="button" onClick={() => setEditing(true)} className="w-full cursor-pointer rounded-[6px] bg-surface-2 px-3 py-2 text-left text-xs text-graphite hover:text-ink">
              {s.trainer_notes ? <span className="whitespace-pre-line text-ink">{s.trainer_notes}</span> : `${t.notes} · ${t.notesHint}`}
            </button>
          )}
        </div>
      )}
    </li>
  );
}
