import { ApiStatus } from "@/components/api-status";
import { LanguageSwitcher } from "@/components/language-switcher";
import type { Locale } from "@/i18n/config";
import type { Dictionary } from "@/i18n/dictionaries";

/** Placeholder layout shared by the desk, admin and trainer workspaces. */
export function StaffShell({
  locale,
  dict,
  title,
  body,
}: {
  locale: Locale;
  dict: Dictionary;
  title: string;
  body: string;
}) {
  return (
    <div className="flex min-h-full flex-1 flex-col">
      <header className="flex items-center justify-between border-b border-border bg-surface px-6 py-3">
        <span className="font-semibold">{dict.meta.title}</span>
        <LanguageSwitcher locale={locale} label={dict.common.language} />
      </header>
      <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-10">
        <h1 className="text-2xl font-semibold">{title}</h1>
        <p className="mt-2 max-w-2xl text-muted">{body}</p>
        <span className="mt-6 inline-block rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-brand">
          {dict.common.comingSoon}
        </span>
      </main>
      <footer className="border-t border-border px-6 py-3">
        <ApiStatus
          labels={{
            label: dict.common.apiStatus,
            online: dict.common.apiOnline,
            offline: dict.common.apiOffline,
            checking: dict.common.apiChecking,
          }}
        />
      </footer>
    </div>
  );
}
