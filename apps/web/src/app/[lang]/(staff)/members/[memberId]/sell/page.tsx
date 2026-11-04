import type { Metadata } from "next";
import { Suspense } from "react";

import { SaleFlow } from "@/components/billing/sale-flow";
import { loadLocale } from "@/i18n/dictionaries";

/** One sample id lets the build prerender the shell; real members load client-side. */
export function generateStaticParams() {
  return [{ memberId: "sample" }];
}

export async function generateMetadata({ params }: PageProps<"/[lang]/members/[memberId]/sell">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.billing.sale.title };
}

export default function SellPage({ params }: PageProps<"/[lang]/members/[memberId]/sell">) {
  return (
    <Suspense fallback={<div className="h-96 animate-pulse rounded-[6px] bg-surface" />}>
      {params.then(({ memberId }) => (
        <SaleFlow memberId={memberId} />
      ))}
    </Suspense>
  );
}
