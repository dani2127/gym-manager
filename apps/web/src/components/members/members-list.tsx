"use client";

import { Plus, Search } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import useSWRInfinite from "swr/infinite";

import { MemberAvatar, StatusBadge } from "@/components/members/member-avatar";
import { useDebounced } from "@/components/members/use-debounced";
import { Button, PageHeader, Select } from "@/components/ui";
import { format, useDictionary } from "@/i18n/dictionary-context";
import { swrFetcher } from "@/lib/api";
import { formatDate, formatPhone } from "@/lib/format";
import { canRegisterMembers } from "@/lib/roles";
import { useSession } from "@/lib/session";
import type { Member, MemberStatus, Paginated } from "@/lib/types";

export function MembersList() {
  const { locale, dict } = useDictionary();
  const { me } = useSession();
  const t = dict.members;
  const [query, setQuery] = useState("");
  const [branch, setBranch] = useState("");
  const [status, setStatus] = useState<"" | MemberStatus>("");
  const search = useDebounced(query.trim());

  const params = new URLSearchParams({ per_page: "25", ...(search && { search }), ...(branch && { branch }), ...(status && { status }) });
  const { data, size, setSize, isLoading, isValidating } = useSWRInfinite<Paginated<Member>>(
    (index, previous) => (previous && previous.meta.current_page >= previous.meta.last_page ? null : `/members?${params}&page=${index + 1}`),
    swrFetcher,
    { keepPreviousData: true },
  );

  const members = data?.flatMap((page) => page.data) ?? [];
  const total = data?.[0]?.meta.total ?? 0;
  const hasMore = data ? data[data.length - 1].meta.current_page < data[data.length - 1].meta.last_page : false;

  return (
    <>
      <PageHeader
        title={t.title}
        description={data ? format(t.count, { count: String(total) }) : undefined}
        actions={
          canRegisterMembers(me) && (
            <Link href="/members/new" className="inline-flex min-h-10 items-center gap-2 rounded-[6px] bg-track px-4 text-sm font-semibold text-lane hover:bg-track-deep">
              <Plus aria-hidden className="h-4 w-4" />
              {t.new}
            </Link>
          )
        }
      />

      <div className="mb-4 grid gap-2 sm:grid-cols-[1fr_12rem_10rem]">
        <label className="relative">
          <span className="sr-only">{t.searchLabel}</span>
          <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-graphite" />
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t.searchPlaceholder}
            autoFocus
            className="h-11 w-full rounded-[6px] border border-line-strong bg-surface pr-3 pl-9 text-sm outline-none focus:border-ink"
          />
        </label>
        <Select aria-label={t.colBranch} value={branch} onChange={(e) => setBranch(e.target.value)} className="h-11">
          <option value="">{t.allBranches}</option>
          {me.branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
        </Select>
        <Select aria-label={t.colStatus} value={status} onChange={(e) => setStatus(e.target.value as MemberStatus | "")} className="h-11">
          <option value="">{t.allStatuses}</option>
          {(["active", "inactive", "banned"] as const).map((s) => (
            <option key={s} value={s}>
              {t.status[s]}
            </option>
          ))}
        </Select>
      </div>

      {isLoading && !data ? (
        <div className="space-y-2">
          {Array.from({ length: 6 }, (_, i) => (
            <div key={i} className="h-16 animate-pulse rounded-[6px] bg-surface" />
          ))}
        </div>
      ) : members.length === 0 ? (
        <div className="rounded-[6px] border border-dashed border-line-strong bg-surface px-6 py-12 text-center text-graphite">
          {search ? format(t.noResults, { query: search }) : t.empty}
        </div>
      ) : (
        <div className={`overflow-hidden rounded-[6px] border border-line bg-surface transition-opacity ${isValidating ? "opacity-70" : ""}`}>
          <table className="w-full text-left text-sm">
            <thead className="hidden border-b border-line bg-surface-2 text-xs text-graphite md:table-header-group">
              <tr>
                <th className="px-4 py-2.5 font-semibold">{t.colMember}</th>
                <th className="px-4 py-2.5 font-semibold">{t.colPhone}</th>
                <th className="px-4 py-2.5 font-semibold">{t.colBranch}</th>
                <th className="px-4 py-2.5 font-semibold">{t.colJoined}</th>
                <th className="px-4 py-2.5 font-semibold">{t.colStatus}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {members.map((member) => (
                <tr key={member.id} className="group relative hover:bg-surface-2">
                  <td className="px-4 py-3">
                    <Link href={`/members/${member.id}`} className="flex items-center gap-3 after:absolute after:inset-0">
                      <MemberAvatar member={member} size="sm" />
                      <span className="min-w-0">
                        <span className="block truncate font-semibold">{member.full_name}</span>
                        <span className="tabular block text-xs text-graphite">
                          #{member.member_no}
                          <span className="md:hidden"> · {formatPhone(member.phone)}</span>
                        </span>
                      </span>
                    </Link>
                  </td>
                  <td className="tabular hidden px-4 py-3 md:table-cell">{formatPhone(member.phone)}</td>
                  <td className="hidden px-4 py-3 md:table-cell">{member.home_branch?.name}</td>
                  <td className="hidden px-4 py-3 text-graphite md:table-cell">{formatDate(member.joined_at, locale)}</td>
                  <td className="px-4 py-3 text-right md:text-left">
                    <StatusBadge status={member.status} label={t.status[member.status]} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {hasMore && (
        <div className="mt-4 text-center">
          <Button variant="secondary" busy={isValidating && size > (data?.length ?? 0)} onClick={() => setSize(size + 1)}>
            {t.showMore}
          </Button>
        </div>
      )}
    </>
  );
}
