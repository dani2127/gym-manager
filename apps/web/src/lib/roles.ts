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

/** Mirrors MemberPolicy: owners, or managers and receptionists of the branch. */
export function canRegisterAt(me: Me, branchId: string | undefined): boolean {
  if (me.user.account_type === "owner") return true;
  const role = me.branches.find((b) => b.id === branchId)?.role;
  return role === "manager" || role === "receptionist";
}

export function canRegisterMembers(me: Me): boolean {
  return me.user.account_type === "owner" || me.branches.some((b) => b.role === "manager" || b.role === "receptionist");
}

export function canDeleteMemberAt(me: Me, branchId: string | undefined): boolean {
  return me.user.account_type === "owner" || me.branches.some((b) => b.id === branchId && b.role === "manager");
}

/** Branches where the user may register members (the home-branch choices). */
export function registrableBranches(me: Me) {
  return me.branches.filter((b) => canRegisterAt(me, b.id));
}
