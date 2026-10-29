"use client";

import type { ReactNode } from "react";

import { Alert } from "@/components/ui";
import { useDictionary } from "@/i18n/dictionary-context";
import { workspaces } from "@/lib/roles";
import { useSession } from "@/lib/session";

/** Guards /admin; its pages are listed in the console sidebar. */
export function AdminSection({ children }: { children: ReactNode }) {
  const { dict } = useDictionary();
  const { me } = useSession();

  if (!workspaces(me).admin) return <Alert tone="error">{dict.common.notAllowed}</Alert>;

  return <>{children}</>;
}
