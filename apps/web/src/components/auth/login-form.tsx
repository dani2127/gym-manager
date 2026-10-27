"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";

import { Alert, Button, Field, Input } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch } from "@/lib/api";
import { homeFor } from "@/lib/roles";
import type { Me } from "@/lib/types";
import { useIsClient } from "@/lib/use-is-client";

const ORG_KEY = "gym.organization";
const DEFAULT_ORG = process.env.NEXT_PUBLIC_ORGANIZATION ?? "";

export function rememberedOrganization(): string {
  try {
    return window.localStorage.getItem(ORG_KEY) ?? DEFAULT_ORG;
  } catch {
    return DEFAULT_ORG;
  }
}

export function LoginForm() {
  const { locale, dict } = useDictionary();
  const t = dict.auth;
  const isClient = useIsClient();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const organization = String(form.get("organization")).trim().toLowerCase();

    try {
      await apiFetch("/auth/login", {
        locale,
        json: { organization, phone: form.get("phone"), password: form.get("password") },
      });
      try {
        window.localStorage.setItem(ORG_KEY, organization);
      } catch {
        // Not remembering the gym ID is harmless.
      }

      const me = await apiFetch<Me>("/me", { locale });
      const next = new URLSearchParams(window.location.search).get("next");
      // Only same-site relative paths, never "//evil.example".
      window.location.assign(next?.startsWith("/") && !next.startsWith("//") ? next : homeFor(me));
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, dict.common.error));
      setBusy(false);
    }
  }

  // Defaults come from localStorage, so render the form only in the browser.
  if (!isClient) return <p className="text-sm text-muted">{dict.common.loading}</p>;

  const general = error && !error.field("phone") && !error.field("organization") ? error.message : null;

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      {general && <Alert tone="error">{general}</Alert>}
      <Field label={t.organization} hint={t.organizationHint} error={error?.field("organization")}>
        {(p) => (
          <Input {...p} name="organization" required autoCapitalize="none" autoCorrect="off" defaultValue={rememberedOrganization()} />
        )}
      </Field>
      <Field label={t.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" inputMode="tel" autoComplete="tel" placeholder="0911 234 567" required />}
      </Field>
      <Field label={t.password} error={error?.field("password")}>
        {(p) => <Input {...p} name="password" type="password" autoComplete="current-password" required />}
      </Field>
      <Button type="submit" busy={busy} className="w-full">
        {busy ? t.signingIn : t.signIn}
      </Button>
      <p className="text-center text-sm">
        <Link href="/forgot-password" className="text-brand hover:underline">
          {t.forgot}
        </Link>
      </p>
    </form>
  );
}
