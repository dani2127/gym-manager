import type { Metadata } from "next";

import { TransferQueue } from "@/components/billing/transfer-queue";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/payments">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.transfers.queue.title };
}

export default function Page() {
  return <TransferQueue />;
}
