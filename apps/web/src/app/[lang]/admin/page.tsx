import type { Metadata } from "next";

import { StaffShell } from "@/components/staff-shell";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staff.admin.title };
}

export default async function AdminPage({ params }: PageProps<"/[lang]/admin">) {
  const { locale, dict } = await loadLocale(params);
  const t = dict.staff.admin;

  return <StaffShell locale={locale} dict={dict} title={t.title} body={t.body} />;
}
