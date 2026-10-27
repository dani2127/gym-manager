import { loadLocale } from "@/i18n/dictionaries";

export default async function MemberHomePage({ params }: PageProps<"/[lang]/app">) {
  const { dict } = await loadLocale(params);
  const t = dict.member;

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">{t.greeting}</h1>
      <section className="rounded-2xl bg-brand p-5 text-brand-foreground">
        <h2 className="text-sm font-medium opacity-80">{t.cardTitle}</h2>
        <div className="mt-4 grid aspect-square w-32 place-items-center rounded-xl bg-white/90 text-xs text-slate-500">
          QR
        </div>
        <p className="mt-4 text-sm opacity-90">{t.cardBody}</p>
      </section>
      <p className="text-xs text-muted">{t.installHint}</p>
    </div>
  );
}
