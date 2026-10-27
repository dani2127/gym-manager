"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { hasLocale } from "@/i18n/config";

export function MemberTabs({ labels }: { labels: { home: string; settings: string } }) {
  const pathname = usePathname();
  const [, first, ...rest] = pathname.split("/");
  const current = "/" + (hasLocale(first) ? rest : [first, ...rest]).join("/");

  const tabs = [
    { href: "/app", label: labels.home },
    { href: "/app/settings", label: labels.settings },
  ];

  return (
    <nav className="sticky bottom-0 grid grid-cols-2 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]">
      {tabs.map((tab) => (
        <Link
          key={tab.href}
          href={tab.href}
          aria-current={current === tab.href ? "page" : undefined}
          className="py-3 text-center text-sm font-medium text-muted aria-[current=page]:text-brand"
        >
          {tab.label}
        </Link>
      ))}
    </nav>
  );
}
