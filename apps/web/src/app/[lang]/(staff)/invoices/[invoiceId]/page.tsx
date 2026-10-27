import { Suspense } from "react";

import { InvoiceDetail } from "@/components/billing/invoice-detail";

/** One sample id lets the build prerender the shell; real invoices load client-side. */
export function generateStaticParams() {
  return [{ invoiceId: "sample" }];
}

export default function InvoicePage({ params }: PageProps<"/[lang]/invoices/[invoiceId]">) {
  return (
    <Suspense fallback={<div className="h-96 animate-pulse rounded-[6px] bg-surface" />}>
      {params.then(({ invoiceId }) => (
        <InvoiceDetail id={invoiceId} />
      ))}
    </Suspense>
  );
}
