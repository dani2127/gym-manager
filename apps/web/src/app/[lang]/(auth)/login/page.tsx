import type { Metadata } from "next";

import { LoginForm } from "@/components/auth/login-form";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/login">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.auth.loginTitle };
}

export default async function LoginPage({ params }: PageProps<"/[lang]/login">) {
  const { dict } = await loadLocale(params);

  return (
    <>
      <h1 className="font-bib mb-6 text-5xl uppercase">{dict.auth.loginTitle}</h1>
      <LoginForm />
    </>
  );
}
