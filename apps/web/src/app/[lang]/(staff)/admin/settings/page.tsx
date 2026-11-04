import type { Metadata } from "next";

import { SettingsAdmin } from "@/components/staff/settings-admin";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/settings">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.settingsPage.title };
}

export default function Page() {
  return <SettingsAdmin />;
}
