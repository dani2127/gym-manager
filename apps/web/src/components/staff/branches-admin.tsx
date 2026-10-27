"use client";

import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, PageHeader, Select } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { Branch, OpeningHour } from "@/lib/types";

export function BranchesAdmin() {
  const { dict } = useDictionary();
  const { me } = useSession();
  const { data, mutate } = useSWR<{ data: Branch[] }>("/branches", swrFetcher);
  const [adding, setAdding] = useState(false);
  const t = dict.branches;

  return (
    <>
      <PageHeader
        title={t.title}
        actions={
          me.abilities.manage_branches && !adding && <Button onClick={() => setAdding(true)}>{t.add}</Button>
        }
      />
      <div className="space-y-4">
        {adding && (
          <Card title={t.add}>
            <BranchDetailsForm
              onCancel={() => setAdding(false)}
              onSaved={() => {
                setAdding(false);
                mutate();
              }}
            />
          </Card>
        )}
        {!data ? (
          <p className="text-sm text-muted">{dict.common.loading}</p>
        ) : (
          data.data.map((branch) => <BranchCard key={branch.id} branch={branch} onChanged={() => mutate()} />)
        )}
      </div>
    </>
  );
}

function BranchCard({ branch, onChanged }: { branch: Branch; onChanged: () => void }) {
  const { dict } = useDictionary();
  const { me } = useSession();
  const [editing, setEditing] = useState(false);
  const t = dict.branches;
  // Owners manage every branch; managers only their own (the API enforces the same).
  const canEdit = me.user.account_type === "owner" || me.branches.some((b) => b.id === branch.id && b.role === "manager");

  return (
    <Card
      title={`${branch.name} · ${branch.code}`}
      actions={
        <div className="flex items-center gap-2">
          <span
            className={`rounded-full px-2 py-0.5 text-xs ${branch.status === "active" ? "bg-brand-soft text-brand" : "bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300"}`}
          >
            {branch.status === "active" ? t.active : t.inactive}
          </span>
          {canEdit && (
            <Button variant="secondary" className="px-3 py-1" onClick={() => setEditing((v) => !v)}>
              {editing ? dict.common.cancel : dict.common.edit}
            </Button>
          )}
        </div>
      }
    >
      {!editing ? (
        <p className="text-sm text-muted">{[branch.city, branch.address, branch.phone].filter(Boolean).join(" · ") || "—"}</p>
      ) : (
        <div className="grid gap-8 lg:grid-cols-2">
          <div>
            <h3 className="mb-3 text-sm font-semibold">{t.details}</h3>
            <BranchDetailsForm branch={branch} onSaved={onChanged} />
          </div>
          <div>
            <h3 className="mb-3 text-sm font-semibold">{t.hours}</h3>
            <OpeningHoursEditor branchId={branch.id} />
          </div>
        </div>
      )}
    </Card>
  );
}

function BranchDetailsForm({ branch, onSaved, onCancel }: { branch?: Branch; onSaved: () => void; onCancel?: () => void }) {
  const { dict } = useDictionary();
  const { me, refresh } = useSession();
  const { busy, error, saved, general, run } = useFormAction();
  const t = dict.branches;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = Object.fromEntries(new FormData(event.currentTarget));
    const payload = {
      ...form,
      code: String(form.code).toUpperCase(),
      capacity: form.capacity ? Number(form.capacity) : null,
      phone: form.phone || null,
    };

    run(async () => {
      await apiFetch(branch ? `/branches/${branch.id}` : "/branches", { method: branch ? "PATCH" : "POST", json: payload });
      await refresh();
      onSaved();
    });
  }

  return (
    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2" noValidate>
      {general && (
        <div className="sm:col-span-2">
          <Alert tone="error">{general}</Alert>
        </div>
      )}
      <Field label={t.name} error={error?.field("name")}>
        {(p) => <Input {...p} name="name" defaultValue={branch?.name} required />}
      </Field>
      <Field label={t.code} hint={t.codeHint} error={error?.field("code")}>
        {(p) => <Input {...p} name="code" defaultValue={branch?.code} required className="uppercase" />}
      </Field>
      <Field label={t.city} error={error?.field("city")}>
        {(p) => <Input {...p} name="city" defaultValue={branch?.city ?? ""} />}
      </Field>
      <Field label={t.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" defaultValue={branch?.phone ?? ""} placeholder="0911 234 567" />}
      </Field>
      <Field label={t.address} error={error?.field("address")}>
        {(p) => <Input {...p} name="address" defaultValue={branch?.address ?? ""} />}
      </Field>
      <Field label={t.capacity} error={error?.field("capacity")}>
        {(p) => <Input {...p} name="capacity" type="number" min={1} defaultValue={branch?.capacity ?? ""} />}
      </Field>
      {branch && me.abilities.manage_branches && (
        <Field label={t.status} error={error?.field("status")}>
          {(p) => (
            <Select {...p} name="status" defaultValue={branch.status}>
              <option value="active">{t.active}</option>
              <option value="inactive">{t.inactive}</option>
            </Select>
          )}
        </Field>
      )}
      <div className="flex items-center gap-3 sm:col-span-2">
        <Button type="submit" busy={busy}>
          {busy ? dict.common.saving : dict.common.save}
        </Button>
        {onCancel && (
          <Button type="button" variant="secondary" onClick={onCancel}>
            {dict.common.cancel}
          </Button>
        )}
        {saved && branch && <span className="text-sm text-brand">{dict.common.saved}</span>}
      </div>
    </form>
  );
}

function OpeningHoursEditor({ branchId }: { branchId: string }) {
  const { dict } = useDictionary();
  const { data } = useSWR<{ data: Branch }>(`/branches/${branchId}`, swrFetcher);

  if (!data?.data.opening_hours) return <p className="text-sm text-muted">{dict.common.loading}</p>;

  return <OpeningHoursForm branchId={branchId} initial={data.data.opening_hours} />;
}

function OpeningHoursForm({ branchId, initial }: { branchId: string; initial: OpeningHour[] }) {
  const { dict } = useDictionary();
  const { busy, error, saved, general, run } = useFormAction();
  const [days, setDays] = useState(initial);
  const t = dict.branches;

  function update(weekday: number, patch: Partial<OpeningHour>) {
    setDays((current) => current.map((day) => (day.weekday === weekday ? { ...day, ...patch } : day)));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    run(() =>
      apiFetch(`/branches/${branchId}/opening-hours`, {
        method: "PUT",
        json: {
          days: days.map((day) =>
            day.is_closed
              ? { weekday: day.weekday, is_closed: true }
              : { weekday: day.weekday, is_closed: false, opens_at: day.opens_at ?? "06:00", closes_at: day.closes_at ?? "22:00" },
          ),
        },
      }),
    );
  }

  return (
    <form onSubmit={submit} className="space-y-2">
      {general && <Alert tone="error">{general}</Alert>}
      {days.map((day, index) => {
        const dayError = error?.field(`days.${index}.closes_at`) ?? error?.field(`days.${index}.opens_at`);
        return (
          <div key={day.weekday} className="grid grid-cols-[6rem_auto_1fr_1fr] items-center gap-2 text-sm">
            <span className="font-medium">{t.weekdays[day.weekday]}</span>
            <label className="flex items-center gap-1 text-xs text-muted">
              <input type="checkbox" checked={day.is_closed} onChange={(e) => update(day.weekday, { is_closed: e.target.checked })} />
              {t.closed}
            </label>
            <Input
              type="time"
              aria-label={`${t.weekdays[day.weekday]} opens`}
              value={day.opens_at ?? "06:00"}
              disabled={day.is_closed}
              onChange={(e) => update(day.weekday, { opens_at: e.target.value })}
              aria-invalid={dayError ? true : undefined}
            />
            <Input
              type="time"
              aria-label={`${t.weekdays[day.weekday]} closes`}
              value={day.closes_at ?? "22:00"}
              disabled={day.is_closed}
              onChange={(e) => update(day.weekday, { closes_at: e.target.value })}
              aria-invalid={dayError ? true : undefined}
            />
            {dayError && <p className="col-span-4 text-xs text-red-600">{dayError}</p>}
          </div>
        );
      })}
      <div className="flex items-center gap-3 pt-2">
        <Button type="submit" busy={busy}>
          {t.saveHours}
        </Button>
        {saved && <span className="text-sm text-brand">{dict.common.saved}</span>}
      </div>
    </form>
  );
}
