import type { Metadata } from "next";

import { ActivityLog } from "@/components/staff/activity-log";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/activity">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.audit.title };
}

export default function Page() {
  return <ActivityLog />;
}
