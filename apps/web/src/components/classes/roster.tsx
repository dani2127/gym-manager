"use client";

import { Search, X } from "lucide-react";
import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { ClassSummary } from "@/components/classes/shared";
import { MemberAvatar } from "@/components/members/member-avatar";
import { useDebounced } from "@/components/members/use-debounced";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { useNow } from "@/lib/use-now";
import type { ClassBooking, ClassSession, Member, Paginated } from "@/lib/types";

type Detail = ClassSession & { bookings: ClassBooking[] };

/**
 * One class: who's booked, the waitlist, attendance. The desk can also book a
 * member in and cancel bookings; managers can call the class off.
 */
export function Roster({ sessionId, canBook, canCancelClass, onChanged }: { sessionId: string; canBook: boolean; canCancelClass: boolean; onChanged?: () => void }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const { data, mutate } = useSWR<{ data: Detail }>(`/class-sessions/${sessionId}`, swrFetcher, { refreshInterval: 20_000 });
  const { busy, general, run } = useFormAction();
  const [cancelling, setCancelling] = useState(false);
  const now = useNow();
  const s = data?.data;

  const refresh = async () => {
    await mutate();
    onChanged?.();
  };
  const act = (path: string, init: Parameters<typeof apiFetch>[1]) =>
    run(async () => {
      await apiFetch(path, init);
      await refresh();
    });

  if (!s) return <div className="h-48 animate-pulse rounded-[6px] bg-surface-2" />;

  const booked = s.bookings.filter((b) => b.status !== "waitlisted");
  const waiting = s.bookings.filter((b) => b.status === "waitlisted");
  const attendanceOpen = Date.parse(s.starts_at) - 30 * 60_000 <= now;
  const scheduled = s.status === "scheduled";

  function cancelClass(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "");
    act(`/class-sessions/${s!.id}/cancel`, { json: { reason } });
    setCancelling(false);
  }

  return (
    <div className="space-y-5">
      <ClassSummary session={s} />
      {s.status === "cancelled" && s.cancel_reason && <Alert tone="error">{s.cancel_reason}</Alert>}
      {general && <Alert tone="error">{general}</Alert>}

      <section>
        <h3 className="mb-2 text-sm font-semibold text-graphite">
          {t.roster} · {booked.length}/{s.capacity}
        </h3>
        {booked.length === 0 ? (
          <p className="text-sm text-graphite">{t.rosterEmpty}</p>
        ) : (
          <ul className="divide-y divide-line rounded-[6px] border border-line">
            {booked.map((b) => (
              <li key={b.id} className="flex flex-wrap items-center gap-3 px-3 py-2.5">
                {b.member && <MemberAvatar member={b.member} size="sm" />}
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-semibold">{b.member?.full_name}</span>
                  <span className="tabular block text-xs text-graphite">#{b.member?.member_no}</span>
                </span>
                {scheduled && attendanceOpen ? (
                  <span className="flex gap-1">
                    {b.status === "booked" ? (
                      <>
                        <Button className="min-h-8 px-3 py-1" busy={busy} onClick={() => act(`/class-bookings/${b.id}/attendance`, { json: { status: "attended" } })}>
                          {t.present}
                        </Button>
                        <Button variant="secondary" className="min-h-8 px-3 py-1" disabled={busy} onClick={() => act(`/class-bookings/${b.id}/attendance`, { json: { status: "no_show" } })}>
                          {t.noShow}
                        </Button>
                      </>
                    ) : (
                      <>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${b.status === "attended" ? "bg-infield-soft text-infield" : "bg-track-soft text-track-deep"}`}>{t.statuses[b.status]}</span>
                        <button type="button" className="cursor-pointer px-2 text-xs text-graphite hover:underline" onClick={() => act(`/class-bookings/${b.id}/attendance`, { json: { status: "booked" } })}>
                          {t.undo}
                        </button>
                      </>
                    )}
                  </span>
                ) : (
                  canBook &&
                  scheduled && (
                    <button type="button" aria-label={`${t.cancelBooking}: ${b.member?.full_name}`} className="cursor-pointer rounded p-1.5 text-graphite hover:bg-surface-2" onClick={() => act(`/class-bookings/${b.id}`, { method: "DELETE" })}>
                      <X aria-hidden className="h-4 w-4" />
                    </button>
                  )
                )}
              </li>
            ))}
          </ul>
        )}
        {scheduled && !attendanceOpen && booked.length > 0 && <p className="mt-2 text-xs text-graphite">{t.attendanceLater}</p>}
      </section>

      {waiting.length > 0 && (
        <section>
          <h3 className="mb-2 text-sm font-semibold text-amber">
            {t.waitlist} · {waiting.length}
          </h3>
          <ul className="divide-y divide-line rounded-[6px] border border-line">
            {waiting.map((b) => (
              <li key={b.id} className="flex items-center gap-3 px-3 py-2.5">
                <span className="font-bib tabular w-6 text-graphite">{b.waitlist_position}</span>
                <span className="min-w-0 flex-1 truncate font-medium">{b.member?.full_name}</span>
                {canBook && (
                  <button type="button" aria-label={`${t.cancelBooking}: ${b.member?.full_name}`} className="cursor-pointer rounded p-1.5 text-graphite hover:bg-surface-2" onClick={() => act(`/class-bookings/${b.id}`, { method: "DELETE" })}>
                    <X aria-hidden className="h-4 w-4" />
                  </button>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}

      {canBook && scheduled && Date.parse(s.ends_at) > now && <BookMember sessionId={s.id} onBooked={refresh} />}

      {canCancelClass && scheduled && Date.parse(s.starts_at) > now && (
        <section className="border-t border-line pt-4">
          {cancelling ? (
            <form onSubmit={cancelClass} className="space-y-2" noValidate>
              <label className="block text-sm font-semibold">
                {t.cancelReason}
                <Input name="reason" autoFocus className="mt-1" />
              </label>
              <div className="flex gap-2">
                <Button type="submit" variant="danger" busy={busy}>
                  {t.confirmCancelClass}
                </Button>
                <Button type="button" variant="secondary" onClick={() => setCancelling(false)}>
                  {dict.common.cancel}
                </Button>
              </div>
            </form>
          ) : (
            <Button variant="secondary" onClick={() => setCancelling(true)}>
              {t.cancelClass}
            </Button>
          )}
        </section>
      )}
    </div>
  );
}

function BookMember({ sessionId, onBooked }: { sessionId: string; onBooked: () => Promise<void> }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const [query, setQuery] = useState("");
  const search = useDebounced(query.trim(), 200);
  const { data } = useSWR<Paginated<Member>>(search ? `/members?per_page=5&search=${encodeURIComponent(search)}` : null, swrFetcher, { keepPreviousData: true });
  const { busy, error, general, run } = useFormAction();

  const book = (member: Member) =>
    run(async () => {
      await apiFetch(`/class-sessions/${sessionId}/bookings`, { json: { member: member.id } });
      setQuery("");
      await onBooked();
    });

  return (
    <section>
      <h3 className="mb-2 text-sm font-semibold text-graphite">{t.addMember}</h3>
      {(general || error?.field("member")) && <Alert tone="error">{error?.field("member") ?? general}</Alert>}
      <label className="relative block">
        <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-graphite" />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t.searchMember}
          aria-label={t.searchMember}
          className="h-10 w-full rounded-[6px] border border-line-strong bg-surface pr-3 pl-9 text-sm outline-none focus:border-ink"
        />
      </label>
      {search && data && (
        <ul className="mt-1 divide-y divide-line rounded-[6px] border border-line bg-surface">
          {data.data.map((m) => (
            <li key={m.id} className="flex items-center gap-3 px-3 py-2">
              <MemberAvatar member={m} size="sm" />
              <span className="min-w-0 flex-1 truncate text-sm font-medium">
                {m.full_name} <span className="tabular text-graphite">#{m.member_no}</span>
              </span>
              <Button className="min-h-8 px-3 py-1" busy={busy} onClick={() => book(m)}>
                {t.book}
              </Button>
            </li>
          ))}
          {data.data.length === 0 && <li className="px-3 py-3 text-sm text-graphite">{format(dict.members.noResults, { query: search })}</li>}
        </ul>
      )}
    </section>
  );
}
