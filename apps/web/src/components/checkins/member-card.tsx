"use client";

import { Printer, RefreshCw } from "lucide-react";
import QRCode from "qrcode";
import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { Member } from "@/lib/types";

/** The card face, drawn in mm so the printout is exactly credit-card size (85.6 × 54). */
function CardFace({ member, gym, svg }: { member: Member; gym: string; svg: string }) {
  return (
    <div className="member-card relative flex h-[54mm] w-[85.6mm] flex-col overflow-hidden rounded-[3mm] bg-white text-[#141514] ring-1 ring-black/10">
      <div className="flex h-[9mm] items-center justify-between bg-track px-[4mm] text-white">
        <span className="font-bib truncate text-[11pt] uppercase">{gym}</span>
        <span className="text-[6.5pt] font-semibold tracking-wide uppercase">{member.home_branch?.name}</span>
      </div>
      <div className="grid flex-1 grid-cols-[1fr_auto] items-center gap-[3mm] px-[4mm]">
        <div className="min-w-0">
          <p className="font-bib tabular text-[30pt] leading-none">{member.member_no}</p>
          <p className="mt-[2mm] truncate text-[9pt] font-semibold">{member.full_name}</p>
          <p className="mt-[1mm] text-[6pt] leading-tight text-[#565a56]">Show this card at the desk · ይህን ካርድ በዴስኩ ያሳዩ</p>
        </div>
        {/* SVG generated locally by the qrcode library from our own code. */}
        <div className="h-[30mm] w-[30mm] [&>svg]:h-full [&>svg]:w-full" dangerouslySetInnerHTML={{ __html: svg }} />
      </div>
      <div className="h-[1.5mm] bg-track" />
    </div>
  );
}

/**
 * The printed member card: its QR opens the door like the app's code.
 * Replacing a lost card makes the old one stop working at once.
 */
export function MemberCardPanel({ member }: { member: Member }) {
  const { dict } = useDictionary();
  const { me } = useSession();
  const t = dict.checkins.profile;
  const { data, mutate } = useSWR<{ data: { code: string; version: number } }>(`/members/${member.id}/card`, swrFetcher);
  const [svg, setSvg] = useState("");
  const [confirming, setConfirming] = useState(false);
  const { busy, general, run } = useFormAction();

  useEffect(() => {
    if (!data) return;
    let cancelled = false;
    QRCode.toString(data.data.code, { type: "svg", margin: 0, errorCorrectionLevel: "M", color: { dark: "#141514", light: "#ffffff" } }).then((s) => !cancelled && setSvg(s));
    return () => {
      cancelled = true;
    };
  }, [data]);

  const face = svg ? <CardFace member={member} gym={me.organization.name} svg={svg} /> : null;

  return (
    <Card title={t.cardTitle} actions={data && <span className="text-xs text-graphite">{format(t.version, { n: String(data.data.version) })}</span>}>
      {general && <Alert tone="error">{general}</Alert>}
      <div className="space-y-4">
        <div className="flex justify-center">{face ?? <div className="h-[54mm] w-[85.6mm] animate-pulse rounded-[3mm] bg-surface-2" />}</div>
        <p className="text-xs text-graphite">{t.cardHint}</p>
        {confirming ? (
          <div className="space-y-3 rounded-[6px] border border-amber/40 bg-amber-soft p-3 text-sm">
            <p>{t.replaceConfirm}</p>
            <div className="flex gap-2">
              <Button
                variant="danger"
                busy={busy}
                onClick={() =>
                  run(async () => {
                    const next = await apiFetch<{ data: { code: string; version: number } }>(`/members/${member.id}/card/reissue`, { method: "POST" });
                    await mutate(next, { revalidate: false });
                    setConfirming(false);
                  })
                }
              >
                {t.replaceYes}
              </Button>
              <Button variant="secondary" onClick={() => setConfirming(false)}>
                {dict.common.cancel}
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => window.print()} disabled={!face}>
              <Printer aria-hidden className="h-4 w-4" />
              {t.print}
            </Button>
            <Button variant="secondary" onClick={() => setConfirming(true)}>
              <RefreshCw aria-hidden className="h-4 w-4" />
              {t.replace}
            </Button>
          </div>
        )}
      </div>
      {/* Only the card prints; see .print-card in globals.css. */}
      {face && typeof document !== "undefined" && createPortal(<div className="print-card">{face}</div>, document.body)}
    </Card>
  );
}
