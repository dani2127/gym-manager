import type { Metadata } from "next";

import { DoorAdmin } from "@/components/checkins/door-admin";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/door">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.checkins.door.title };
}

export default function Page() {
  return <DoorAdmin />;
}
