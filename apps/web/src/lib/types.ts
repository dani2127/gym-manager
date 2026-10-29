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

export type Translations = { en: string; am: string | null };

export type PlanType = "time" | "visits" | "time_visits";
export type AccessScope = "home_branch" | "all_branches" | "selected";

export type Plan = {
  id: string;
  name: string;
  name_translations: Translations;
  description: string | null;
  description_translations: Translations | null;
  type: PlanType;
  duration_days: number | null;
  visit_count: number | null;
  price_cents: number;
  access_scope: AccessScope;
  branches?: { id: string; name: string }[];
  freeze_allowed: boolean;
  max_freeze_days: number | null;
  max_freezes: number | null;
  class_credits: number | null;
  is_active: boolean;
  sort: number;
};

export type FeeKind = "registration" | "card_replacement" | "other";

export type Fee = {
  id: string;
  name: string;
  name_translations: Translations;
  kind: FeeKind;
  amount_cents: number;
  is_active: boolean;
};

export type DiscountKind = "student" | "couple" | "family" | "corporate" | "promo";

export type DiscountRule = {
  id: string;
  name: string;
  name_translations: Translations;
  kind: DiscountKind;
  value_type: "percent" | "fixed";
  value: number;
  min_group_size: number | null;
  requires_proof: boolean;
  promo_code: string | null;
  plan: { id: string; name: string } | null;
  valid_from: string | null;
  valid_until: string | null;
  is_active: boolean;
};

export type QuoteDiscount = {
  id: string;
  name: string;
  kind: DiscountKind;
  value_type: "percent" | "fixed";
  value: number;
  amount_cents: number;
  requires_proof: boolean;
  automatic: boolean;
};

export type TaxInfo = { enabled: boolean; name: "VAT" | "TOT"; rate: number; inclusive: boolean };

export type Quote = {
  lines: { kind: "plan" | "fee"; id: string; description: string; unit_price_cents: number; discount_cents: number; tax_cents: number; total_cents: number }[];
  subtotal_cents: number;
  discount_cents: number;
  tax_cents: number;
  net_cents: number;
  total_cents: number;
  tax: TaxInfo;
  discount: QuoteDiscount | null;
  starts_on: string | null;
  ends_on: string | null;
  suggested_start: string;
  suggested_fees: string[];
  available_discounts: QuoteDiscount[];
};

export type PaymentMethod = "cash" | "pos_card" | "bank_transfer" | "telebirr" | "chapa";
export type InvoiceStatus = "issued" | "partially_paid" | "paid" | "void";
export type MembershipState = "upcoming" | "current" | "expired" | "pending_payment" | "frozen" | "cancelled" | "active";

export type Membership = {
  id: string;
  plan: { id: string; name: string; type: PlanType; access_scope: AccessScope };
  status: "pending_payment" | "active" | "frozen" | "expired" | "cancelled";
  state: MembershipState;
  starts_on: string;
  ends_on: string | null;
  days_left: number | null;
  visits_total: number | null;
  visits_remaining: number | null;
  branch?: { id: string; name: string };
  invoice?: { id: string; number: string; status: InvoiceStatus; balance_cents: number } | null;
  created_at: string;
};

export type Invoice = {
  id: string;
  number: string;
  status: InvoiceStatus;
  subtotal_cents: number;
  discount_cents: number;
  tax_cents: number;
  net_cents: number;
  total_cents: number;
  paid_cents: number;
  balance_cents: number;
  tax_name: "VAT" | "TOT" | null;
  tax_rate: number;
  tax_inclusive: boolean;
  currency: string;
  issued_at: string;
  branch?: { id: string; name: string };
  member?: { id: string; full_name: string; member_no: string } | null;
  created_by?: string | null;
  items?: { description: string; kind: string | null; qty: number; unit_price_cents: number; discount_cents: number; discount: { kind: DiscountKind; name: string } | null; tax_cents: number; total_cents: number }[];
  payments?: { id: string; method: PaymentMethod; amount_cents: number; status: string; reference: string | null; rejection_reason?: string | null; received_by: string | null; created_at: string }[];
  memberships?: Membership[];
  void_reason: string | null;
  voided_at: string | null;
};

export type Takings = {
  date: string;
  total_cents: number;
  by_method: { method: PaymentMethod; amount_cents: number; count: number }[];
  refunded_cents: number;
  invoices_count: number;
  unpaid_cents: number;
};

export type CheckInResult = "allowed" | "denied" | "overridden" | "already_inside";

export type CheckIn = {
  id: string | null;
  result: CheckInResult;
  reason: string | null;
  overridable: boolean;
  override_reason?: string | null;
  method?: "qr" | "card" | "search" | "kiosk" | "manual";
  checked_in_at?: string;
  checked_out_at?: string | null;
  branch?: { id: string; name: string };
  member: {
    id: string;
    member_no: string;
    full_name: string;
    first_name: string;
    last_name: string;
    status: MemberStatus;
    photo_url: string | null;
    home_branch: string | null;
  } | null;
  membership: {
    id: string;
    plan: string;
    status: string;
    state: MembershipState;
    starts_on: string;
    ends_on: string | null;
    days_left: number | null;
    visits_total: number | null;
    visits_remaining: number | null;
  } | null;
  handled_by?: string | null;
  locker?: { id: number; number: string; zone: LockerZone } | null;
};

export type LockerZone = "male" | "female" | "unisex";

export type Locker = {
  id: number;
  number: string;
  zone: LockerZone;
  status: "available" | "out_of_order";
  in_use_by: { check_in: string; member: NonNullable<CheckIn["member"]> } | null;
};

export type Kiosk = {
  id: string;
  name: string;
  status: "paired" | "pairing" | "unpaired";
  paired_at: string | null;
  pairing_expires_at: string | null;
  last_seen_at: string | null;
  pairing_code?: string;
};

export type CheckInList = {
  data: CheckIn[];
  meta: { inside: number; capacity: number | null; visits_today: number; denied_today: number };
};

export type MemberHome = {
  member: NonNullable<CheckIn["member"]> & { phone: string | null; joined_at: string | null };
  organization: { name: string; slug: string };
  current: Membership | null;
  upcoming: Membership[];
  owed_cents: number;
  open_order: { id: string; number: string; balance_cents: number; transfer: { status: string; rejection_reason: string | null } | null } | null;
};

export type PaymentAccount = {
  id: string;
  kind: "telebirr" | "bank";
  bank_name: string | null;
  account_name: string;
  account_number: string;
  is_active: boolean;
};

export type TransferReview = {
  id: string;
  method: "telebirr" | "bank_transfer";
  amount_cents: number;
  reference: string;
  status: "awaiting_verification" | "completed" | "rejected";
  rejection_reason: string | null;
  submitted_at: string;
  verified_at: string | null;
  verified_by: string | null;
  proof_url: string | null;
  branch: { id: string; name: string };
  member: NonNullable<CheckIn["member"]> | null;
  invoice: { id: string; number: string; total_cents: number; balance_cents: number; status: InvoiceStatus };
};
