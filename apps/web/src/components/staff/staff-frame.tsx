"use client";

import {
  BadgeDollarSign,
  Building2,
  Contact,
  Dumbbell,
  History,
  KeyRound,
  LayoutGrid,
  LogOut,
  Menu,
  ReceiptText,
  ScanLine,
  Smartphone,
  Settings2,
  UserRound,
  Users,
  X,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState, type ReactNode } from "react";
import useSWR from "swr";

import { Logo } from "@/components/brand/logo";
import { LanguageSwitcher } from "@/components/language-switcher";
import { Alert, Button } from "@/components/ui";
import { hasLocale } from "@/i18n/config";
import { useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { handlesMoneyAt, workspaces } from "@/lib/roles";
import { SessionProvider, useSession } from "@/lib/session";

/** Signed-in shell for /desk, /admin, /trainer and /account. */
export function StaffFrame({ children }: { children: ReactNode }) {
  const { locale, dict } = useDictionary();

  return (
    <SessionProvider
      locale={locale}
      loading={<ShellSkeleton />}
      forbidden={(signOut) => (
        <div className="mx-auto max-w-md space-y-4 p-10">
          <Alert tone="error">{dict.common.notAllowed}</Alert>
          <Button variant="secondary" onClick={signOut}>
            {dict.common.signOut}
          </Button>
        </div>
      )}
    >
      <Shell>{children}</Shell>
    </SessionProvider>
  );
}

type NavItem = { href: string; label: string; icon: LucideIcon; exact?: boolean; badge?: number };

function useNav() {
  const { dict } = useDictionary();
  const { me, branch } = useSession();
  const access = workspaces(me);
  // Transfers waiting for the desk at the selected branch, shown as a count.
  const { data: transfers } = useSWR<{ meta: { waiting: number } }>(access.desk && branch && handlesMoneyAt(me, branch.id) ? `/payments/transfers?branch=${branch.id}` : null, swrFetcher, { refreshInterval: 30_000 });

  const work: NavItem[] = [
    ...(access.desk ? [{ href: "/desk", label: dict.nav.desk, icon: ScanLine }] : []),
    { href: "/members", label: dict.nav.members, icon: Contact },
    ...(access.desk ? [{ href: "/invoices", label: dict.nav.invoices, icon: ReceiptText }] : []),
    ...(access.desk ? [{ href: "/payments", label: dict.nav.transfers, icon: Smartphone, badge: transfers?.meta.waiting }] : []),
    ...(access.trainer ? [{ href: "/trainer", label: dict.nav.trainer, icon: Dumbbell }] : []),
  ];

  const admin: NavItem[] = access.admin
    ? [
        { href: "/admin", label: dict.staff.admin.title, icon: LayoutGrid, exact: true },
        { href: "/admin/branches", label: dict.nav.branches, icon: Building2 },
        { href: "/admin/pricing", label: dict.nav.pricing, icon: BadgeDollarSign },
        ...(me.user.account_type === "owner" || me.branches.some((b) => b.role === "manager") ? [{ href: "/admin/door", label: dict.nav.door, icon: KeyRound }] : []),
        ...(me.abilities.manage_staff ? [{ href: "/admin/staff", label: dict.nav.staff, icon: Users }] : []),
        { href: "/admin/settings", label: dict.nav.settings, icon: Settings2 },
        ...(me.abilities.view_audit_log ? [{ href: "/admin/activity", label: dict.nav.audit, icon: History }] : []),
      ]
    : [];

  return { work, admin };
}

function useCurrentPath() {
  const pathname = usePathname();
  const [, first, ...rest] = pathname.split("/");
  return "/" + (hasLocale(first) ? rest : [first, ...rest]).join("/");
}

function Shell({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);

  return (
    <div className="flex min-h-full flex-1 flex-col lg:flex-row">
      {/* Desktop rail */}
      <aside className="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col bg-asphalt text-asphalt-ink lg:flex">
        <Sidebar />
      </aside>

      {/* Mobile bar + disclosure menu */}
      <MobileBar open={open} onToggle={() => setOpen((v) => !v)} />
      {open && (
        <div id="mobile-nav" className="bg-asphalt text-asphalt-ink lg:hidden">
          <Sidebar onNavigate={() => setOpen(false)} />
        </div>
      )}

      <main className="min-w-0 flex-1">
        <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-8 sm:py-8">{children}</div>
      </main>
    </div>
  );
}

function MobileBar({ open, onToggle }: { open: boolean; onToggle: () => void }) {
  const { dict } = useDictionary();
  const { me } = useSession();

  return (
    <header className="flex h-14 items-center justify-between bg-asphalt px-4 text-asphalt-ink lg:hidden">
      <span className="truncate">
        <Logo name={me.organization.name} tone="lane" />
      </span>
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={open}
        aria-controls="mobile-nav"
        aria-label={dict.common.menu}
        className="grid h-11 w-11 cursor-pointer place-items-center rounded-[6px] hover:bg-asphalt-2"
      >
        {open ? <X aria-hidden className="h-5 w-5" /> : <Menu aria-hidden className="h-5 w-5" />}
      </button>
    </header>
  );
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { locale, dict } = useDictionary();
  const { me, branch, setBranch, signOut } = useSession();
  const { work, admin } = useNav();
  const current = useCurrentPath();
  const role = me.user.account_type === "owner" ? dict.staffPage.roles.owner : branch?.role ? dict.staffPage.roles[branch.role] : "";

  return (
    <div className="flex h-full flex-col gap-6 px-3 py-5">
      <div className="hidden px-3 lg:block">
        <Logo name={me.organization.name} tone="lane" />
      </div>

      {me.branches.length > 0 && (
        <label className="block px-3">
          <span className="mb-1.5 block text-xs font-semibold text-asphalt-muted">{dict.nav.branch}</span>
          <select
            value={branch?.id}
            onChange={(event) => setBranch(event.target.value)}
            className="h-10 w-full cursor-pointer rounded-[6px] border border-white/10 bg-asphalt-2 px-3 text-sm font-semibold text-asphalt-ink outline-none focus:border-white/40"
          >
            {me.branches.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </select>
        </label>
      )}

      <nav className="flex-1 space-y-6 overflow-y-auto">
        {work.length > 0 && <NavGroup items={work} current={current} onNavigate={onNavigate} />}
        {admin.length > 0 && <NavGroup title={dict.nav.admin} items={admin} current={current} onNavigate={onNavigate} />}
      </nav>

      <div className="space-y-3 border-t border-white/10 pt-4">
        <NavGroup
          items={[{ href: "/account", label: dict.nav.account, icon: UserRound }]}
          current={current}
          onNavigate={onNavigate}
        />
        <div className="px-3">
          <p className="truncate text-sm font-semibold">{me.user.name}</p>
          <p className="truncate text-xs text-asphalt-muted">{role}</p>
        </div>
        <div className="flex items-center justify-between gap-2 px-3 [&_select]:border-white/10 [&_select]:bg-asphalt-2 [&_select]:text-asphalt-ink">
          <LanguageSwitcher locale={locale} label={dict.common.language} />
          <button
            type="button"
            onClick={signOut}
            className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-[6px] px-2 text-sm text-asphalt-muted hover:bg-asphalt-2 hover:text-asphalt-ink"
          >
            <LogOut aria-hidden className="h-4 w-4" />
            {dict.common.signOut}
          </button>
        </div>
      </div>
    </div>
  );
}

function NavGroup({
  title,
  items,
  current,
  onNavigate,
}: {
  title?: string;
  items: NavItem[];
  current: string;
  onNavigate?: () => void;
}) {
  return (
    <div>
      {title && <p className="mb-1 px-3 text-xs font-semibold text-asphalt-muted">{title}</p>}
      <ul className="space-y-0.5">
        {items.map(({ href, label, icon: Icon, exact, badge }) => {
          const active = exact ? current === href : current === href || current.startsWith(`${href}/`);
          return (
            <li key={href}>
              <Link
                href={href}
                onClick={onNavigate}
                aria-current={active ? "page" : undefined}
                className="group flex min-h-10 items-center gap-3 rounded-[6px] px-3 text-sm font-medium text-asphalt-muted transition-colors duration-150 hover:bg-asphalt-2 hover:text-asphalt-ink aria-[current=page]:bg-asphalt-2 aria-[current=page]:text-asphalt-ink"
              >
                <Icon aria-hidden className="h-4 w-4 shrink-0 group-aria-[current=page]:text-track" />
                <span className="flex-1">{label}</span>
                {badge ? <span className="tabular rounded-full bg-track px-2 py-0.5 text-xs font-semibold text-white">{badge}</span> : null}
              </Link>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

/** Same geometry as the shell, so nothing jumps when /me arrives. */
function ShellSkeleton() {
  return (
    <div className="flex min-h-full flex-1 flex-col lg:flex-row" aria-busy>
      <div className="hidden h-dvh w-64 shrink-0 bg-asphalt lg:block" />
      <div className="h-14 bg-asphalt lg:hidden" />
      <div className="mx-auto w-full max-w-6xl space-y-4 px-4 py-8 sm:px-8">
        <div className="h-8 w-48 animate-pulse rounded-[6px] bg-line" />
        <div className="h-40 animate-pulse rounded-[6px] bg-line/70" />
      </div>
    </div>
  );
}
