import type { Metadata } from "next";

import { ClassesAdmin } from "@/components/classes/classes-admin";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/admin/classes">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.classes.adminTitle };
}

export default function Page() {
  return <ClassesAdmin />;
}
