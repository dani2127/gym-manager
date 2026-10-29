"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";

import { rememberedOrganization } from "@/components/auth/login-form";
import { Alert, Button, Field, Input } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch } from "@/lib/api";
import { useIsClient } from "@/lib/use-is-client";

type Step = { name: "request" } | { name: "reset"; organization: string; phone: string; notice: string } | { name: "done" };

/** Two steps: send a code to Telegram, then set a new password with it. */
export function ForgotPasswordForm() {
  const { locale, dict } = useDictionary();
  const t = dict.auth;
  const isClient = useIsClient();
  const [step, setStep] = useState<Step>({ name: "request" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function run(action: () => Promise<void>) {
    setBusy(true);
    setError(null);
    try {
      await action();
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, dict.common.error));
    } finally {
      setBusy(false);
    }
  }

  function requestCode(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const organization = String(form.get("organization")).trim().toLowerCase();
    const phone = String(form.get("phone"));

    run(async () => {
      const { message } = await apiFetch<{ message: string }>("/auth/password/forgot", {
        locale,
        json: { organization, phone },
      });
      setStep({ name: "reset", organization, phone, notice: message });
    });
  }

  function reset(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (step.name !== "reset") return;
    const form = new FormData(event.currentTarget);

    run(async () => {
      await apiFetch("/auth/password/reset", {
        locale,
        json: {
          organization: step.organization,
          phone: step.phone,
          code: form.get("code"),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
        },
      });
      setStep({ name: "done" });
    });
  }

  if (!isClient) return <p className="text-sm text-muted">{dict.common.loading}</p>;

  if (step.name === "done") {
    return (
      <div className="space-y-4">
        <Alert tone="success">{t.resetDone}</Alert>
        <Link href="/login" className="block text-center text-sm text-brand hover:underline">
          {t.backToLogin}
        </Link>
      </div>
    );
  }

  const general = error && Object.keys(error.errors).length === 0 ? error.message : null;

  return step.name === "request" ? (
    <form onSubmit={requestCode} className="space-y-4" noValidate>
      <p className="text-sm text-muted">{t.forgotIntro}</p>
      {general && <Alert tone="error">{general}</Alert>}
      <Field label={t.organization} error={error?.field("organization")}>
        {(p) => <Input {...p} name="organization" required autoCapitalize="none" defaultValue={rememberedOrganization()} />}
      </Field>
      <Field label={t.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" inputMode="tel" placeholder="0911 234 567" required />}
      </Field>
      <Button type="submit" busy={busy} className="w-full">
        {t.sendCode}
      </Button>
      <BackLink label={t.backToLogin} />
    </form>
  ) : (
    <form onSubmit={reset} className="space-y-4" noValidate>
      <Alert>{step.notice}</Alert>
      {general && <Alert tone="error">{general}</Alert>}
      <Field label={t.code} error={error?.field("code")}>
        {(p) => <Input {...p} name="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} required />}
      </Field>
      <Field label={t.newPassword} error={error?.field("password")}>
        {(p) => <Input {...p} name="password" type="password" autoComplete="new-password" required />}
      </Field>
      <Field label={t.confirmPassword}>
        {(p) => <Input {...p} name="password_confirmation" type="password" autoComplete="new-password" required />}
      </Field>
      <Button type="submit" busy={busy} className="w-full">
        {t.resetPassword}
      </Button>
      <BackLink label={t.backToLogin} />
    </form>
  );
}

function BackLink({ label }: { label: string }) {
  return (
    <Link href="/login" className="block text-center text-sm text-brand hover:underline">
      {label}
    </Link>
  );
}
