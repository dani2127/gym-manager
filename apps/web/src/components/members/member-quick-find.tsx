"use client";

import { Search } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import useSWR from "swr";

import { MemberAvatar, StatusBadge } from "@/components/members/member-avatar";
import { useDebounced } from "@/components/members/use-debounced";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatPhone } from "@/lib/format";
import type { Member, Paginated } from "@/lib/types";

/** Desk search: type a name, phone or bib number, open the member. */
export function MemberQuickFind() {
  const { dict } = useDictionary();
  const t = dict.members;
  const [query, setQuery] = useState("");
  const search = useDebounced(query.trim(), 200);
  const { data, isLoading } = useSWR<Paginated<Member>>(search ? `/members?per_page=8&search=${encodeURIComponent(search)}` : null, swrFetcher, { keepPreviousData: true });

  return (
    <div>
      <label className="relative block">
        <span className="sr-only">{t.searchLabel}</span>
        <Search aria-hidden className="pointer-events-none absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-graphite" />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t.searchPlaceholder}
          autoFocus
          className="h-14 w-full rounded-[6px] border-2 border-line-strong bg-surface pr-4 pl-12 text-lg outline-none focus:border-ink"
        />
      </label>
      {search && (
        <ul className={`mt-2 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-surface ${isLoading ? "opacity-70" : ""}`} aria-live="polite">
          {data?.data.length === 0 && <li className="px-4 py-6 text-center text-sm text-graphite">{format(t.noResults, { query: search })}</li>}
          {data?.data.map((member) => (
            <li key={member.id}>
              <Link href={`/members/${member.id}`} className="flex items-center gap-4 px-4 py-3 hover:bg-surface-2">
                <MemberAvatar member={member} />
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-semibold">{member.full_name}</span>
                  <span className="tabular block text-sm text-graphite">
                    #{member.member_no} · {formatPhone(member.phone)} · {member.home_branch?.name}
                  </span>
                </span>
                <StatusBadge status={member.status} label={t.status[member.status]} />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
