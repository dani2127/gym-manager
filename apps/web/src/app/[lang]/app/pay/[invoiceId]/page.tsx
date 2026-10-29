import { Suspense } from "react";

import { MemberPay } from "@/components/member/member-pay";

/** One sample id lets the build prerender the shell; real orders load client-side. */
export function generateStaticParams() {
  return [{ invoiceId: "sample" }];
}

export default function PayPage({ params }: PageProps<"/[lang]/app/pay/[invoiceId]">) {
  return (
    <Suspense fallback={<div className="h-96 animate-pulse rounded-[10px] bg-surface" />}>
      {params.then(({ invoiceId }) => (
        <MemberPay id={invoiceId} />
      ))}
    </Suspense>
  );
}
