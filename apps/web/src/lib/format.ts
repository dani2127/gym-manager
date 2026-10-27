import type { Locale } from "@/i18n/config";

/** "+251911223344" → "0911 223 344", the way Ethiopians write mobile numbers. */
export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return "";
  const local = phone.replace(/^\+251/, "0");
  return local.length === 10 ? `${local.slice(0, 4)} ${local.slice(4, 7)} ${local.slice(7)}` : local;
}

/** A calendar date ("2026-10-27"), or a timestamp's day in Addis Ababa, in the UI language. */
export function formatDate(date: string | null | undefined, locale: Locale): string {
  if (!date) return "";
  if (date.length > 10) {
    return new Intl.DateTimeFormat(locale === "am" ? "am-ET" : "en-GB", { dateStyle: "medium", timeZone: "Africa/Addis_Ababa" }).format(new Date(date));
  }
  return new Intl.DateTimeFormat(locale === "am" ? "am-ET" : "en-GB", { dateStyle: "medium", timeZone: "UTC" }).format(new Date(`${date.slice(0, 10)}T00:00:00Z`));
}

/** Santim → "ETB 2,500.00" (English) or "2,500.00 ብር" (Amharic). */
export function formatMoney(cents: number, locale: Locale, { sign = false }: { sign?: boolean } = {}): string {
  const amount = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.abs(cents) / 100);
  const prefix = sign && cents < 0 ? "−" : "";
  return locale === "am" ? `${prefix}${amount} ብር` : `${prefix}ETB ${amount}`;
}

/** Santim → "2,500.00" without currency, for receipt columns. */
export function formatAmount(cents: number): string {
  return new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100);
}

/** Birr typed by staff ("2,500" or "2500.5") → santim, or null if not a number. */
export function birrToCents(value: string): number | null {
  const clean = value.replace(/[,\s]/g, "");
  if (clean === "" || !/^\d+(\.\d{0,2})?$/.test(clean)) return null;
  return Math.round(Number(clean) * 100);
}

/** Santim → plain birr for an input ("2500" or "2500.50"). */
export function centsToBirr(cents: number): string {
  return cents % 100 === 0 ? String(cents / 100) : (cents / 100).toFixed(2);
}

/** A timestamp as date and time in Addis Ababa. */
export function formatDateTime(iso: string | null | undefined, locale: Locale): string {
  if (!iso) return "";
  return new Intl.DateTimeFormat(locale === "am" ? "am-ET" : "en-GB", { dateStyle: "medium", timeStyle: "short", timeZone: "Africa/Addis_Ababa" }).format(new Date(iso));
}

/** Today's date in Addis Ababa as "2026-10-27". */
export function addisToday(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Africa/Addis_Ababa" }).format(new Date());
}
