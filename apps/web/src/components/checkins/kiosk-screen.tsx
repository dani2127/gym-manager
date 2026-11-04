"use client";

import { CheckCircle2, CircleAlert, XCircle } from "lucide-react";
import { useCallback, useEffect, useState, type FormEvent } from "react";

import { Logo } from "@/components/brand/logo";
import { QrScanner } from "@/components/checkins/qr-scanner";
import { LanguageSwitcher } from "@/components/language-switcher";
import type { Locale } from "@/i18n/config";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { ApiError, apiFetch, setApiLocale } from "@/lib/api";
import { useIsClient } from "@/lib/use-is-client";
import type { CheckIn } from "@/lib/types";

const TOKEN_KEY = "gym.kiosk.token";
const RESULT_SECONDS = 4;

type KioskInfo = { kiosk: { id: string; name: string }; branch: { id: string; name: string }; organization: { name: string } };
type KioskResult = { result: CheckIn["result"]; reason: string | null; first_name: string | null; member_no: string | null; membership: CheckIn["membership"] };

function readToken() {
  try {
    return window.localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}
function writeToken(token: string | null) {
  try {
    if (token) window.localStorage.setItem(TOKEN_KEY, token);
    else window.localStorage.removeItem(TOKEN_KEY);
  } catch {
    // Private mode: the tablet re-pairs after a reload.
  }
}

/** The entrance tablet: pair once with a code, then members scan themselves in. */
export function KioskScreen({ locale }: { locale: Locale }) {
  const { dict } = useDictionary();
  const isClient = useIsClient();
  // The stored device token (browser only), unless pairing or a sign-out changed it.
  const [changedToken, setToken] = useState<string | null | undefined>(undefined);
  const token = changedToken !== undefined ? changedToken : isClient ? readToken() : undefined;
  const [info, setInfo] = useState<KioskInfo | null>(null);
  const [lost, setLost] = useState(false);

  useEffect(() => setApiLocale(locale), [locale]);

  useEffect(() => {
    if (!token) return;
    apiFetch<{ data: KioskInfo }>("/kiosk", { headers: { "X-Kiosk-Token": token } })
      .then((res) => setInfo(res.data))
      .catch((error) => {
        if (error instanceof ApiError && error.status === 401) {
          writeToken(null);
          setToken(null);
          setLost(true);
        }
      });
  }, [token]);

  // Keep the screen on while the kiosk is running.
  useEffect(() => {
    if (!info) return;
    let lock: { release: () => Promise<void> } | null = null;
    const nav = navigator as Navigator & { wakeLock?: { request: (type: "screen") => Promise<{ release: () => Promise<void> }> } };
    nav.wakeLock
      ?.request("screen")
      .then((l) => (lock = l))
      .catch(() => {});
    return () => void lock?.release();
  }, [info]);

  return (
    <div className="flex min-h-dvh flex-col bg-asphalt text-asphalt-ink">
      <header className="flex items-center justify-between px-8 py-5">
        <span className="flex items-center gap-4">
          <Logo name={info?.organization.name ?? dict.meta.title} tone="lane" />
          {info && <span className="text-sm text-asphalt-muted">{info.branch.name}</span>}
        </span>
        <span className="text-ink">
          <LanguageSwitcher locale={locale} label={dict.common.language} />
        </span>
      </header>
      <main className="flex flex-1 items-center justify-center px-6 pb-10">
        {token === undefined ? null : token && info ? (
          <KioskScanner token={token} />
        ) : (
          <PairForm
            lost={lost}
            onPaired={(newToken, newInfo) => {
              writeToken(newToken);
              setLost(false);
              setInfo(newInfo);
              setToken(newToken);
            }}
          />
        )}
      </main>
      <div className="h-2 bg-track" aria-hidden />
    </div>
  );
}

function PairForm({ lost, onPaired }: { lost: boolean; onPaired: (token: string, info: KioskInfo) => void }) {
  const { dict } = useDictionary();
  const t = dict.checkins.kiosk;
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const code = String(new FormData(event.currentTarget).get("code") ?? "");
    setBusy(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: KioskInfo & { token: string } }>("/kiosk/pair", { json: { code } });
      onPaired(data.token, data);
    } catch (e) {
      setError(e instanceof ApiError ? (e.field("code") ?? e.message) : dict.common.error);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="w-full max-w-md space-y-5 text-center" noValidate>
      <h1 className="font-bib text-5xl">{t.pairTitle}</h1>
      <p className="text-asphalt-muted">{lost ? t.disconnected : t.pairBody}</p>
      <label className="block">
        <span className="sr-only">{t.code}</span>
        <input
          name="code"
          autoFocus
          autoComplete="off"
          autoCapitalize="characters"
          placeholder="XXXX-XXXX"
          className="font-bib tabular h-20 w-full rounded-[10px] border-2 border-white/20 bg-asphalt-2 text-center text-4xl tracking-widest text-white uppercase outline-none focus:border-white/60"
        />
      </label>
      {error && <p className="font-medium text-[#f0705f]">{error}</p>}
      <button type="submit" disabled={busy} className="h-14 w-full cursor-pointer rounded-[10px] bg-track text-lg font-semibold text-white hover:bg-track-deep disabled:opacity-60">
        {t.connect}
      </button>
    </form>
  );
}

function KioskScanner({ token }: { token: string }) {
  const { dict } = useDictionary();
  const t = dict.checkins.kiosk;
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<KioskResult | null>(null);

  useEffect(() => {
    if (!result) return;
    const timer = setTimeout(() => setResult(null), RESULT_SECONDS * 1000);
    return () => clearTimeout(timer);
  }, [result]);

  const scan = useCallback(
    async (code: string) => {
      if (busy) return;
      setBusy(true);
      try {
        const { data } = await apiFetch<{ data: KioskResult }>("/kiosk/scan", { json: { code }, headers: { "X-Kiosk-Token": token } });
        setResult(data);
      } catch {
        setResult({ result: "denied", reason: "invalid_code", first_name: null, member_no: null, membership: null });
      } finally {
        setBusy(false);
      }
    },
    [busy, token],
  );

  if (result) {
    const entered = result.result === "allowed" || result.result === "overridden";
    const inside = result.result === "already_inside";
    const Icon = entered ? CheckCircle2 : inside ? CircleAlert : XCircle;
    const m = result.membership;
    const left = m
      ? [m.days_left !== null ? (m.days_left === 1 ? dict.checkins.popup.lastDay : format(dict.checkins.popup.daysLeft, { n: String(m.days_left) })) : null, m.visits_total !== null ? format(dict.checkins.popup.visitsLeft, { n: String(m.visits_remaining ?? 0), total: String(m.visits_total) }) : null]
          .filter(Boolean)
          .join(" · ")
      : "";
    const name = result.first_name ?? "";

    return (
      <div role="status" className={`w-full max-w-3xl rounded-[16px] p-12 text-center text-white ${entered ? "bg-infield" : inside ? "bg-[#8a5a12]" : "bg-critical"}`}>
        <Icon aria-hidden className="mx-auto h-24 w-24" />
        <p className="font-bib mt-6 text-6xl leading-tight">
          {entered ? format(t.welcome, { name }) : inside ? format(t.alreadyIn, { name }) : t.seeDesk}
        </p>
        {entered && left && <p className="mt-4 text-2xl opacity-90">{left}</p>}
        {!entered && !inside && (
          <p className="mt-4 text-xl opacity-90">
            {result.reason === "invalid_code" ? t.notRecognised : dict.checkins.reasons[result.reason as keyof typeof dict.checkins.reasons] ?? ""}
          </p>
        )}
        {result.member_no && <p className="font-bib tabular mt-6 text-3xl opacity-70">#{result.member_no}</p>}
      </div>
    );
  }

  return (
    <div className="w-full max-w-3xl space-y-6 text-center">
      <div>
        <h1 className="font-bib text-6xl">{t.scanTitle}</h1>
        <p className="mt-3 text-xl text-asphalt-muted">{t.scanBody}</p>
      </div>
      <QrScanner onCode={scan} busy={busy} variant="kiosk" />
    </div>
  );
}
