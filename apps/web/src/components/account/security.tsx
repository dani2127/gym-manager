"use client";

import { KeyRound, Laptop, ShieldCheck, Smartphone } from "lucide-react";
import { useState, type FormEvent } from "react";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card, Field, Input } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import { useSession } from "@/lib/session";

type TwoFactorState = { enabled: boolean; pending: boolean; required: boolean; recovery_codes_left: number | null };
type Device = { id: string; kind: "browser" | "app"; device: string; ip: string | null; last_active: string; current: boolean };

/** Asks for the password before a sensitive change, then runs it. */
function PasswordStep({ action, onSubmit, onCancel, busy, error }: { action: string; onSubmit: (password: string) => void; onCancel: () => void; busy: boolean; error?: string | null }) {
  const { dict } = useDictionary();
  const t = dict.security;

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit(String(new FormData(event.currentTarget).get("current_password") ?? ""));
  }

  return (
    <form onSubmit={submit} className="space-y-3" noValidate>
      <Field label={t.confirmPasswordToContinue} error={error ?? undefined}>
        {(p) => <Input {...p} name="current_password" type="password" autoComplete="current-password" autoFocus required />}
      </Field>
      <div className="flex flex-wrap gap-3">
        <Button type="submit" busy={busy}>
          {action}
        </Button>
        <Button type="button" variant="secondary" onClick={onCancel}>
          {dict.common.cancel}
        </Button>
      </div>
    </form>
  );
}

function RecoveryCodes({ codes, onDone }: { codes: string[]; onDone: () => void }) {
  const { dict } = useDictionary();
  const t = dict.security;
  const [copied, setCopied] = useState(false);

  return (
    <div className="space-y-3">
      <Alert tone="success">
        <span className="block font-semibold">{t.recoveryTitle}</span>
        <span className="mt-1 block">{t.recoveryBody}</span>
      </Alert>
      <ul className="grid grid-cols-2 gap-2 rounded-[6px] border border-line bg-surface-2 p-4 font-mono text-sm">
        {codes.map((c) => (
          <li key={c}>{c}</li>
        ))}
      </ul>
      <div className="flex flex-wrap gap-3">
        <Button
          variant="secondary"
          onClick={() => {
            void navigator.clipboard?.writeText(codes.join("\n")).then(() => setCopied(true));
          }}
        >
          {copied ? t.copied : t.copy}
        </Button>
        <Button onClick={onDone}>{t.done}</Button>
      </div>
    </div>
  );
}

/** Two-step login: off → password → scan QR → first code → recovery codes → on. */
export function TwoFactorCard({ onEnabled }: { onEnabled?: () => void }) {
  const { dict } = useDictionary();
  const { refresh } = useSession();
  const t = dict.security;
  const { data, mutate } = useSWR<{ data: TwoFactorState }>("/me/two-factor", swrFetcher);
  const { busy, error, general, run } = useFormAction();
  const [step, setStep] = useState<"idle" | "password" | "scan" | "codes" | "disable" | "regenerate">("idle");
  const [setup, setSetup] = useState<{ qr_svg: string; secret: string } | null>(null);
  const [codes, setCodes] = useState<string[]>([]);
  const state = data?.data;

  const start = (password: string) =>
    run(async () => {
      const res = await apiFetch<{ data: { qr_svg: string; secret: string } }>("/me/two-factor", { json: { current_password: password } });
      setSetup(res.data);
      setStep("scan");
    });

  function confirm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const code = String(new FormData(event.currentTarget).get("code") ?? "");
    run(async () => {
      const res = await apiFetch<{ data: { recovery_codes: string[] } }>("/me/two-factor/confirm", { json: { code } });
      setCodes(res.data.recovery_codes);
      setStep("codes");
      await mutate();
    });
  }

  const disable = (password: string) =>
    run(async () => {
      await apiFetch("/me/two-factor", { method: "DELETE", json: { current_password: password } });
      setStep("idle");
      await Promise.all([mutate(), refresh()]);
    });

  const regenerate = (password: string) =>
    run(async () => {
      const res = await apiFetch<{ data: { recovery_codes: string[] } }>("/me/two-factor/recovery-codes", { json: { current_password: password } });
      setCodes(res.data.recovery_codes);
      setStep("codes");
      await mutate();
    });

  const finish = async () => {
    setStep("idle");
    setCodes([]);
    setSetup(null);
    await refresh();
    onEnabled?.();
  };

  return (
    <Card title={t.twoFactor}>
      <div className="space-y-4">
        {general && step !== "scan" && <Alert tone="error">{general}</Alert>}
        {!state ? (
          <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
        ) : step === "codes" ? (
          <RecoveryCodes codes={codes} onDone={finish} />
        ) : step === "scan" && setup ? (
          <form onSubmit={confirm} className="space-y-4" noValidate>
            <p className="text-sm font-semibold">{t.scan}</p>
            {/* Fortify renders the QR code as SVG markup from our own API. */}
            <div className="inline-block rounded-[6px] border border-line bg-white p-3" dangerouslySetInnerHTML={{ __html: setup.qr_svg }} />
            <p className="text-sm text-graphite">
              {t.manual} <code className="break-all rounded bg-surface-2 px-1.5 py-0.5 font-mono text-ink">{setup.secret}</code>
            </p>
            <p className="text-sm font-semibold">{t.enterCode}</p>
            <Field label={t.code} error={error?.field("code") ?? (general || undefined)}>
              {(p) => <Input {...p} name="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} className="tabular w-40 font-mono text-lg tracking-widest" autoFocus required />}
            </Field>
            <div className="flex flex-wrap gap-3">
              <Button type="submit" busy={busy}>
                {t.confirm}
              </Button>
              <Button type="button" variant="secondary" onClick={() => setStep("idle")}>
                {dict.common.cancel}
              </Button>
            </div>
          </form>
        ) : (
          <>
            <div className="flex items-start gap-3">
              <ShieldCheck aria-hidden className={`mt-0.5 h-5 w-5 shrink-0 ${state.enabled ? "text-infield" : "text-graphite"}`} />
              <div className="text-sm">
                <p className="font-semibold">{state.enabled ? t.twoFactorOn : t.twoFactorOff}</p>
                {!state.enabled && <p className="mt-1 text-graphite">{t.twoFactorWhy}</p>}
                {state.enabled && state.recovery_codes_left !== null && <p className="mt-1 text-graphite">{format(t.codesLeft, { n: String(state.recovery_codes_left) })}</p>}
                {state.required && <p className="mt-1 font-medium text-amber">{t.requiredNote}</p>}
              </div>
            </div>
            {step === "password" && <PasswordStep action={t.continue} onSubmit={start} onCancel={() => setStep("idle")} busy={busy} error={error?.field("current_password")} />}
            {step === "disable" && <PasswordStep action={t.turnOff} onSubmit={disable} onCancel={() => setStep("idle")} busy={busy} error={error?.field("current_password")} />}
            {step === "regenerate" && <PasswordStep action={t.newCodes} onSubmit={regenerate} onCancel={() => setStep("idle")} busy={busy} error={error?.field("current_password")} />}
            {step === "idle" &&
              (state.enabled ? (
                <div className="flex flex-wrap gap-3">
                  <Button variant="secondary" onClick={() => setStep("regenerate")}>
                    <KeyRound aria-hidden className="h-4 w-4" />
                    {t.newCodes}
                  </Button>
                  {!state.required && (
                    <Button variant="secondary" onClick={() => setStep("disable")}>
                      {t.turnOff}
                    </Button>
                  )}
                </div>
              ) : (
                <Button onClick={() => setStep("password")}>{t.turnOn}</Button>
              ))}
          </>
        )}
      </div>
    </Card>
  );
}

export function DevicesCard() {
  const { locale, dict } = useDictionary();
  const t = dict.security;
  const { data, mutate } = useSWR<{ data: Device[] }>("/me/sessions", swrFetcher);
  const { busy, error, general, run } = useFormAction();
  const [asking, setAsking] = useState(false);
  const [done, setDone] = useState<number | null>(null);
  const devices = data?.data ?? [];
  const others = devices.filter((d) => !d.current).length;

  const signOut = (password: string) =>
    run(async () => {
      const res = await apiFetch<{ data: { signed_out: number } }>("/me/sessions/sign-out-others", { json: { current_password: password } });
      setDone(res.data.signed_out);
      setAsking(false);
      await mutate();
    });

  return (
    <Card title={t.devices}>
      <p className="-mt-2 mb-3 text-sm text-graphite">{t.devicesHint}</p>
      {general && <Alert tone="error">{general}</Alert>}
      {done !== null && <Alert tone="success">{done === 1 ? t.signedOutOne : format(t.signedOutOthers, { n: String(done) })}</Alert>}
      {!data ? (
        <div className="h-16 animate-pulse rounded-[6px] bg-surface-2" />
      ) : (
        <ul className="divide-y divide-line">
          {devices.map((d) => {
            const Icon = d.kind === "app" ? Smartphone : /Android|iPhone/.test(d.device) ? Smartphone : Laptop;
            return (
              <li key={d.id} className="flex items-center gap-3 py-3">
                <Icon aria-hidden className="h-5 w-5 shrink-0 text-graphite" />
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">
                    {d.kind === "app" ? `${t.app} · ${d.device}` : d.device}
                    {d.current && <span className="ml-2 rounded-full bg-infield-soft px-2 py-0.5 text-xs font-semibold text-infield">{t.thisDevice}</span>}
                  </p>
                  <p className="text-xs text-graphite">{[format(t.lastActive, { time: formatDateTime(d.last_active, locale) }), d.ip].filter(Boolean).join(" · ")}</p>
                </div>
              </li>
            );
          })}
        </ul>
      )}
      <div className="mt-3">
        {asking ? (
          <PasswordStep action={t.signOutOthers} onSubmit={signOut} onCancel={() => setAsking(false)} busy={busy} error={error?.field("current_password")} />
        ) : others > 0 ? (
          <Button variant="secondary" onClick={() => setAsking(true)}>
            {t.signOutOthers}
          </Button>
        ) : (
          data && <p className="text-sm text-graphite">{t.noOthers}</p>
        )}
      </div>
    </Card>
  );
}
