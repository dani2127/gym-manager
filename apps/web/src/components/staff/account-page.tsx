"use client";

import { useState, type FormEvent } from "react";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input, PageHeader, Select } from "@/components/ui";
import { LOCALE_COOKIE } from "@/i18n/config";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch } from "@/lib/api";
import { useSession } from "@/lib/session";

export function AccountPage() {
  const { dict } = useDictionary();

  return (
    <>
      <PageHeader title={dict.account.title} />
      <div className="grid gap-4 lg:grid-cols-2">
        <ProfileCard />
        <TelegramCard />
        <PasswordCard />
        <PhoneCard />
      </div>
    </>
  );
}

function ProfileCard() {
  const { dict } = useDictionary();
  const { me, refresh } = useSession();
  const { busy, error, saved, general, run } = useFormAction();
  const t = dict.account;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const locale = String(form.get("locale"));

    run(async () => {
      await apiFetch("/me", { method: "PATCH", json: { name: form.get("name"), locale } });
      if (locale !== me.user.locale) {
        // The UI language follows the profile language.
        document.cookie = `${LOCALE_COOKIE}=${locale}; path=/; max-age=31536000; samesite=lax`;
        window.location.reload();
        return;
      }
      await refresh();
    });
  }

  return (
    <Card title={t.profile}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        {general && <Alert tone="error">{general}</Alert>}
        <Field label={t.name} error={error?.field("name")}>
          {(p) => <Input {...p} name="name" defaultValue={me.user.name} required />}
        </Field>
        <Field label={t.language}>
          {(p) => (
            <Select {...p} name="locale" defaultValue={me.user.locale}>
              <option value="en">English</option>
              <option value="am">አማርኛ</option>
            </Select>
          )}
        </Field>
        <div className="flex items-center gap-3">
          <Button type="submit" busy={busy}>
            {busy ? dict.common.saving : dict.common.save}
          </Button>
          {saved && <span className="text-sm text-brand">{dict.common.saved}</span>}
        </div>
      </form>
    </Card>
  );
}

function PasswordCard() {
  const { dict } = useDictionary();
  const { busy, error, saved, general, run } = useFormAction();
  const t = dict.account;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);

    run(async () => {
      await apiFetch("/me/password", { method: "PUT", json: Object.fromEntries(form) });
      formElement.reset();
    });
  }

  return (
    <Card title={t.password}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        {general && <Alert tone="error">{general}</Alert>}
        <Field label={t.currentPassword} error={error?.field("current_password")}>
          {(p) => <Input {...p} name="current_password" type="password" autoComplete="current-password" required />}
        </Field>
        <Field label={t.newPassword} error={error?.field("password")}>
          {(p) => <Input {...p} name="password" type="password" autoComplete="new-password" required />}
        </Field>
        <Field label={t.confirmPassword}>
          {(p) => <Input {...p} name="password_confirmation" type="password" autoComplete="new-password" required />}
        </Field>
        <div className="flex items-center gap-3">
          <Button type="submit" busy={busy}>
            {t.password}
          </Button>
          {saved && <span className="text-sm text-brand">{dict.common.saved}</span>}
        </div>
      </form>
    </Card>
  );
}

function TelegramCard() {
  const { dict } = useDictionary();
  const { me, refresh } = useSession();
  const { busy, error, general, run } = useFormAction();
  const [waiting, setWaiting] = useState(false);
  const t = dict.account;
  const linked = me.user.telegram_linked;
  const message = error?.field("telegram") ?? general;

  function link() {
    // Open the tab synchronously so pop-up blockers allow it, then point it at the bot.
    const tab = window.open("about:blank", "_blank");
    run(async () => {
      try {
        const { url } = await apiFetch<{ url: string }>("/me/telegram/link", { method: "POST" });
        if (tab) tab.location.href = url;
        else window.location.href = url;
        setWaiting(true);
      } catch (e) {
        tab?.close();
        throw e;
      }
    });
  }

  return (
    <Card title={t.telegram}>
      <div className="space-y-4 text-sm">
        {message && <Alert tone="error">{message}</Alert>}
        <p className={linked ? "text-brand" : "text-muted"}>{linked ? t.telegramLinked : t.telegramNotLinked}</p>
        {waiting && !linked && <Alert>{t.telegramLinkHelp}</Alert>}
        <div className="flex flex-wrap gap-2">
          {linked ? (
            <Button
              variant="danger"
              busy={busy}
              onClick={() =>
                run(async () => {
                  await apiFetch("/me/telegram", { method: "DELETE" });
                  await refresh();
                })
              }
            >
              {t.telegramUnlink}
            </Button>
          ) : (
            <>
              <Button busy={busy} onClick={link}>
                {t.telegramLink}
              </Button>
              {waiting && (
                <Button variant="secondary" onClick={() => refresh()}>
                  {t.telegramCheck}
                </Button>
              )}
            </>
          )}
        </div>
      </div>
    </Card>
  );
}

function PhoneCard() {
  const { dict } = useDictionary();
  const { me, refresh } = useSession();
  const send = useFormAction();
  const verify = useFormAction();
  const [codeSent, setCodeSent] = useState(false);
  const t = dict.account;

  function submitCode(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    verify.run(async () => {
      await apiFetch("/me/phone/verify", { json: { code: form.get("code") } });
      await refresh();
    });
  }

  return (
    <Card title={t.phone}>
      <div className="space-y-4 text-sm">
        <p>
          <span className="font-medium">{me.user.phone}</span>{" "}
          <span className={me.user.phone_verified ? "text-brand" : "text-muted"}>
            · {me.user.phone_verified ? t.phoneVerified : t.phoneNotVerified}
          </span>
        </p>
        {!me.user.phone_verified && (
          <>
            {send.error && <Alert tone="error">{send.error.field("telegram") ?? send.error.message}</Alert>}
            {verify.general && <Alert tone="error">{verify.general}</Alert>}
            {!codeSent ? (
              <Button
                variant="secondary"
                busy={send.busy}
                disabled={!me.user.telegram_linked}
                onClick={() =>
                  send.run(async () => {
                    await apiFetch("/me/phone/verification", { method: "POST" });
                    setCodeSent(true);
                  })
                }
              >
                {t.sendCode}
              </Button>
            ) : (
              <form onSubmit={submitCode} className="flex items-end gap-2" noValidate>
                <div className="flex-1">
                  <Field label={t.code} error={verify.error?.field("code")}>
                    {(p) => <Input {...p} name="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} required />}
                  </Field>
                </div>
                <Button type="submit" busy={verify.busy}>
                  {t.verify}
                </Button>
              </form>
            )}
          </>
        )}
      </div>
    </Card>
  );
}
