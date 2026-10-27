/** Response shapes of the Laravel API (apps/api/app/Http/Resources). */

export type BranchRole = "manager" | "receptionist" | "trainer";

export type OpeningHour = {
  weekday: number;
  opens_at: string | null;
  closes_at: string | null;
  is_closed: boolean;
};

export type Branch = {
  id: string;
  name: string;
  code: string;
  phone: string | null;
  address: string | null;
  city: string | null;
  capacity: number | null;
  status: "active" | "inactive";
  role?: BranchRole | null;
  opening_hours?: OpeningHour[];
};

export type Me = {
  user: {
    id: string;
    name: string;
    phone: string;
    phone_verified: boolean;
    email: string | null;
    locale: "en" | "am";
    account_type: "owner" | "staff" | "member";
    telegram_linked: boolean;
  };
  organization: { id: string; name: string; slug: string; currency: string };
  branches: Branch[];
  abilities: {
    manage_branches: boolean;
    manage_settings: boolean;
    manage_staff: boolean;
    view_audit_log: boolean;
  };
};

export type StaffMember = {
  id: string;
  name: string;
  phone: string;
  email: string | null;
  account_type: "owner" | "staff";
  status: "active" | "disabled";
  last_login_at: string | null;
  assignments: { branch: { id: string; name: string }; role: BranchRole }[];
};

export type Settings = {
  "tax.enabled": boolean;
  "tax.name": "VAT" | "TOT";
  "tax.rate": number;
  "tax.prices_inclusive": boolean;
  "freeze.enabled": boolean;
  "lockers.enabled": boolean;
  "chapa.enabled": boolean;
};

export type AuditEntry = {
  id: number;
  action: string;
  subject_type: string | null;
  changes: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
  user: { id: string; name: string } | null;
  branch: { id: string; name: string } | null;
};

export type MemberStatus = "active" | "inactive" | "banned";

export type MemberGroup = {
  id: string;
  type: "couple" | "family" | "corporate";
  name: string;
  primary_member_id: string | null;
  members: { id: string; full_name: string; member_no: string }[];
};

export type Member = {
  id: string;
  member_no: string;
  first_name: string;
  last_name: string;
  full_name: string;
  gender: "male" | "female" | null;
  date_of_birth: string | null;
  phone: string | null;
  email: string | null;
  status: MemberStatus;
  joined_at: string;
  home_branch?: { id: string; name: string };
  photo_url: string | null;
  emergency_name: string | null;
  emergency_phone: string | null;
  notes: string | null;
  group?: MemberGroup | null;
  created_at: string;
};

export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
};
