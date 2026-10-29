"use client";

import { Smartphone } from "lucide-react";
import { useState } from "react";
import useSWR from "swr";

import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Card } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { formatDateTime } from "@/lib/format";
import type { Member } from "@/lib/types";

type Access = { status: "none" | "invited" | "active"; last_login_at: string | null; setup_code?: string };

/** Desk: invite a member to the app with a setup code, or sign out a lost phone. */
export function AppAccessCard({ member }: { member: Member }) {
  const { locale, dict } = useDictionary();
  const t = dict.memberAccess;
  const { data, mutate } = useSWR<{ data: Access }>(`/members/${member.id}/app-access`, swrFetcher);
  const [code, setCode] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const { busy, error, general, run } = useFormAction();
  const access = data?.data;
  const appUrl = typeof window !== "undefined" ? `${window.location.origin}/app` : "/app";

  return (
    <Card title={t.title}>
      <div className="space-y-4">
        {(general || error?.field("phone")) && <Alert tone="error">{error?.field("phone") ?? general}</Alert>}
        {access && (
          <p className="flex items-start gap-2 text-sm">
            <Smartphone aria-hidden className="mt-0.5 h-4 w-4 shrink-0 text-graphite" />
            {access.status === "active" ? format(t.active, { time: formatDateTime(access.last_login_at, locale) }) : t[access.status]}
          </p>
        )}

        {code && (
          <div className="rounded-[6px] border-2 border-track bg-track-soft p-4 text-center">
            <p className="text-xs font-semibold text-graphite">{t.codeTitle}</p>
            <p className="font-bib tabular my-2 text-4xl tracking-wider">{code}</p>
            <p className="text-xs text-graphite">{format(t.codeSteps, { name: member.first_name, url: appUrl })}</p>
            <Button variant="secondary" className="mt-3 min-h-9 py-1" onClick={() => setCode(null)}>
              {t.close}
            </Button>
          </div>
        )}

        {confirming ? (
          <div className="space-y-3 rounded-[6px] border border-amber/40 bg-amber-soft p-3 text-sm">
            <p>{t.signOutConfirm}</p>
            <div className="flex gap-2">
              <Button
                variant="danger"
                busy={busy}
                onClick={() =>
                  run(async () => {
                    const res = await apiFetch<{ data: Access }>(`/members/${member.id}/app-access`, { method: "DELETE" });
                    await mutate(res, { revalidate: false });
                    setConfirming(false);
                  })
                }
              >
                {t.signOutYes}
              </Button>
              <Button variant="secondary" onClick={() => setConfirming(false)}>
                {dict.common.cancel}
              </Button>
            </div>
          </div>
        ) : (
          !code && (
            <div className="flex flex-wrap gap-2">
              <Button
                variant={access?.status === "none" ? "primary" : "secondary"}
                busy={busy}
                onClick={() =>
                  run(async () => {
                    const res = await apiFetch<{ data: Access }>(`/members/${member.id}/app-access`, { method: "POST" });
                    setCode(res.data.setup_code ?? null);
                    await mutate({ data: res.data }, { revalidate: false });
                  })
                }
              >
                {access?.status === "none" ? t.invite : t.reinvite}
              </Button>
              {access && access.status !== "none" && (
                <Button variant="danger" onClick={() => setConfirming(true)}>
                  {t.signOut}
                </Button>
              )}
            </div>
          )
        )}
      </div>
    </Card>
  );
}
