"use client";

import { Check, LogOut, Send } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import useSWR, { useSWRConfig } from "swr";

import { useMember } from "@/components/member/use-member";
import { useFormAction } from "@/components/staff/use-form-action";
import { Alert, Button, Field, Input } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { apiFetch, swrFetcher } from "@/lib/api";
import { formatPhone } from "@/lib/format";
import { clearPass } from "@/lib/member-pass";

type Prefs = { channel: "telegram" | "whatsapp" | null; telegram_linked: boolean; whatsapp_number: string };

/** Settings tab: Telegram or WhatsApp for reminders, and sign out. */
export function MemberSettings() {
  const { dict } = useDictionary();
  const t = dict.memberApp;
  const n = dict.settings.notifications;
  const router = useRouter();
  const { data: home, signedOut } = useMember();
  const { mutate: mutateAll } = useSWRConfig();
  const { data, mutate } = useSWR<{ data: Prefs }>(signedOut ? null : "/me/notifications", swrFetcher);
  const [channel, setChannel] = useState<"telegram" | "whatsapp" | null>(null);
  const { busy, error, general, saved, run } = useFormAction();
  const prefs = data?.data;
  const chosen = channel ?? prefs?.channel ?? "telegram";

  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const number = String(new FormData(event.currentTarget).get("whatsapp_number") ?? "");
    run(async () => {
      const res = await apiFetch<{ data: Prefs }>("/me/notifications", { method: "PUT", json: { channel: chosen, whatsapp_number: chosen === "whatsapp" ? number : null } });
      await mutate(res, { revalidate: false });
    });
  }

  async function connectTelegram() {
    const tab = window.open("", "_blank");
    try {
      const { url } = await apiFetch<{ url: string }>("/me/telegram/link", { method: "POST" });
      if (tab) tab.location.href = url;
      else window.location.href = url;
    } catch {
      tab?.close();
    }
  }

  async function signOut() {
    await apiFetch("/auth/logout", { method: "POST" }).catch(() => {});
    clearPass();
    await mutateAll(() => true, undefined, { revalidate: false });
    router.replace("/app/login");
  }

  return (
    <div className="space-y-6">
      <h1 className="font-bib text-4xl">{t.settings.title}</h1>

      <form onSubmit={save} className="space-y-4 rounded-[10px] border border-line bg-surface p-5" noValidate>
        <div>
          <h2 className="font-semibold">{n.title}</h2>
          <p className="mt-1 text-sm text-graphite">{n.description}</p>
        </div>
        {general && <Alert tone="error">{general}</Alert>}
        <div role="radiogroup" aria-label={n.channelLabel} className="grid grid-cols-2 gap-1 rounded-[6px] bg-surface-2 p-1">
          {(["telegram", "whatsapp"] as const).map((c) => (
            <button
              key={c}
              type="button"
              role="radio"
              aria-checked={chosen === c}
              onClick={() => setChannel(c)}
              className="min-h-11 cursor-pointer rounded-[5px] text-sm font-semibold text-graphite aria-checked:bg-surface aria-checked:text-ink aria-checked:shadow-sm"
            >
              {n[c]}
            </button>
          ))}
        </div>

        {chosen === "telegram" ? (
          prefs?.telegram_linked ? (
            <p className="flex items-center gap-2 text-sm font-semibold text-infield">
              <Check aria-hidden className="h-4 w-4" />
              {t.notifications.linked}
            </p>
          ) : (
            <div className="space-y-2">
              <p className="text-sm text-graphite">{n.telegramHelp}</p>
              <Button type="button" variant="secondary" onClick={connectTelegram}>
                <Send aria-hidden className="h-4 w-4" />
                {t.notifications.connectTelegram}
              </Button>
            </div>
          )
        ) : (
          <Field label={n.whatsappNumber} hint={n.whatsappHelp} error={error?.field("whatsapp_number")}>
            {(p) => <Input {...p} key={prefs?.whatsapp_number} name="whatsapp_number" type="tel" inputMode="tel" defaultValue={formatPhone(prefs?.whatsapp_number)} />}
          </Field>
        )}
        {error?.field("channel") && <p className="text-xs font-medium text-critical">{error.field("channel")}</p>}
        <div className="flex items-center gap-3">
          <Button type="submit" busy={busy}>
            {t.notifications.save}
          </Button>
          {saved && <span className="text-sm font-semibold text-infield">{t.notifications.saved}</span>}
        </div>
      </form>

      <section className="space-y-3 rounded-[10px] border border-line bg-surface p-5">
        <h2 className="font-semibold">{t.settings.account}</h2>
        {home && (
          <p className="text-sm text-graphite">
            {home.data.member.full_name} · #{home.data.member.member_no} · {formatPhone(home.data.member.phone)}
          </p>
        )}
        <Button variant="secondary" onClick={signOut}>
          <LogOut aria-hidden className="h-4 w-4" />
          {t.settings.signOut}
        </Button>
      </section>
    </div>
  );
}
