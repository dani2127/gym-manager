"use client";

import useSWR from "swr";

import { Alert, PageHeader } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { useSession } from "@/lib/session";
import type { AuditEntry } from "@/lib/types";

export function ActivityLog() {
  const { locale, dict } = useDictionary();
  const { me } = useSession();
  const { data } = useSWR<{ data: AuditEntry[] }>(me.abilities.view_audit_log ? "/audit-logs" : null, swrFetcher);
  const t = dict.audit;

  if (!me.abilities.view_audit_log) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  const formatter = new Intl.DateTimeFormat(locale === "am" ? "am-ET" : "en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Africa/Addis_Ababa",
  });

  return (
    <>
      <PageHeader title={t.title} />
      {!data ? (
        <p className="text-sm text-muted">{dict.common.loading}</p>
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted">{t.empty}</p>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-border bg-surface">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border text-xs text-muted">
              <tr>
                <th className="px-4 py-2 font-medium">{t.when}</th>
                <th className="px-4 py-2 font-medium">{t.who}</th>
                <th className="px-4 py-2 font-medium">{t.action}</th>
                <th className="px-4 py-2 font-medium">{t.branch}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {data.data.map((entry) => (
                <tr key={entry.id}>
                  <td className="px-4 py-2 whitespace-nowrap">{formatter.format(new Date(entry.created_at))}</td>
                  <td className="px-4 py-2">{entry.user?.name ?? "—"}</td>
                  <td className="px-4 py-2 font-mono text-xs">{entry.action}</td>
                  <td className="px-4 py-2">{entry.branch?.name ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
