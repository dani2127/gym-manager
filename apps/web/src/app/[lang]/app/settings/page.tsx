import type { Metadata } from "next";

import { NotificationPreference } from "@/components/notification-preference";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/app/settings">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.settings.title };
}

export default async function MemberSettingsPage({ params }: PageProps<"/[lang]/app/settings">) {
  const { dict } = await loadLocale(params);

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">{dict.settings.title}</h1>
      <NotificationPreference t={dict.settings.notifications} />
    </div>
  );
}
