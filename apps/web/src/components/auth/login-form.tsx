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
  // Set when the password was right but two-step login is on.
  const [challenge, setChallenge] = useState<string | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const organization = String(form.get("organization")).trim().toLowerCase();

    try {
      const res = await apiFetch<{ two_factor?: boolean; challenge?: string }>("/auth/login", {
        locale,
        json: { organization, phone: form.get("phone"), password: form.get("password") },
      });
      try {
        window.localStorage.setItem(ORG_KEY, organization);
      } catch {
        // Not remembering the gym ID is harmless.
      }

      if (res?.two_factor && res.challenge) {
        setChallenge(res.challenge);
        setBusy(false);
        return;
      }
      await goHome();
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, dict.common.error));
      setBusy(false);
    }
  }

  async function goHome() {
    const me = await apiFetch<Me>("/me", { locale });
    const next = new URLSearchParams(window.location.search).get("next");
    // Only same-site relative paths, never "//evil.example".
    window.location.assign(next?.startsWith("/") && !next.startsWith("//") ? next : homeFor(me));
  }

  if (challenge) return <TwoFactorStep challenge={challenge} onBack={() => setChallenge(null)} onDone={goHome} />;

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

/** Second step of login: the authenticator code, or a recovery code. */
function TwoFactorStep({ challenge, onBack, onDone }: { challenge: string; onBack: () => void; onDone: () => Promise<void> }) {
  const { locale, dict } = useDictionary();
  const t = dict.security;
  const [recovery, setRecovery] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    const value = String(new FormData(event.currentTarget).get("code") ?? "").trim();
    try {
      await apiFetch("/auth/two-factor-challenge", { locale, json: recovery ? { challenge, recovery_code: value } : { challenge, code: value } });
      await onDone();
    } catch (e) {
      const err = e instanceof ApiError ? e : new ApiError(0, dict.common.error);
      // An expired challenge means starting again from the password.
      if (err.field("challenge")) {
        onBack();
        return;
      }
      setError(err);
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <div>
        <h2 className="text-lg font-semibold">{t.loginCodeTitle}</h2>
        <p className="mt-1 text-sm text-muted">{t.loginCodeBody}</p>
      </div>
      {error && !error.field("code") && <Alert tone="error">{error.message}</Alert>}
      <Field label={recovery ? t.recoveryCode : t.code} error={error?.field("code")}>
        {(p) =>
          recovery ? (
            <Input {...p} key="recovery" name="code" autoCapitalize="none" autoCorrect="off" className="font-mono" autoFocus required />
          ) : (
            <Input {...p} key="code" name="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} className="tabular font-mono text-lg tracking-widest" autoFocus required />
          )
        }
      </Field>
      <Button type="submit" busy={busy} className="w-full">
        {t.verify}
      </Button>
      <div className="flex justify-between text-sm">
        <button type="button" onClick={onBack} className="cursor-pointer text-muted hover:underline">
          {t.back}
        </button>
        <button type="button" onClick={() => { setRecovery((v) => !v); setError(null); }} className="cursor-pointer text-brand hover:underline">
          {recovery ? t.useApp : t.useRecovery}
        </button>
      </div>
    </form>
  );
}
