"use client";

import { Pencil, Plus, X } from "lucide-react";
import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { CLASS_COLORS } from "@/components/classes/shared";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, PageHeader, Select, Toggle } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { isOwner, managesBranch } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { ClassColor, ClassSchedule, ClassType, Room, StaffMember } from "@/lib/types";

const COLORS: ClassColor[] = ["track", "infield", "amber", "sky", "plum", "graphite"];
// Monday first, the way the week is read at the desk.
const WEEK = [1, 2, 3, 4, 5, 6, 0];

/** Class types (owner), and the selected branch's rooms and weekly timetable (managers). */
export function ClassesAdmin() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.classes;
  const owner = isOwner(me);
  const manages = managesBranch(me, branch?.id);

  if (!owner && !manages) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader title={t.adminTitle} description={t.adminDescription} />
      <div className="space-y-6">
        <TypesCard editable={owner} />
        {branch && (
          <>
            <TimetableCard branchId={branch.id} branchName={branch.name} />
            <RoomsCard branchId={branch.id} />
          </>
        )}
      </div>
    </>
  );
}

function TypesCard({ editable }: { editable: boolean }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const { data, mutate } = useSWR<{ data: ClassType[] }>("/class-types", swrFetcher);
  const [editing, setEditing] = useState<ClassType | "new" | null>(null);
  const done = () => {
    setEditing(null);
    mutate();
  };

  return (
    <Card
      title={t.types}
      actions={
        editable &&
        editing === null && (
          <Button variant="secondary" className="min-h-9" onClick={() => setEditing("new")}>
            <Plus aria-hidden className="h-4 w-4" />
            {t.addType}
          </Button>
        )
      }
    >
      {editing === "new" && <TypeForm onDone={done} onCancel={() => setEditing(null)} />}
      {!data ? (
        <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
      ) : data.data.length === 0 && editing !== "new" ? (
        <p className="text-sm text-graphite">{t.typesEmpty}</p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {data.data.map((type) =>
            editing !== "new" && editing?.id === type.id ? (
              <li key={type.id} className="sm:col-span-2 lg:col-span-4">
                <TypeForm type={type} onDone={done} onCancel={() => setEditing(null)} />
              </li>
            ) : (
              <li key={type.id} className={`flex items-start gap-3 rounded-[6px] border border-line p-3 ${type.is_active ? "" : "opacity-55"}`}>
                <span aria-hidden className="mt-1 h-8 w-1.5 rounded-full" style={{ background: CLASS_COLORS[type.color] }} />
                <span className="min-w-0 flex-1">
                  <span className="block font-semibold">{type.name}</span>
                  <span className="block text-xs text-graphite">
                    {format(t.minutes, { n: String(type.default_duration_min) })} · {type.default_capacity} {t.capacity.toLowerCase()}
                    {!type.is_active && ` · ${t.inactive}`}
                  </span>
                </span>
                {editable && editing === null && (
                  <button type="button" aria-label={`${dict.common.edit}: ${type.name}`} onClick={() => setEditing(type)} className="cursor-pointer rounded p-1 text-graphite hover:bg-surface-2">
                    <Pencil aria-hidden className="h-4 w-4" />
                  </button>
                )}
              </li>
            ),
          )}
        </ul>
      )}
    </Card>
  );
}

function TypeForm({ type, onDone, onCancel }: { type?: ClassType; onDone: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const { busy, error, general, run } = useFormAction();
  const [color, setColor] = useState<ClassColor>(type?.color ?? "track");
  const [active, setActive] = useState(type?.is_active ?? true);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    run(async () => {
      await apiFetch(type ? `/class-types/${type.id}` : "/class-types", {
        method: type ? "PATCH" : "POST",
        json: {
          name: { en: String(f.get("name_en") ?? "").trim(), am: String(f.get("name_am") ?? "").trim() || null },
          color,
          default_duration_min: Number(f.get("duration")),
          default_capacity: Number(f.get("capacity")),
          is_active: active,
        },
      });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="mb-4 space-y-4 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t.nameEn} error={error?.field("name.en")}>
          {(p) => <Input {...p} name="name_en" defaultValue={type?.name_translations.en} required />}
        </Field>
        <Field label={t.nameAm} error={error?.field("name.am")}>
          {(p) => <Input {...p} name="name_am" defaultValue={type?.name_translations.am ?? ""} />}
        </Field>
        <Field label={t.duration} error={error?.field("default_duration_min")}>
          {(p) => <Input {...p} name="duration" type="number" min={10} max={240} defaultValue={type?.default_duration_min ?? 60} required />}
        </Field>
        <Field label={t.capacity} error={error?.field("default_capacity")}>
          {(p) => <Input {...p} name="capacity" type="number" min={1} max={500} defaultValue={type?.default_capacity ?? 20} required />}
        </Field>
      </div>
      <fieldset>
        <legend className="mb-2 text-sm font-semibold">{t.color}</legend>
        <div className="flex flex-wrap gap-2">
          {COLORS.map((c) => (
            <button
              key={c}
              type="button"
              aria-pressed={color === c}
              aria-label={t.colors[c]}
              onClick={() => setColor(c)}
              className="h-9 w-9 cursor-pointer rounded-full ring-offset-2 aria-pressed:ring-2 aria-pressed:ring-ink"
              style={{ background: CLASS_COLORS[c] }}
            />
          ))}
        </div>
      </fieldset>
      {type && (
        <div className="rounded-[6px] border border-line bg-surface px-4">
          <Toggle label={t.active} checked={active} onChange={setActive} />
        </div>
      )}
      <div className="flex gap-3">
        <Button type="submit" busy={busy}>
          {dict.common.save}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
      </div>
    </form>
  );
}

function TimetableCard({ branchId, branchName }: { branchId: string; branchName: string }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const { data, mutate } = useSWR<{ data: ClassSchedule[] }>(`/branches/${branchId}/class-schedules`, swrFetcher);
  const [editing, setEditing] = useState<ClassSchedule | "new" | null>(null);
  const slots = data?.data ?? [];
  const done = () => {
    setEditing(null);
    mutate();
  };

  return (
    <Card
      title={`${t.timetable} · ${branchName}`}
      actions={
        editing === null && (
          <Button variant="secondary" className="min-h-9" onClick={() => setEditing("new")}>
            <Plus aria-hidden className="h-4 w-4" />
            {t.addSlot}
          </Button>
        )
      }
    >
      {editing !== null && <SlotForm branchId={branchId} slot={editing === "new" ? undefined : editing} onDone={done} onCancel={() => setEditing(null)} />}
      {!data ? (
        <div className="h-40 animate-pulse rounded-[6px] bg-surface-2" />
      ) : slots.length === 0 ? (
        <p className="text-sm text-graphite">{t.timetableEmpty}</p>
      ) : (
        <div className="grid gap-2 overflow-x-auto sm:grid-cols-7">
          {WEEK.map((day) => (
            <div key={day} className="min-w-0">
              <p className="mb-2 text-xs font-semibold text-graphite">{t.weekdays[day]}</p>
              <ul className="space-y-1.5">
                {slots
                  .filter((s) => s.weekday === day)
                  .map((s) => (
                    <li key={s.id}>
                      <button
                        type="button"
                        onClick={() => setEditing(s)}
                        className="w-full cursor-pointer rounded-[6px] border border-line px-2 py-1.5 text-left text-xs hover:border-ink"
                        style={{ borderLeft: `4px solid ${CLASS_COLORS[s.class_type?.color ?? "track"]}` }}
                      >
                        <span className="tabular block font-semibold">{s.start_time}</span>
                        <span className="block truncate font-medium text-ink">{s.class_type?.name}</span>
                        <span className="block truncate text-graphite">{[s.trainer?.name, s.room?.name].filter(Boolean).join(" · ")}</span>
                      </button>
                    </li>
                  ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

function SlotForm({ branchId, slot, onDone, onCancel }: { branchId: string; slot?: ClassSchedule; onDone: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const { busy, error, general, run } = useFormAction();
  const { data: types } = useSWR<{ data: ClassType[] }>("/class-types", swrFetcher);
  const { data: rooms } = useSWR<{ data: Room[] }>(`/branches/${branchId}/rooms`, swrFetcher);
  const { data: staff } = useSWR<{ data: StaffMember[] }>("/staff", swrFetcher);
  const active = (types?.data ?? []).filter((x) => x.is_active);
  const [typeId, setTypeId] = useState(slot?.class_type?.id ?? "");
  const chosen = active.find((x) => x.id === (typeId || active[0]?.id));
  const people = (staff?.data ?? []).filter((p) => p.status === "active" && (p.account_type === "owner" || p.assignments.some((a) => a.branch.id === branchId)));

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    run(async () => {
      await apiFetch(slot ? `/branches/${branchId}/class-schedules/${slot.id}` : `/branches/${branchId}/class-schedules`, {
        method: slot ? "PATCH" : "POST",
        json: {
          class_type: String(f.get("class_type")),
          weekday: Number(f.get("weekday")),
          start_time: String(f.get("start_time")),
          duration_min: Number(f.get("duration")),
          capacity: Number(f.get("capacity")),
          trainer: String(f.get("trainer") ?? "") || null,
          room: String(f.get("room") ?? "") || null,
        },
      });
      onDone();
    });
  }

  function remove() {
    if (!slot) return;
    run(async () => {
      await apiFetch(`/branches/${branchId}/class-schedules/${slot.id}`, { method: "DELETE" });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="mb-5 space-y-4 rounded-[6px] border border-line-strong bg-ground/40 p-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="grid gap-4 sm:grid-cols-3">
        <Field label={t.classType} error={error?.field("class_type")}>
          {(p) => (
            <Select {...p} name="class_type" value={typeId || chosen?.id || ""} onChange={(e) => setTypeId(e.target.value)}>
              {active.map((x) => (
                <option key={x.id} value={x.id}>
                  {x.name}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.weekday} error={error?.field("weekday")}>
          {(p) => (
            <Select {...p} name="weekday" defaultValue={slot?.weekday ?? 1}>
              {WEEK.map((d) => (
                <option key={d} value={d}>
                  {t.weekdays[d]}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.startTime} error={error?.field("start_time")}>
          {(p) => <Input {...p} name="start_time" type="time" defaultValue={slot?.start_time ?? "18:00"} required />}
        </Field>
        <Field label={t.duration} error={error?.field("duration_min")}>
          {(p) => <Input {...p} key={`d-${chosen?.id}`} name="duration" type="number" min={10} max={240} defaultValue={slot?.duration_min ?? chosen?.default_duration_min ?? 60} required />}
        </Field>
        <Field label={t.capacity} error={error?.field("capacity")}>
          {(p) => <Input {...p} key={`c-${chosen?.id}`} name="capacity" type="number" min={1} max={500} defaultValue={slot?.capacity ?? chosen?.default_capacity ?? 20} required />}
        </Field>
        <Field label={t.trainer} error={error?.field("trainer")}>
          {(p) => (
            <Select {...p} name="trainer" defaultValue={slot?.trainer?.id ?? ""}>
              <option value="">{t.none}</option>
              {people.map((x) => (
                <option key={x.id} value={x.id}>
                  {x.name}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.room} error={error?.field("room")}>
          {(p) => (
            <Select {...p} name="room" defaultValue={slot?.room?.id ?? ""}>
              <option value="">{t.none}</option>
              {(rooms?.data ?? []).map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name}
                </option>
              ))}
            </Select>
          )}
        </Field>
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit" busy={busy}>
          {dict.common.save}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
        {slot && (
          <Button type="button" variant="danger" className="ml-auto" disabled={busy} onClick={remove}>
            {t.remove}
          </Button>
        )}
      </div>
    </form>
  );
}

function RoomsCard({ branchId }: { branchId: string }) {
  const { dict } = useDictionary();
  const t = dict.classes;
  const { data, mutate } = useSWR<{ data: Room[] }>(`/branches/${branchId}/rooms`, swrFetcher);
  const { busy, error, general, run } = useFormAction();

  function add(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const f = new FormData(form);
    run(async () => {
      await apiFetch(`/branches/${branchId}/rooms`, { json: { name: String(f.get("name") ?? "").trim(), capacity: Number(f.get("capacity")) || null } });
      form.reset();
      await mutate();
    });
  }

  const remove = (room: Room) =>
    run(async () => {
      await apiFetch(`/branches/${branchId}/rooms/${room.id}`, { method: "DELETE" });
      await mutate();
    });

  return (
    <Card title={t.rooms}>
      {general && <Alert tone="error">{general}</Alert>}
      {data && data.data.length === 0 && <p className="mb-3 text-sm text-graphite">{t.roomsEmpty}</p>}
      <ul className="mb-4 flex flex-wrap gap-2">
        {(data?.data ?? []).map((r) => (
          <li key={r.id} className="flex items-center gap-2 rounded-full border border-line py-1 pr-1 pl-3 text-sm">
            <span className="font-medium">{r.name}</span>
            {r.capacity && <span className="text-xs text-graphite">{r.capacity}</span>}
            <button type="button" aria-label={`${t.remove}: ${r.name}`} onClick={() => remove(r)} className="cursor-pointer rounded-full p-1 text-graphite hover:bg-surface-2">
              <X aria-hidden className="h-3.5 w-3.5" />
            </button>
          </li>
        ))}
      </ul>
      <form onSubmit={add} className="flex flex-wrap items-end gap-3" noValidate>
        <Field label={t.roomName} error={error?.field("name")}>
          {(p) => <Input {...p} name="name" className="w-56" required />}
        </Field>
        <Field label={t.capacity} error={error?.field("capacity")}>
          {(p) => <Input {...p} name="capacity" type="number" min={1} className="w-24" />}
        </Field>
        <Button type="submit" variant="secondary" busy={busy}>
          {t.addRoom}
        </Button>
      </form>
    </Card>
  );
}
