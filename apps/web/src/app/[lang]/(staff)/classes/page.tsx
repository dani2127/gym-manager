import type { Metadata } from "next";

import { ClassesDesk } from "@/components/classes/classes-desk";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/classes">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.classes.title };
}

export default function Page() {
  return <ClassesDesk />;
}
