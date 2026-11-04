import "server-only";

import { notFound } from "next/navigation";

import { hasLocale, type Locale } from "./config";
import type en from "./dictionaries/en.json";

export type Dictionary = typeof en;

const dictionaries: Record<Locale, () => Promise<Dictionary>> = {
  en: () => import("./dictionaries/en.json").then((m) => m.default),
  am: () => import("./dictionaries/am.json").then((m) => m.default),
};

export const getDictionary = (locale: Locale) => dictionaries[locale]();

/** Resolves the `[lang]` route param to a locale and its dictionary (404 if unsupported). */
export async function loadLocale(params: Promise<{ lang: string }>) {
  const { lang } = await params;
  if (!hasLocale(lang)) notFound();
  return { locale: lang, dict: await getDictionary(lang) };
}
