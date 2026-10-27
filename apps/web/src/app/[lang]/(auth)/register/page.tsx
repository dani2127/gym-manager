import type { Metadata } from "next";

import { RegisterForm } from "@/components/auth/register-form";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/register">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.register.title };
}

export default async function RegisterPage({ params }: PageProps<"/[lang]/register">) {
  const { dict } = await loadLocale(params);

  return (
    <>
      <h1 className="font-bib mb-6 text-5xl uppercase">{dict.register.title}</h1>
      <RegisterForm />
    </>
  );
}
