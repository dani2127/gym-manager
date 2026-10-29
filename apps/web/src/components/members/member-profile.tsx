"use client";

import { ArrowLeft, DoorOpen, Pencil, Trash2, X } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { MemberInvoicesCard, MembershipsCard } from "@/components/billing/member-billing";
import { CheckInPopup } from "@/components/checkins/check-in-popup";
import { useLockersEnabled } from "@/components/checkins/locker-picker";
import { MemberCardPanel } from "@/components/checkins/member-card";
import { MemberAvatar, StatusBadge } from "@/components/members/member-avatar";
import { MemberForm } from "@/components/members/member-form";
import { useDebounced } from "@/components/members/use-debounced";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, Select } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch, swrFetcher } from "@/lib/api";
import { formatDate, formatPhone } from "@/lib/format";
import { canDeleteMemberAt, canRegisterAt, handlesMoneyAt } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { CheckIn, Member, MemberGroup, MemberStatus, Paginated } from "@/lib/types";

export function MemberProfile({ id }: { id: string }) {
  const { locale, dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.members;
  const { data, error, mutate } = useSWR<{ data: Member }>(`/members/${id}`, swrFetcher);
  const [editing, setEditing] = useState(false);
  const [checkIn, setCheckIn] = useState<CheckIn | null>(null);
  const checkInAction = useFormAction();
  const lockersEnabled = useLockersEnabled(branch?.id);

  if (error instanceof ApiError && error.status === 404) {
    return (
      <div className="space-y-4">
        <BackLink label={t.back} />
        <Alert tone="error">{t.notFound}</Alert>
      </div>
    );
  }
  if (!data) return <div className="h-64 animate-pulse rounded-[6px] bg-surface" />;

  const member = data.data;
  const canEdit = canRegisterAt(me, member.home_branch?.id);

  return (
    <div className="space-y-6">
      <BackLink label={t.back} />

      {/* Header: the member as their bib */}
      <section className="overflow-hidden rounded-[6px] border border-line bg-surface">
        <div className="h-2 bg-track" aria-hidden />
        <div className="flex flex-wrap items-center gap-6 p-6">
          <MemberAvatar member={member} size="lg" />
          <div className="min-w-0 flex-1">
            <p className="font-bib tabular text-6xl text-ink">{member.member_no}</p>
            <h1 className="mt-1 text-2xl font-bold tracking-tight">{member.full_name}</h1>
            <p className="mt-1 text-sm text-graphite">
              {member.home_branch?.name} · {format(t.memberSince, { date: formatDate(member.joined_at, locale) })}
            </p>
          </div>
          <div className="flex flex-col items-end gap-3">
            <StatusBadge status={member.status} label={t.status[member.status]} />
            {branch && handlesMoneyAt(me, branch.id) && (
              <Button
                busy={checkInAction.busy}
                onClick={() =>
                  checkInAction.run(async () => {
                    const res = await apiFetch<{ data: CheckIn }>(`/branches/${branch.id}/check-ins`, { json: { member: member.id } });
                    setCheckIn(res.data);
                  })
                }
              >
                <DoorOpen aria-hidden className="h-4 w-4" />
                {dict.checkins.profile.checkIn}
              </Button>
            )}
            {canEdit && !editing && (
              <Button variant="secondary" onClick={() => setEditing(true)}>
                <Pencil aria-hidden className="h-4 w-4" />
                {t.edit}
              </Button>
            )}
          </div>
        </div>
      </section>

      {checkIn && (
        <CheckInPopup
          key={`${checkIn.id}-${checkIn.result}`}
          checkIn={checkIn}
          branchId={branch?.id}
          lockersEnabled={lockersEnabled}
          onClose={() => setCheckIn(null)}
          onChanged={setCheckIn}
        />
      )}

      {editing ? (
        <Card title={t.edit}>
          <MemberForm
            member={member}
            onCancel={() => setEditing(false)}
            onSaved={(saved) => {
              mutate({ data: saved }, { revalidate: true });
              setEditing(false);
            }}
          />
        </Card>
      ) : (
        <div className="grid items-start gap-6 lg:grid-cols-[1.3fr_1fr]">
          <div className="space-y-6">
            <Card title={t.details}>
              <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <Detail label={t.phone} value={formatPhone(member.phone)} fallback={t.notGiven} numeric />
                <Detail label={t.email} value={member.email} fallback={t.notGiven} />
                <Detail label={t.gender} value={member.gender ? t[member.gender] : null} fallback={t.notGiven} />
                <Detail label={t.dob} value={formatDate(member.date_of_birth, locale)} fallback={t.notGiven} />
                <Detail
                  label={t.emergency}
                  value={member.emergency_name ? `${member.emergency_name}${member.emergency_phone ? ` · ${formatPhone(member.emergency_phone)}` : ""}` : null}
                  fallback={t.notGiven}
                />
                <Detail label={t.notes} value={member.notes} fallback="—" />
              </dl>
              {!canEdit && <p className="mt-4 text-xs text-graphite">{t.readOnly}</p>}
            </Card>

            <MembershipsCard member={member} />
            <MemberInvoicesCard member={member} />
          </div>

          <div className="space-y-6">
            {canEdit && <StatusCard member={member} onChanged={(saved) => mutate({ data: saved }, { revalidate: false })} />}
            {canEdit && <MemberCardPanel member={member} />}
            <GroupCard member={member} canEdit={canEdit} onChanged={() => mutate()} />
            {canDeleteMemberAt(me, member.home_branch?.id) && <DeleteCard member={member} />}
          </div>
        </div>
      )}
    </div>
  );
}

function BackLink({ label }: { label: string }) {
  return (
    <Link href="/members" className="inline-flex items-center gap-1.5 text-sm font-medium text-graphite hover:text-ink">
      <ArrowLeft aria-hidden className="h-4 w-4" />
      {label}
    </Link>
  );
}

function Detail({ label, value, fallback, numeric }: { label: string; value: string | null | undefined; fallback: string; numeric?: boolean }) {
  return (
    <div>
      <dt className="text-xs font-semibold text-graphite">{label}</dt>
      <dd className={`mt-0.5 ${value ? "" : "text-graphite"} ${numeric ? "tabular" : ""}`}>{value || fallback}</dd>
    </div>
  );
}

function StatusCard({ member, onChanged }: { member: Member; onChanged: (member: Member) => void }) {
  const { dict } = useDictionary();
  const t = dict.members;
  const { busy, general, run } = useFormAction();

  function change(status: MemberStatus) {
    run(async () => {
      const { data } = await apiFetch<{ data: Member }>(`/members/${member.id}`, { method: "PATCH", json: { status } });
      onChanged(data);
    });
  }

  return (
    <Card title={t.statusLabel}>
      {general && <Alert tone="error">{general}</Alert>}
      <div role="radiogroup" aria-label={t.statusLabel} className="grid grid-cols-3 gap-1 rounded-[6px] bg-surface-2 p-1">
        {(["active", "inactive", "banned"] as const).map((status) => (
          <button
            key={status}
            type="button"
            role="radio"
            aria-checked={member.status === status}
            disabled={busy}
            onClick={() => member.status !== status && change(status)}
            className="min-h-10 cursor-pointer rounded-[5px] text-sm font-semibold text-graphite transition-colors aria-checked:bg-surface aria-checked:text-ink aria-checked:shadow-sm disabled:cursor-wait"
          >
            {t.status[status]}
          </button>
        ))}
      </div>
    </Card>
  );
}

function GroupCard({ member, canEdit, onChanged }: { member: Member; canEdit: boolean; onChanged: () => void }) {
  const { dict } = useDictionary();
  const t = dict.members;
  const group = member.group;
  const [creating, setCreating] = useState(false);
  const { busy, error, general, run } = useFormAction();

  function save(members: string[]) {
    if (!group) return;
    run(async () => {
      await apiFetch(`/member-groups/${group.id}`, { method: "PATCH", json: { members } });
      onChanged();
    });
  }

  return (
    <Card title={t.group}>
      {general && <Alert tone="error">{general}</Alert>}
      {error?.field("members") && <Alert tone="error">{error.field("members")}</Alert>}

      {group ? (
        <div className="space-y-4">
          <p className="text-sm">
            <span className="mr-2 rounded-full bg-track-soft px-2 py-0.5 text-xs font-semibold text-track-deep">{t.groupTypes[group.type]}</span>
            <span className="font-semibold">{group.name}</span>
          </p>
          <ul className="divide-y divide-line rounded-[6px] border border-line">
            {group.members.map((m) => (
              <li key={m.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <Link href={`/members/${m.id}`} className={`min-w-0 truncate hover:underline ${m.id === member.id ? "font-semibold" : ""}`}>
                  <span className="tabular text-graphite">#{m.member_no}</span> {m.full_name}
                </Link>
                <span className="flex items-center gap-2">
                  {m.id === group.primary_member_id && <span className="text-xs text-graphite">{t.primary}</span>}
                  {canEdit && (
                    <button
                      type="button"
                      aria-label={`${t.removeFromGroup} ${m.full_name}`}
                      disabled={busy}
                      onClick={() => save(group.members.filter((x) => x.id !== m.id).map((x) => x.id))}
                      className="grid h-8 w-8 cursor-pointer place-items-center rounded-[6px] text-graphite hover:bg-surface-2 hover:text-critical"
                    >
                      <X aria-hidden className="h-4 w-4" />
                    </button>
                  )}
                </span>
              </li>
            ))}
          </ul>
          {canEdit && (
            <>
              <MemberPicker exclude={group.members.map((m) => m.id)} onPick={(picked) => save([...group.members.map((m) => m.id), picked.id])} />
              <Button
                variant="danger"
                busy={busy}
                onClick={() =>
                  run(async () => {
                    await apiFetch(`/member-groups/${group.id}`, { method: "DELETE" });
                    onChanged();
                  })
                }
              >
                {t.deleteGroup}
              </Button>
            </>
          )}
        </div>
      ) : creating ? (
        <NewGroupForm member={member} onDone={() => { setCreating(false); onChanged(); }} onCancel={() => setCreating(false)} />
      ) : (
        <div className="space-y-3">
          <p className="text-sm text-graphite">{t.noGroup}</p>
          <p className="text-xs text-graphite">{t.groupHint}</p>
          {canEdit && (
            <Button variant="secondary" onClick={() => setCreating(true)}>
              {t.createGroup}
            </Button>
          )}
        </div>
      )}
    </Card>
  );
}

function NewGroupForm({ member, onDone, onCancel }: { member: Member; onDone: () => void; onCancel: () => void }) {
  const { dict } = useDictionary();
  const t = dict.members;
  const { busy, error, general, run } = useFormAction();
  const [type, setType] = useState<MemberGroup["type"]>("couple");
  const [others, setOthers] = useState<Member[]>([]);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const name = String(new FormData(event.currentTarget).get("name") ?? "");
    run(async () => {
      await apiFetch("/member-groups", {
        json: { type, name, members: [member.id, ...others.map((o) => o.id)], primary_member: member.id },
      });
      onDone();
    });
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label={t.groupType}>
          {(p) => (
            <Select {...p} value={type} onChange={(e) => setType(e.target.value as MemberGroup["type"])}>
              {(["couple", "family", "corporate"] as const).map((g) => (
                <option key={g} value={g}>
                  {t.groupTypes[g]}
                </option>
              ))}
            </Select>
          )}
        </Field>
        <Field label={t.groupName} error={error?.field("name")}>
          {(p) => <Input {...p} name="name" defaultValue={`${member.last_name} ${t.groupTypes.family.toLowerCase()}`} required />}
        </Field>
      </div>
      <ul className="space-y-1 text-sm">
        <li className="font-semibold">
          #{member.member_no} {member.full_name} · {t.primary}
        </li>
        {others.map((o) => (
          <li key={o.id} className="flex items-center justify-between">
            <span>
              #{o.member_no} {o.full_name}
            </span>
            <button type="button" onClick={() => setOthers(others.filter((x) => x.id !== o.id))} className="cursor-pointer text-xs text-graphite hover:text-critical">
              {t.removeFromGroup}
            </button>
          </li>
        ))}
      </ul>
      <MemberPicker exclude={[member.id, ...others.map((o) => o.id)]} onPick={(picked) => setOthers([...others, picked])} />
      {error?.field("members") && <p className="text-xs font-medium text-critical">{error.field("members")}</p>}
      <div className="flex gap-3">
        <Button type="submit" busy={busy} disabled={others.length === 0}>
          {t.saveGroup}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {t.cancel}
        </Button>
      </div>
    </form>
  );
}

/** Search-as-you-type picker for adding members to a group. */
function MemberPicker({ exclude, onPick }: { exclude: string[]; onPick: (member: Member) => void }) {
  const { dict } = useDictionary();
  const t = dict.members;
  const [query, setQuery] = useState("");
  const search = useDebounced(query.trim());
  const { data } = useSWR<Paginated<Member>>(search.length >= 2 ? `/members?per_page=6&search=${encodeURIComponent(search)}` : null, swrFetcher);
  const results = data?.data.filter((m) => !exclude.includes(m.id)) ?? [];

  return (
    <div className="space-y-1">
      <Input type="search" aria-label={t.addSearch} placeholder={t.addSearch} value={query} onChange={(e) => setQuery(e.target.value)} />
      {results.length > 0 && (
        <ul className="divide-y divide-line rounded-[6px] border border-line bg-surface">
          {results.map((m) => (
            <li key={m.id}>
              <button
                type="button"
                onClick={() => {
                  onPick(m);
                  setQuery("");
                }}
                className="flex w-full cursor-pointer items-center gap-3 px-3 py-2 text-left text-sm hover:bg-surface-2"
              >
                <MemberAvatar member={m} size="sm" />
                <span>
                  <span className="block font-semibold">{m.full_name}</span>
                  <span className="tabular block text-xs text-graphite">
                    #{m.member_no} · {formatPhone(m.phone)}
                  </span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function DeleteCard({ member }: { member: Member }) {
  const { dict } = useDictionary();
  const t = dict.members;
  const router = useRouter();
  const { busy, general, run } = useFormAction();
  const [confirming, setConfirming] = useState(false);

  return (
    <Card>
      {general && <Alert tone="error">{general}</Alert>}
      {confirming ? (
        <div className="space-y-3">
          <p className="text-sm">{format(t.deleteConfirm, { name: member.full_name })}</p>
          <div className="flex gap-3">
            <Button
              variant="danger"
              busy={busy}
              onClick={() =>
                run(async () => {
                  await apiFetch(`/members/${member.id}`, { method: "DELETE" });
                  router.push("/members");
                })
              }
            >
              {t.deleteYes}
            </Button>
            <Button variant="secondary" onClick={() => setConfirming(false)}>
              {t.cancel}
            </Button>
          </div>
        </div>
      ) : (
        <Button variant="danger" onClick={() => setConfirming(true)}>
          <Trash2 aria-hidden className="h-4 w-4" />
          {t.deleteMember}
        </Button>
      )}
    </Card>
  );
}
