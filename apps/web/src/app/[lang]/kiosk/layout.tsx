import { DictionaryProvider } from "@/i18n/dictionary-context";
import { loadLocale } from "@/i18n/dictionaries";

/** No site chrome: the kiosk fills the tablet screen. */
export default async function KioskLayout({ children, params }: LayoutProps<"/[lang]/kiosk">) {
  const { locale, dict } = await loadLocale(params);

  return (
    <DictionaryProvider locale={locale} dict={dict}>
      {children}
    </DictionaryProvider>
  );
}
