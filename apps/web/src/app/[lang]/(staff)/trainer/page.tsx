import type { Metadata } from "next";

import { TrainerHome } from "@/components/staff/trainer-home";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/trainer">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.staff.trainer.title };
}

export default function TrainerPage() {
  return <TrainerHome />;
}
