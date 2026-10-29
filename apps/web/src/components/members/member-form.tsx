"use client";

import { Camera } from "lucide-react";
import { useEffect, useMemo, useState, type FormEvent } from "react";

import { MemberAvatar } from "@/components/members/member-avatar";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input, Select } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { formatPhone } from "@/lib/format";
import { registrableBranches } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Member } from "@/lib/types";

/**
 * Register a member, or edit one. The photo uploads after the member is
 * saved, so a failed upload never loses the registration.
 */
export function MemberForm({ member, onSaved, onCancel }: { member?: Member; onSaved: (member: Member) => void; onCancel?: () => void }) {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const t = dict.members;
  const { busy, error, general, run } = useFormAction();
  const [photo, setPhoto] = useState<File | null>(null);
  const preview = useMemo(() => (photo ? URL.createObjectURL(photo) : null), [photo]);
  const [confirmDuplicate, setConfirmDuplicate] = useState(false);
  const branches = registrableBranches(me);
  const duplicate = error?.field("phone_duplicate");

  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview);
  }, [preview]);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const value = (name: string) => {
      const v = String(form.get(name) ?? "").trim();
      return v === "" ? null : v;
    };

    run(async () => {
      const payload = {
        first_name: value("first_name"),
        last_name: value("last_name"),
        home_branch: value("home_branch"),
        phone: value("phone"),
        gender: value("gender"),
        date_of_birth: value("date_of_birth"),
        email: value("email"),
        emergency_name: value("emergency_name"),
        emergency_phone: value("emergency_phone"),
        notes: value("notes"),
        ...(confirmDuplicate && { confirm_duplicate: true }),
      };

      const { data: saved } = await apiFetch<{ data: Member }>(member ? `/members/${member.id}` : "/members", {
        method: member ? "PATCH" : "POST",
        json: payload,
      });

      let result = saved;
      if (photo) {
        const body = new FormData();
        body.append("photo", photo);
        result = (await apiFetch<{ data: Member }>(`/members/${saved.id}/photo`, { method: "POST", body })).data;
      }
      onSaved(result);
    });
  }

  return (
    <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[12rem_1fr]" noValidate>
      {/* Photo */}
      <div className="space-y-3">
        <p className="text-sm font-semibold">{t.photo}</p>
        {preview ? (
          // eslint-disable-next-line @next/next/no-img-element -- local preview blob
          <img src={preview} alt="" className="h-40 w-40 rounded-[6px] object-cover" />
        ) : member ? (
          <div className="[&>*]:h-40 [&>*]:w-40 [&>*]:text-5xl">
            <MemberAvatar member={member} size="lg" />
          </div>
        ) : (
          <div className="grid h-40 w-40 place-items-center rounded-[6px] border-2 border-dashed border-line-strong bg-surface text-graphite">
            <Camera aria-hidden className="h-8 w-8" />
          </div>
        )}
        <label className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-[6px] border border-line-strong bg-surface px-3 text-sm font-semibold hover:border-ink">
          <Camera aria-hidden className="h-4 w-4" />
          {member?.photo_url || preview ? t.photoChange : t.photoTake}
          <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(e) => setPhoto(e.target.files?.[0] ?? null)} />
        </label>
        {error?.field("photo") ? <p className="text-xs font-medium text-critical">{error.field("photo")}</p> : <p className="text-xs text-graphite">{t.photoHint}</p>}
      </div>

      <div className="space-y-6">
        {general && <Alert tone="error">{general}</Alert>}

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t.firstName} error={error?.field("first_name")}>
            {(p) => <Input {...p} name="first_name" defaultValue={member?.first_name} required autoComplete="off" />}
          </Field>
          <Field label={t.lastName} error={error?.field("last_name")}>
            {(p) => <Input {...p} name="last_name" defaultValue={member?.last_name} required autoComplete="off" />}
          </Field>
          <Field label={t.phone} hint={t.phoneHint} error={error?.field("phone")}>
            {(p) => <Input {...p} name="phone" type="tel" inputMode="tel" placeholder="0911 234 567" defaultValue={formatPhone(member?.phone)} onChange={() => setConfirmDuplicate(false)} />}
          </Field>
          <Field label={t.homeBranch} error={error?.field("home_branch")}>
            {(p) => (
              <Select {...p} name="home_branch" defaultValue={member?.home_branch?.id ?? (branches.some((b) => b.id === branch?.id) ? branch?.id : branches[0]?.id)}>
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </Select>
            )}
          </Field>
          <Field label={t.gender} error={error?.field("gender")}>
            {(p) => (
              <Select {...p} name="gender" defaultValue={member?.gender ?? ""}>
                <option value="">{t.genderUnset}</option>
                <option value="female">{t.female}</option>
                <option value="male">{t.male}</option>
              </Select>
            )}
          </Field>
          <Field label={t.dob} error={error?.field("date_of_birth")}>
            {(p) => <Input {...p} name="date_of_birth" type="date" defaultValue={member?.date_of_birth ?? ""} max={new Date().toISOString().slice(0, 10)} />}
          </Field>
          <Field label={t.email} error={error?.field("email")}>
            {(p) => <Input {...p} name="email" type="email" defaultValue={member?.email ?? ""} />}
          </Field>
        </div>

        {duplicate && (
          <div className="space-y-3 rounded-[6px] border border-amber/40 bg-amber-soft p-4 text-sm">
            <p className="font-medium text-amber">{duplicate}</p>
            <label className="flex items-center gap-2 font-semibold">
              <input type="checkbox" checked={confirmDuplicate} onChange={(e) => setConfirmDuplicate(e.target.checked)} className="h-4 w-4 accent-[var(--track)]" />
              {t.confirmDuplicate}
            </label>
          </div>
        )}

        <fieldset className="grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
          <legend className="mb-1 text-sm font-semibold">{t.emergency}</legend>
          <Field label={t.emergencyName} error={error?.field("emergency_name")}>
            {(p) => <Input {...p} name="emergency_name" defaultValue={member?.emergency_name ?? ""} />}
          </Field>
          <Field label={t.emergencyPhone} error={error?.field("emergency_phone")}>
            {(p) => <Input {...p} name="emergency_phone" type="tel" placeholder="0911 234 567" defaultValue={formatPhone(member?.emergency_phone)} />}
          </Field>
        </fieldset>

        <Field label={t.notes} hint={t.notesHint} error={error?.field("notes")}>
          {(p) => (
            <textarea
              {...p}
              name="notes"
              rows={3}
              defaultValue={member?.notes ?? ""}
              className="w-full rounded-[6px] border border-line-strong bg-surface px-3 py-2 text-sm outline-none focus:border-ink"
            />
          )}
        </Field>

        <div className="flex flex-wrap gap-3">
          <Button type="submit" busy={busy} disabled={Boolean(duplicate) && !confirmDuplicate}>
            {busy ? t.saving : member ? t.saveChanges : t.save}
          </Button>
          {onCancel && (
            <Button type="button" variant="secondary" onClick={onCancel}>
              {t.cancel}
            </Button>
          )}
        </div>
      </div>
    </form>
  );
}
