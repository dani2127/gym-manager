"use client";

import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, Toggle } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { TrainerProfile } from "@/lib/types";

/** For trainers: the short profile members see in the app. */
export function TrainerProfileCard() {
  const { me } = useSession();
  const isTrainer = me.user.account_type === "owner" || me.branches.some((b) => b.role === "trainer");
  const { data } = useSWR<{ data: TrainerProfile }>(isTrainer ? "/me/trainer-profile" : null, swrFetcher);

  if (!isTrainer) return null;
  if (!data) return <div className="h-48 animate-pulse rounded-[6px] bg-surface" />;

  return <ProfileForm profile={data.data} />;
}

function ProfileForm({ profile }: { profile: TrainerProfile }) {
  const { dict } = useDictionary();
  const t = dict.training;
  const { busy, error, saved, general, run } = useFormAction();
  const [isPublic, setPublic] = useState(profile.is_public);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const f = new FormData(event.currentTarget);
    const en = String(f.get("bio_en") ?? "").trim();
    const am = String(f.get("bio_am") ?? "").trim();
    run(async () => {
      await apiFetch("/me/trainer-profile", {
        method: "PUT",
        json: {
          bio: en || am ? { en, am: am || null } : null,
          specialties: String(f.get("specialties") ?? "").split(",").map((x) => x.trim()).filter(Boolean).slice(0, 8),
          is_public: isPublic,
        },
      });
    });
  }

  return (
    <Card title={t.profile}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        <p className="-mt-2 text-sm text-graphite">{t.profileHint}</p>
        {general && <Alert tone="error">{general}</Alert>}
        <Field label={t.bioEn} error={error?.field("bio.en")}>
          {(p) => <textarea {...p} name="bio_en" rows={3} maxLength={600} defaultValue={profile.bio_translations?.en ?? ""} className="w-full rounded-[6px] border border-line-strong bg-surface p-2 text-sm" />}
        </Field>
        <Field label={t.bioAm} error={error?.field("bio.am")}>
          {(p) => <textarea {...p} name="bio_am" rows={3} maxLength={600} defaultValue={profile.bio_translations?.am ?? ""} className="w-full rounded-[6px] border border-line-strong bg-surface p-2 text-sm" />}
        </Field>
        <Field label={t.specialties} hint={t.specialtiesHint}>
          {(p) => <Input {...p} name="specialties" defaultValue={profile.specialties.join(", ")} />}
        </Field>
        <div className="rounded-[6px] border border-line bg-surface px-4">
          <Toggle label={t.public} checked={isPublic} onChange={setPublic} />
        </div>
        <div className="flex items-center gap-3">
          <Button type="submit" busy={busy}>
            {dict.common.save}
          </Button>
          {saved && <span className="text-sm text-brand">{dict.common.saved}</span>}
        </div>
      </form>
    </Card>
  );
}
