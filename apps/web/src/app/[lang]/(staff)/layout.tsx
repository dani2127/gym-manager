import { StaffFrame } from "@/components/staff/staff-frame";
import { DictionaryProvider } from "@/i18n/dictionary-context";
import { loadLocale } from "@/i18n/dictionaries";

export default async function StaffLayout({ children, params }: LayoutProps<"/[lang]">) {
  const { locale, dict } = await loadLocale(params);

  return (
    <DictionaryProvider locale={locale} dict={dict}>
      <StaffFrame>{children}</StaffFrame>
    </DictionaryProvider>
  );
}
