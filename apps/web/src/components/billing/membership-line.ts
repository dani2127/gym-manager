import { format } from "@/i18n/dictionary-context";
import type { Dictionary } from "@/i18n/dictionaries";
import type { Locale } from "@/i18n/config";
import { formatDate } from "@/lib/format";
import type { Membership } from "@/lib/types";

/** What is left on a membership: "12 days left", "4 of 10 visits left", "Starts 26 Nov". */
export function membershipLeft(m: Membership, dict: Dictionary, locale: Locale): string {
  const t = dict.billing.memberships;
  if (m.state === "upcoming") return format(t.startsOn, { date: formatDate(m.starts_on, locale) });
  const parts: string[] = [];
  if (m.days_left !== null) parts.push(m.days_left === 1 ? t.lastDay : format(t.daysLeft, { n: String(m.days_left) }));
  if (m.visits_total !== null) parts.push(format(t.visitsLeft, { n: String(m.visits_remaining ?? 0), total: String(m.visits_total) }));
  if (parts.length === 0 && m.ends_on) parts.push(format(t.until, { date: formatDate(m.ends_on, locale) }));
  return parts.join(" · ");
}
