import { Suspense } from "react";

import { MemberProfile } from "@/components/members/member-profile";

/**
 * One sample id lets the build prerender the shell (the console reads the
 * pathname); real member ids render on request and load their data client-side.
 */
export function generateStaticParams() {
  return [{ memberId: "sample" }];
}

export default function MemberPage({ params }: PageProps<"/[lang]/members/[memberId]">) {
  return (
    <Suspense fallback={<div className="h-64 animate-pulse rounded-[6px] bg-surface" />}>
      {params.then(({ memberId }) => (
        <MemberProfile id={memberId} />
      ))}
    </Suspense>
  );
}
