"use client";

import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, PageHeader, Select } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, type ApiError, swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { BranchRole, StaffMember } from "@/lib/types";

type Assignment = { branch: string; role: BranchRole };

export function StaffAdmin() {
  const { dict } = useDictionary();
  const { me } = useSession();
  const { data, mutate } = useSWR<{ data: StaffMember[] }>("/staff", swrFetcher);
  const [adding, setAdding] = useState(false);
  const t = dict.staffPage;

  if (!me.abilities.manage_staff) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return (
    <>
      <PageHeader title={t.title} actions={!adding && <Button onClick={() => setAdding(true)}>{t.add}</Button>} />
      <div className="space-y-4">
        {adding && (
          <Card title={t.add}>
            <NewStaffForm
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
          <ul className="divide-y divide-border rounded-2xl border border-border bg-surface">
            {data.data.map((person) => (
              <StaffRow key={person.id} person={person} onChanged={() => mutate()} />
            ))}
          </ul>
        )}
      </div>
    </>
  );
}

/** Branches the current user may assign people to, and the roles they may give. */
function useAssignable() {
  const { me } = useSession();
  const owner = me.user.account_type === "owner";

  return {
    branches: owner ? me.branches : me.branches.filter((b) => b.role === "manager"),
    roles: (owner ? ["manager", "receptionist", "trainer"] : ["receptionist", "trainer"]) as BranchRole[],
  };
}

function StaffRow({ person, onChanged }: { person: StaffMember; onChanged: () => void }) {
  const { dict } = useDictionary();
  const { me } = useSession();
  const { busy, general, run } = useFormAction();
  const [editing, setEditing] = useState(false);
  const t = dict.staffPage;

  const isOwner = person.account_type === "owner";
  const isSelf = person.id === me.user.id;
  const managesAll = me.user.account_type === "owner";
  const editable = !isOwner && !isSelf && (managesAll || person.assignments.every((a) => a.role !== "manager"));

  function toggleStatus() {
    run(async () => {
      await apiFetch(`/staff/${person.id}`, { method: "PATCH", json: { status: person.status === "active" ? "disabled" : "active" } });
      onChanged();
    });
  }

  return (
    <li className="p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-medium">
            {person.name}
            {person.status === "disabled" && (
              <span className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                {t.disabledBadge}
              </span>
            )}
          </p>
          <p className="text-sm text-muted">{person.phone}</p>
          <div className="mt-2 flex flex-wrap gap-1">
            {isOwner ? (
              <Badge>
                {t.roles.owner} · {t.allBranches}
              </Badge>
            ) : (
              person.assignments.map((a) => (
                <Badge key={a.branch.id}>
                  {a.branch.name} · {t.roles[a.role]}
                </Badge>
              ))
            )}
          </div>
        </div>
        {editable && (
          <div className="flex gap-2">
            <Button variant="secondary" className="px-3 py-1" onClick={() => setEditing((v) => !v)}>
              {editing ? dict.common.cancel : dict.common.edit}
            </Button>
            <Button variant={person.status === "active" ? "danger" : "secondary"} className="px-3 py-1" busy={busy} onClick={toggleStatus}>
              {person.status === "active" ? t.disable : t.enable}
            </Button>
          </div>
        )}
      </div>
      {general && (
        <div className="mt-3">
          <Alert tone="error">{general}</Alert>
        </div>
      )}
      {editing && (
        <div className="mt-4">
          <EditAssignmentsForm
            person={person}
            onSaved={() => {
              setEditing(false);
              onChanged();
            }}
          />
        </div>
      )}
    </li>
  );
}

function Badge({ children }: { children: React.ReactNode }) {
  return <span className="rounded-full bg-brand-soft px-2 py-0.5 text-xs text-brand">{children}</span>;
}

function NewStaffForm({ onSaved, onCancel }: { onSaved: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const assignable = useAssignable();
  const { busy, error, general, run } = useFormAction();
  const [assignments, setAssignments] = useState<Assignment[]>([
    { branch: assignable.branches[0]?.id ?? "", role: assignable.roles.at(-1) ?? "trainer" },
  ]);
  const t = dict.staffPage;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);

    run(async () => {
      await apiFetch("/staff", {
        json: {
          name: form.get("name"),
          phone: form.get("phone"),
          email: form.get("email") || null,
          password: form.get("password"),
          assignments,
        },
      });
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
        {(p) => <Input {...p} name="name" required />}
      </Field>
      <Field label={t.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" placeholder="0911 234 567" required />}
      </Field>
      <Field label={t.email} error={error?.field("email")}>
        {(p) => <Input {...p} name="email" type="email" />}
      </Field>
      <Field label={t.password} hint={t.passwordHint} error={error?.field("password")}>
        {(p) => <Input {...p} name="password" type="text" autoComplete="new-password" required minLength={8} />}
      </Field>
      <div className="sm:col-span-2">
        <AssignmentRows value={assignments} onChange={setAssignments} error={error} />
      </div>
      <div className="flex gap-3 sm:col-span-2">
        <Button type="submit" busy={busy}>
          {busy ? dict.common.saving : dict.common.save}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
      </div>
    </form>
  );
}

function EditAssignmentsForm({ person, onSaved }: { person: StaffMember; onSaved: () => void }) {
  const { dict } = useDictionary();
  const assignable = useAssignable();
  const { busy, error, general, run } = useFormAction();
  // Managers only see and edit assignments in branches they manage; the API keeps the rest.
  const [assignments, setAssignments] = useState<Assignment[]>(
    person.assignments
      .filter((a) => assignable.branches.some((b) => b.id === a.branch.id))
      .map((a) => ({ branch: a.branch.id, role: a.role })),
  );

  function submit(event: FormEvent) {
    event.preventDefault();
    run(async () => {
      await apiFetch(`/staff/${person.id}`, { method: "PATCH", json: { assignments } });
      onSaved();
    });
  }

  return (
    <form onSubmit={submit} className="space-y-4 rounded-xl bg-background p-4">
      {general && <Alert tone="error">{general}</Alert>}
      <AssignmentRows value={assignments} onChange={setAssignments} error={error} />
      <Button type="submit" busy={busy}>
        {busy ? dict.common.saving : dict.common.save}
      </Button>
    </form>
  );
}

function AssignmentRows({
  value,
  onChange,
  error,
}: {
  value: Assignment[];
  onChange: (next: Assignment[]) => void;
  error: ApiError | null;
}) {
  const { dict } = useDictionary();
  const assignable = useAssignable();
  const t = dict.staffPage;
  const unused = assignable.branches.filter((b) => !value.some((a) => a.branch === b.id));

  const set = (index: number, patch: Partial<Assignment>) => onChange(value.map((a, i) => (i === index ? { ...a, ...patch } : a)));

  return (
    <fieldset className="space-y-2">
      <legend className="text-sm font-medium">{t.assignments}</legend>
      {error?.field("assignments") && <p className="text-xs text-red-600">{error.field("assignments")}</p>}
      {value.map((assignment, index) => {
        const rowError = error?.field(`assignments.${index}.branch`) ?? error?.field(`assignments.${index}.role`);
        return (
          <div key={index} className="grid grid-cols-[1fr_1fr_auto] items-center gap-2">
            <Select aria-label={t.branch} value={assignment.branch} onChange={(e) => set(index, { branch: e.target.value })}>
              {assignable.branches
                .filter((b) => b.id === assignment.branch || unused.some((u) => u.id === b.id))
                .map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
            </Select>
            <Select aria-label={t.role} value={assignment.role} onChange={(e) => set(index, { role: e.target.value as BranchRole })}>
              {assignable.roles.map((role) => (
                <option key={role} value={role}>
                  {t.roles[role]}
                </option>
              ))}
            </Select>
            <Button
              type="button"
              variant="secondary"
              className="px-3"
              disabled={value.length === 1}
              onClick={() => onChange(value.filter((_, i) => i !== index))}
            >
              {dict.common.remove}
            </Button>
            {rowError && <p className="col-span-3 text-xs text-red-600">{rowError}</p>}
          </div>
        );
      })}
      {unused.length > 0 && (
        <button
          type="button"
          className="text-sm text-brand hover:underline"
          onClick={() => onChange([...value, { branch: unused[0].id, role: assignable.roles.at(-1) ?? "trainer" }])}
        >
          + {t.addAssignment}
        </button>
      )}
    </fieldset>
  );
}
