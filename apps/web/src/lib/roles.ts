import type { BranchRole, Me } from "@/lib/types";

/** Which staff workspaces a user may open. Mirrors the API's policies. */
export function workspaces(me: Me) {
  const owner = me.user.account_type === "owner";
  const roles = new Set<BranchRole>(me.branches.flatMap((b) => (b.role ? [b.role] : [])));

  return {
    desk: owner || roles.has("manager") || roles.has("receptionist"),
    trainer: owner || roles.has("trainer"),
    admin: me.abilities.manage_staff || me.abilities.manage_branches,
  };
}

/** Landing page after sign-in. */
export function homeFor(me: Me): string {
  const access = workspaces(me);
  if (access.admin) return "/admin";
  if (access.desk) return "/desk";
  if (access.trainer) return "/trainer";
  return "/account";
}
