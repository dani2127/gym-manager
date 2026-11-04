"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { useSWRConfig } from "swr";

import entrance from "@/assets/images/member-welcome.jpg";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { useIsClient } from "@/lib/use-is-client";

const GYM_KEY = "gym.member.organization";

function rememberedGym() {
  try {
    return window.localStorage.getItem(GYM_KEY) ?? process.env.NEXT_PUBLIC_ORGANIZATION ?? "";
  } catch {
    return process.env.NEXT_PUBLIC_ORGANIZATION ?? "";
  }
}
function rememberGym(slug: string) {
  try {
    window.localStorage.setItem(GYM_KEY, slug);
  } catch {
    // Not remembered; asked again next time.
  }
}

/** Member sign-in: gym ID, phone and password. Lands on the pass. */
export function MemberLogin() {
  const { dict } = useDictionary();
  const t = dict.memberApp.login;
  const router = useRouter();
  const isClient = useIsClient();
  const { mutate } = useSWRConfig();
  const { busy, error, general, run } = useFormAction();

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const organization = String(form.get("organization") ?? "").trim();
    run(async () => {
      await apiFetch("/auth/login", { json: { organization, phone: form.get("phone"), password: form.get("password") } });
      rememberGym(organization);
      // Forget the signed-out answers cached before sign-in.
      await mutate(() => true, undefined, { revalidate: false });
      router.replace("/app");
    });
  }

  return (
    <form onSubmit={submit} className="space-y-5" noValidate>
      <div className="relative -mx-5 aspect-[16/9] overflow-hidden">
        <Image src={entrance} alt={dict.member.photoAlt} fill sizes="(min-width: 448px) 448px, 100vw" placeholder="blur" loading="eager" className="object-cover object-[50%_58%]" />
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-1.5 bg-track" />
      </div>
      <div>
        <h1 className="font-bib text-4xl">{t.title}</h1>
        <p className="mt-1 text-sm text-graphite">{t.body}</p>
      </div>
      {general && <Alert tone="error">{general}</Alert>}
      {isClient && (
        <Field label={t.gym} hint={t.gymHint} error={error?.field("organization")}>
          {(p) => <Input {...p} name="organization" defaultValue={rememberedGym()} autoCapitalize="none" autoComplete="organization" required />}
        </Field>
      )}
      <Field label={t.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" inputMode="tel" autoComplete="tel" placeholder="0911 234 567" required />}
      </Field>
      <Field label={t.password} error={error?.field("password")}>
        {(p) => <Input {...p} name="password" type="password" autoComplete="current-password" required />}
      </Field>
      <Button type="submit" busy={busy} className="w-full min-h-12 text-base">
        {t.submit}
      </Button>
      <div className="space-y-2 text-center text-sm">
        <Link href="/app/setup" className="block font-semibold text-track-deep hover:underline">
          {t.firstTime}
        </Link>
        <Link href="/forgot-password" className="block text-graphite hover:text-ink">
          {t.forgot}
        </Link>
      </div>
    </form>
  );
}

/** First time: the setup code from the desk, then the member's own password. */
export function MemberSetup() {
  const { dict } = useDictionary();
  const t = dict.memberApp.setup;
  const tl = dict.memberApp.login;
  const router = useRouter();
  const isClient = useIsClient();
  const { mutate } = useSWRConfig();
  const { busy, error, general, run } = useFormAction();
  const [code, setCode] = useState("");

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const organization = String(form.get("organization") ?? "").trim();
    run(async () => {
      await apiFetch("/auth/member-setup", {
        json: {
          organization,
          phone: form.get("phone"),
          code,
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
        },
      });
      rememberGym(organization);
      // Forget the signed-out answers cached before sign-in.
      await mutate(() => true, undefined, { revalidate: false });
      router.replace("/app");
    });
  }

  return (
    <form onSubmit={submit} className="space-y-5 pt-4" noValidate>
      <div>
        <h1 className="font-bib text-4xl">{t.title}</h1>
        <p className="mt-1 text-sm text-graphite">{t.body}</p>
      </div>
      {general && <Alert tone="error">{general}</Alert>}
      {isClient && (
        <Field label={tl.gym} hint={tl.gymHint} error={error?.field("organization")}>
          {(p) => <Input {...p} name="organization" defaultValue={rememberedGym()} autoCapitalize="none" required />}
        </Field>
      )}
      <Field label={tl.phone} error={error?.field("phone")}>
        {(p) => <Input {...p} name="phone" type="tel" inputMode="tel" autoComplete="tel" placeholder="0911 234 567" required />}
      </Field>
      <Field label={t.code} error={error?.field("code")}>
        {(p) => (
          <Input
            {...p}
            name="code"
            inputMode="numeric"
            autoComplete="one-time-code"
            placeholder="1234 5678"
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/[^\d ]/g, "").slice(0, 9))}
            className="font-bib tabular text-2xl tracking-widest"
            required
          />
        )}
      </Field>
      <Field label={t.password} hint={t.passwordHint} error={error?.field("password")}>
        {(p) => <Input {...p} name="password" type="password" autoComplete="new-password" required />}
      </Field>
      <Field label={t.confirm}>{(p) => <Input {...p} name="password_confirmation" type="password" autoComplete="new-password" required />}</Field>
      <Button type="submit" busy={busy} className="w-full min-h-12 text-base">
        {t.submit}
      </Button>
      <Link href="/app/login" className="block text-center text-sm font-semibold text-track-deep hover:underline">
        {t.haveAccount}
      </Link>
    </form>
  );
}
