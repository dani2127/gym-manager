"use client";

import { WifiOff } from "lucide-react";
import QRCode from "qrcode";
import { useEffect, useState } from "react";

import { format, useDictionary } from "@/i18n/dictionary-context";
import { currentWindow, passCode, type StoredPass } from "@/lib/member-pass";

/** The member's bib with a door QR code that changes every 30 seconds. */
export function PassCard({ pass, offline }: { pass: StoredPass; offline: boolean }) {
  const { dict } = useDictionary();
  const t = dict.memberApp.pass;
  const [svg, setSvg] = useState("");
  const [left, setLeft] = useState(pass.window_seconds);

  useEffect(() => {
    let cancelled = false;
    let shown = -1;
    const tick = async () => {
      const { window, secondsLeft } = currentWindow(pass);
      setLeft(secondsLeft);
      if (window === shown) return;
      shown = window;
      const code = await passCode(pass, window);
      const next = await QRCode.toString(code, { type: "svg", margin: 0, errorCorrectionLevel: "M", color: { dark: "#141514", light: "#ffffff" } });
      if (!cancelled) setSvg(next);
    };
    void tick();
    const timer = setInterval(tick, 1000);
    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [pass]);

  const progress = left / pass.window_seconds;

  return (
    <figure className="overflow-hidden rounded-[12px] bg-white text-[#141514] shadow-[0_24px_48px_-24px_rgb(20_21_20/0.45),0_2px_6px_rgb(20_21_20/0.12)]">
      <div className="flex items-center justify-between bg-track px-5 py-2.5 text-white">
        <span className="font-bib truncate text-lg uppercase">{pass.gym}</span>
        <span className="text-xs font-semibold">{format(t.bib, { n: pass.member_no })}</span>
      </div>
      <div className="px-6 pt-5 pb-6">
        <p className="font-bib tabular text-6xl leading-none">{pass.member_no}</p>
        <p className="mt-2 truncate text-base font-semibold">{pass.full_name}</p>
        {pass.summary && <p className="text-sm text-[#565a56]">{pass.summary}</p>}
        <div className="mx-auto mt-5 aspect-square w-full max-w-64">
          {svg ? (
            // QR drawn locally from the member's own secret.
            <div aria-label={t.show} role="img" className="h-full w-full [&>svg]:h-full [&>svg]:w-full" dangerouslySetInnerHTML={{ __html: svg }} />
          ) : (
            <div className="h-full w-full animate-pulse rounded-[6px] bg-[#f4f4f1]" />
          )}
        </div>
        <div className="mx-auto mt-4 max-w-64">
          <div className="h-1.5 overflow-hidden rounded-full bg-[#ecedea]" aria-hidden>
            <div className="h-full rounded-full bg-track transition-[width] duration-1000 ease-linear" style={{ width: `${progress * 100}%` }} />
          </div>
          <p className="mt-2 flex items-center justify-center gap-1.5 text-xs text-[#565a56]">
            {offline && <WifiOff aria-hidden className="h-3.5 w-3.5" />}
            {offline ? t.offline : format(t.refreshes, { n: String(left) })}
          </p>
        </div>
      </div>
    </figure>
  );
}
