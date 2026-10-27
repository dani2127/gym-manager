import type { Metadata } from "next";

import { StaffShell } from "@/components/staff-shell";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/trainer">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staff.trainer.title };
}

export default async function TrainerPage({ params }: PageProps<"/[lang]/trainer">) {
  const { locale, dict } = await loadLocale(params);
  const t = dict.staff.trainer;

  return <StaffShell locale={locale} dict={dict} title={t.title} body={t.body} />;
}
