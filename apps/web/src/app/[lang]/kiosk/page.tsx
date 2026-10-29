import type { Metadata } from "next";

import { KioskScreen } from "@/components/checkins/kiosk-screen";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/kiosk">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.checkins.kiosk.scanTitle, robots: { index: false } };
}

/** Full-screen entrance tablet. Signs in with its own device token, not a staff login. */
export default async function KioskPage({ params }: PageProps<"/[lang]/kiosk">) {
  const { locale } = await loadLocale(params);
  return <KioskScreen locale={locale} />;
}
