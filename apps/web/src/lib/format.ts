import type { Locale } from "@/i18n/config";

/** "+251911223344" → "0911 223 344", the way Ethiopians write mobile numbers. */
export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return "";
  const local = phone.replace(/^\+251/, "0");
  return local.length === 10 ? `${local.slice(0, 4)} ${local.slice(4, 7)} ${local.slice(7)}` : local;
}

/** A calendar date ("2026-10-27") in the UI language. */
export function formatDate(date: string | null | undefined, locale: Locale): string {
  if (!date) return "";
  return new Intl.DateTimeFormat(locale === "am" ? "am-ET" : "en-GB", { dateStyle: "medium", timeZone: "UTC" }).format(new Date(`${date.slice(0, 10)}T00:00:00Z`));
}
