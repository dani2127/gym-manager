import type { Metadata } from "next";

import { ForgotPasswordForm } from "@/components/auth/forgot-password-form";
import { loadLocale } from "@/i18n/dictionaries";

export async function generateMetadata({ params }: PageProps<"/[lang]/forgot-password">): Promise<Metadata> {
  const { dict } = await loadLocale(params);
  return { title: dict.auth.forgotTitle };
}

export default async function ForgotPasswordPage({ params }: PageProps<"/[lang]/forgot-password">) {
  const { dict } = await loadLocale(params);

  return (
    <>
      <h1 className="mb-5 text-xl font-semibold">{dict.auth.forgotTitle}</h1>
      <ForgotPasswordForm />
    </>
  );
}
