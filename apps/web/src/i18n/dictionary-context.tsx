"use client";

import { createContext, useContext, type ReactNode } from "react";

import type { Locale } from "./config";
import type { Dictionary } from "./dictionaries";

const DictionaryContext = createContext<{ locale: Locale; dict: Dictionary } | null>(null);

/** Hands the server-loaded dictionary to client components below it. */
export function DictionaryProvider({
  locale,
  dict,
  children,
}: {
  locale: Locale;
  dict: Dictionary;
  children: ReactNode;
}) {
  return <DictionaryContext.Provider value={{ locale, dict }}>{children}</DictionaryContext.Provider>;
}

export function useDictionary() {
  const value = useContext(DictionaryContext);
  if (!value) throw new Error("useDictionary must be used inside DictionaryProvider");
  return value;
}

/** Fills `{name}` placeholders: format("Open {opens}", { opens: "06:00" }). */
export function format(template: string, values: Record<string, string>) {
  return template.replace(/\{(\w+)\}/g, (match, key: string) => values[key] ?? match);
}
