"use client";

import { Download } from "lucide-react";
import { useEffect, useState } from "react";

import { useDictionary } from "@/i18n/dictionary-context";
import { useIsClient } from "@/lib/use-is-client";

type InstallEvent = Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: string }> };

/** Offers to install the app: Android/desktop get a button, iPhone gets the Share tip. */
export function InstallPrompt() {
  const { dict } = useDictionary();
  const t = dict.memberApp.install;
  const isClient = useIsClient();
  const [event, setEvent] = useState<InstallEvent | null>(null);
  const [installed, setInstalled] = useState(false);

  useEffect(() => {
    const onPrompt = (e: Event) => {
      e.preventDefault();
      setEvent(e as InstallEvent);
    };
    const onInstalled = () => setInstalled(true);
    window.addEventListener("beforeinstallprompt", onPrompt);
    window.addEventListener("appinstalled", onInstalled);
    return () => {
      window.removeEventListener("beforeinstallprompt", onPrompt);
      window.removeEventListener("appinstalled", onInstalled);
    };
  }, []);

  if (!isClient || installed || window.matchMedia("(display-mode: standalone)").matches) return null;
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  if (!event && !ios) return null;

  return (
    <section className="flex items-center gap-4 rounded-[10px] border border-line bg-surface p-4">
      <span className="grid h-11 w-11 shrink-0 place-items-center rounded-[8px] bg-track-soft text-track-deep">
        <Download aria-hidden className="h-5 w-5" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{t.title}</p>
        <p className="text-sm text-graphite">{ios && !event ? t.ios : t.body}</p>
      </div>
      {event && (
        <button
          type="button"
          onClick={async () => {
            await event.prompt();
            await event.userChoice;
            setEvent(null);
          }}
          className="min-h-10 shrink-0 cursor-pointer rounded-[6px] bg-track px-4 text-sm font-semibold text-white hover:bg-track-deep"
        >
          {t.button}
        </button>
      )}
    </section>
  );
}
