import type { Metadata } from "next";

import { StaffShell } from "@/components/staff-shell";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/desk">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staff.desk.title };
}

export default async function DeskPage({ params }: PageProps<"/[lang]/desk">) {
  const { locale, dict } = await loadLocale(params);
  const t = dict.staff.desk;

  return <StaffShell locale={locale} dict={dict} title={t.title} body={t.body} />;
}
