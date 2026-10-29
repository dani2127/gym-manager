"use client";

import { CreditCard, ReceiptText, Settings2 } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { hasLocale } from "@/i18n/config";

/** Bottom tabs of the member app. Hidden on sign-in and setup. */
export function MemberTabs({ labels }: { labels: { home: string; membership: string; settings: string } }) {
  const pathname = usePathname();
  const [, first, ...rest] = pathname.split("/");
  const current = "/" + (hasLocale(first) ? rest : [first, ...rest]).join("/");

  if (current === "/app/login" || current === "/app/setup") return null;

  const tabs = [
    { href: "/app", label: labels.home, icon: CreditCard },
    { href: "/app/membership", label: labels.membership, icon: ReceiptText },
    { href: "/app/settings", label: labels.settings, icon: Settings2 },
  ];

  return (
    <nav className="sticky bottom-0 grid grid-cols-3 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)]">
      {tabs.map(({ href, label, icon: Icon }) => (
        <Link
          key={href}
          href={href}
          aria-current={current === href ? "page" : undefined}
          className="flex flex-col items-center gap-1 py-2.5 text-xs font-semibold text-graphite aria-[current=page]:text-track-deep aria-[current=page]:shadow-[inset_0_2px_0_var(--track)]"
        >
          <Icon aria-hidden className="h-5 w-5" />
          {label}
        </Link>
      ))}
    </nav>
  );
}
