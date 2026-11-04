"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";

import { Alert, Button, Field, Input } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch } from "@/lib/api";
import { useIsClient } from "@/lib/use-is-client";

/** "lion fitness" → "lion-fitness": the suggestion for the gym ID. */
function slugify(value: string) {
  return value
    .toLowerCase()
    .normalize("NFKD")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 63);
}

export function RegisterForm() {
  const { locale, dict } = useDictionary();
  const t = dict.register;
  const isClient = useIsClient();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [slugTouched, setSlugTouched] = useState(false);
  const [name, setName] = useState(() => (typeof window === "undefined" ? "" : new URLSearchParams(window.location.search).get("name") ?? ""));
  const [slug, setSlug] = useState(() => slugify(name));

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setError(null);

    try {
      await apiFetch("/auth/register", {
        locale,
        json: {
          organization_name: name,
          organization_slug: slug,
          branch_name: form.get("branch_name"),
          name: form.get("name"),
          phone: form.get("phone"),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
          locale,
        },
      });
      try {
        window.localStorage.setItem("gym.organization", slug);
      } catch {
        // Remembering the gym ID is only a convenience.
      }
      // Full load so the console starts with the new session's data.
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- intentional hard navigation
      window.location.assign("/admin");
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, dict.common.error));
      setBusy(false);
    }
  }

  if (!isClient) return <p className="text-sm text-graphite">{dict.common.loading}</p>;

  const general = error && Object.keys(error.errors).length === 0 ? error.message : null;

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <p className="text-graphite">{t.intro}</p>
      {general && <Alert tone="error">{general}</Alert>}
      <Field label={t.gymName} error={error?.field("organization_name")}>
        {(p) => (
          <Input
            {...p}
            value={name}
            required
            autoComplete="organization"
            onChange={(e) => {
              setName(e.target.value);
              if (!slugTouched) setSlug(slugify(e.target.value));
            }}
          />
        )}
      </Field>
      <Field label={t.gymId} hint={t.gymIdHint} error={error?.field("organization_slug")}>
        {(p) => (
          <Input
            {...p}
            value={slug}
            required
            autoCapitalize="none"
            onChange={(e) => {
              setSlugTouched(true);
              setSlug(slugify(e.target.value));
            }}
          />
        )}
      </Field>
      <Field label={t.branchName} error={error?.field("branch_name")}>
        {(p) => <Input {...p} name="branch_name" placeholder={t.branchPlaceholder} required />}
      </Field>
      <div className="border-t border-line pt-4" />
      <Field label={t.yourName} error={error?.field("name")}>
        {(p) => <Input {...p} name="name" autoComplete="name" required />}
      </Field>
      <Field label={t.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" inputMode="tel" autoComplete="tel" placeholder="0911 234 567" required />}
      </Field>
      <Field label={t.password} hint={t.passwordHint} error={error?.field("password")}>
        {(p) => <Input {...p} name="password" type="password" autoComplete="new-password" required />}
      </Field>
      <Field label={t.confirmPassword}>
        {(p) => <Input {...p} name="password_confirmation" type="password" autoComplete="new-password" required />}
      </Field>
      <Button type="submit" busy={busy} className="w-full">
        {busy ? t.submitting : t.submit}
      </Button>
      <p className="text-center text-sm">
        <Link href="/login" className="font-medium text-track-deep hover:underline">
          {t.haveAccount}
        </Link>
      </p>
    </form>
  );
}
