"use client";

import { useSyncExternalStore } from "react";

import type { Dictionary } from "@/i18n/dictionaries";

type Channel = "telegram" | "whatsapp";
type Preference = { channel: Channel; whatsappNumber: string };

const DEFAULT_PREFERENCE: Preference = { channel: "telegram", whatsappNumber: "" };

// Until member sign-in exists (M5) the choice is kept on the device; it will
// then be sent to the API's notification_preferences endpoint instead.
const STORAGE_KEY = "gym.notificationPreference";
const CHANGE_EVENT = "gym:notification-preference";
let memoryFallback: string | null = null;

function subscribe(onChange: () => void) {
  window.addEventListener("storage", onChange);
  window.addEventListener(CHANGE_EVENT, onChange);
  return () => {
    window.removeEventListener("storage", onChange);
    window.removeEventListener(CHANGE_EVENT, onChange);
  };
}

function readSnapshot(): string | null {
  try {
    return window.localStorage.getItem(STORAGE_KEY) ?? memoryFallback;
  } catch {
    return memoryFallback;
  }
}

function parse(raw: string | null): Preference {
  if (!raw) return DEFAULT_PREFERENCE;
  try {
    return { ...DEFAULT_PREFERENCE, ...JSON.parse(raw) };
  } catch {
    return DEFAULT_PREFERENCE;
  }
}

function persist(preference: Preference) {
  memoryFallback = JSON.stringify(preference);
  try {
    window.localStorage.setItem(STORAGE_KEY, memoryFallback);
  } catch {
    // Private mode or blocked storage: the in-memory copy still drives the UI.
  }
  window.dispatchEvent(new Event(CHANGE_EVENT));
}

const ETHIOPIAN_MOBILE = /^[79]\d{8}$/;
const TELEGRAM_BOT = process.env.NEXT_PUBLIC_TELEGRAM_BOT_USERNAME;

export function NotificationPreference({
  t,
}: {
  t: Dictionary["settings"]["notifications"];
}) {
  const raw = useSyncExternalStore(subscribe, readSnapshot, () => null);
  const preference = parse(raw);
  const update = (patch: Partial<Preference>) => persist({ ...preference, ...patch });

  const numberInvalid =
    preference.whatsappNumber !== "" && !ETHIOPIAN_MOBILE.test(preference.whatsappNumber);

  return (
    <section className="rounded-[6px] border border-border bg-surface p-5">
      <h2 className="text-base font-semibold">{t.title}</h2>
      <p className="mt-1 text-sm text-muted">{t.description}</p>

      <fieldset className="mt-4">
        <legend className="text-sm font-medium">{t.channelLabel}</legend>
        <div className="mt-2 grid grid-cols-2 gap-1 rounded-[6px] bg-background p-1">
          {(["telegram", "whatsapp"] as const).map((channel) => (
            <label
              key={channel}
              className="cursor-pointer rounded-lg px-3 py-2 text-center text-sm font-medium text-muted has-checked:bg-brand has-checked:text-brand-foreground has-focus-visible:outline-2 has-focus-visible:outline-brand"
            >
              <input
                type="radio"
                name="notification-channel"
                value={channel}
                checked={preference.channel === channel}
                onChange={() => update({ channel })}
                className="sr-only"
              />
              {t[channel]}
            </label>
          ))}
        </div>
      </fieldset>

      {preference.channel === "telegram" ? (
        <div className="mt-4 text-sm">
          <p className="text-muted">{t.telegramHelp}</p>
          {TELEGRAM_BOT ? (
            <a
              href={`https://t.me/${TELEGRAM_BOT}`}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-block rounded-lg bg-brand px-4 py-2 font-medium text-brand-foreground"
            >
              {t.telegramConnect}
            </a>
          ) : (
            <p className="mt-3 text-amber">{t.telegramUnavailable}</p>
          )}
        </div>
      ) : (
        <div className="mt-4 text-sm">
          <label htmlFor="whatsapp-number" className="font-medium">
            {t.whatsappNumber}
          </label>
          <div className="mt-2 flex overflow-hidden rounded-lg border border-border focus-within:border-brand">
            <span className="bg-background px-3 py-2 text-muted">+251</span>
            <input
              id="whatsapp-number"
              type="tel"
              inputMode="numeric"
              autoComplete="tel-national"
              placeholder="911234567"
              value={preference.whatsappNumber}
              onChange={(event) =>
                update({ whatsappNumber: event.target.value.replace(/\D/g, "").slice(0, 9) })
              }
              aria-invalid={numberInvalid}
              aria-describedby="whatsapp-help"
              className="w-full bg-surface px-3 py-2 outline-none"
            />
          </div>
          <p id="whatsapp-help" className={`mt-2 ${numberInvalid ? "text-critical" : "text-muted"}`}>
            {numberInvalid ? t.whatsappInvalid : t.whatsappHelp}
          </p>
        </div>
      )}

      <p className="mt-4 text-xs text-muted">{t.savedLocally}</p>
    </section>
  );
}
