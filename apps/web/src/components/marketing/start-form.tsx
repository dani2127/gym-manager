"use client";

import { ArrowRight } from "lucide-react";
import { useRouter } from "next/navigation";
import { useId, type FormEvent } from "react";

/** The landing page's primary action, in working form: name the gym, continue to sign-up. */
export function StartForm({ label, placeholder, cta, note }: { label: string; placeholder: string; cta: string; note: string }) {
  const router = useRouter();
  const id = useId();

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const name = String(new FormData(event.currentTarget).get("name") ?? "").trim();
    router.push(name ? `/register?name=${encodeURIComponent(name)}` : "/register");
  }

  return (
    <form onSubmit={submit} className="max-w-xl">
      <label htmlFor={id} className="block text-sm font-semibold">
        {label}
      </label>
      <div className="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]">
        <input
          id={id}
          name="name"
          placeholder={placeholder}
          autoComplete="organization"
          className="h-12 w-full rounded-[6px] border border-line-strong bg-surface px-4 text-base outline-none placeholder:text-graphite/70 focus:border-ink"
        />
        <button
          type="submit"
          className="inline-flex h-12 items-center justify-center gap-2 rounded-[6px] bg-track px-6 text-base font-semibold text-lane transition-colors duration-150 hover:bg-track-deep"
        >
          {cta}
          <ArrowRight aria-hidden className="h-4 w-4" />
        </button>
      </div>
      <p className="mt-2 text-sm text-graphite">{note}</p>
    </form>
  );
}
