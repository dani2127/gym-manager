# Architecture & Master Plan

The permanent reference for the multi-branch, SaaS-ready gym platform. Update
this file whenever a decision changes. It supersedes the legacy GYM One PHP app,
which is kept read-only in [`legacy/`](legacy/) for porting reference.

---

## 1. Product scope

| Area | Decision |
|---|---|
| Tenancy | SaaS-ready: many **organizations** (gym owners), each with many **branches**. All data scoped by `organization_id`, and by `branch_id` where it belongs to a branch. |
| Clients | API-first. Next.js web app serves the public site, a mobile-first **member PWA**, staff workspaces and the trainer portal. A React Native / Expo app can follow on the same API. |
| Memberships | Prepaid passes: time-based, visit-based, or both. Optional (admin toggle) **freezing/pausing**. Registration fees, student / couple / family / corporate / promo discounts. |
| Money | **ETB**. Stored as integer santim (1 ETB = 100). Admin toggle for **15% VAT / TOT** on invoices; prices inclusive or exclusive of tax. |
| Payments | Cash and desk POS. **Manual bank transfer / Telebirr**: member uploads a screenshot and FT / transaction number, staff approve. **Chapa** as an optional online gateway. |
| Check-in | Search or **QR scan**. **Live desk pop-up** (photo, name, status, days/visits left) pushed over WebSockets. Optional locker assignment. |
| Classes & trainers | Members book group classes from their phone (capacity, waitlist). Trainers have restricted portals: their classes, PT clients, attendance. |
| Notifications | Each member chooses a **preferred channel: Telegram bot or WhatsApp** (SMS and email as fallbacks). |
| Languages | **English (`en`) and Amharic (`am`)** only. |

---

## 2. Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | **Laravel 13** (PHP 8.3+), REST under `/api/v1` | `apps/api` |
| Auth | **Sanctum**: cookie sessions for the web app, bearer tokens for mobile | `statefulApi()` enabled |
| Real time | **Laravel Reverb** | Private channel `branch.{id}.desk` for check-in pop-ups |
| Jobs | Redis queues (Horizon later) | Notifications, PDFs, Chapa webhooks |
| Scheduler | `schedule:work` container | Expiries, unfreezes, class generation, reminders |
| Database | **MySQL 8.4**, utf8mb4 | |
| Cache / sessions | **Redis 7** | |
| Files | Local disk volume, S3-compatible later | Photos, payment screenshots (private, signed URLs) |
| Frontend | **Next.js 16** (App Router, TypeScript, Tailwind 4, Cache Components) | `apps/web` |
| Edge | **Caddy 2** | Automatic HTTPS, host-based routing |

### Repository layout

```
apps/
  api/        Laravel API (+ Dockerfile used for app, queue, scheduler, reverb)
  web/        Next.js: public site, member PWA, desk, admin, trainer
docker/
  caddy/      Edge proxy config
legacy/       Original GYM One PHP app (reference only, removed after M13)
docker-compose.yml   Single-VPS production stack
.env.example         Production / compose configuration template
```

Later: `packages/api-client` (TypeScript client generated from the OpenAPI
spec) and `apps/mobile` (Expo) sharing translations and the client.

### Runtime topology (single VPS)

```
                 ┌──────────── Caddy (80/443, auto-TLS) ────────────┐
 browser ──HTTPS─┤ {domain}      → web:3000     (Next.js)           │
                 │ api.{domain}  → api:8000     (Laravel/FrankenPHP)│
                 │ ws.{domain}   → reverb:8080  (WebSockets)        │
                 └──────────────────────────────────────────────────┘
 api ─┬─ mysql:3306      queue ─┐
      ├─ redis:6379   scheduler ┼─ same image as api, different command
      └─ reverb (broadcast)     ┘
 Chapa ──webhook──▶ api.{domain}/api/v1/webhooks/chapa
 Telegram ─webhook─▶ api.{domain}/api/v1/webhooks/telegram
 Meta WhatsApp ─webhook─▶ api.{domain}/api/v1/webhooks/whatsapp
```

Reverb gets its own host because its WebSocket path (`/app/{key}`) would
collide with the member PWA at `/app`. Session cookies use
`SESSION_DOMAIN=.{domain}` so the web app and API share Sanctum auth.
Tenant subdomains (`{gym}.{domain}`) and custom domains come later.

### Frontend sections

| Path | Audience | Notes |
|---|---|---|
| `/` | Public | Gym marketing pages, server-rendered for SEO |
| `/app` | Members | Installable PWA (manifest scope `/app/`, service worker scoped to `/app/`) |
| `/desk` | Receptionists | Check-in, live pop-ups, desk sales, payment approvals |
| `/admin` | Owners / managers | Branches, staff, plans, pricing, tax, reports |
| `/trainer` | Trainers | Classes, PT clients, attendance |

### Localization

- Web: pages live under `app/[lang]` and are prerendered for `en` and `am`.
  `src/proxy.ts` keeps URLs clean (`/app`, not `/en/app`) by rewriting to the
  locale from the `NEXT_LOCALE` cookie, then `Accept-Language`, then `en`.
  Explicit `/am/...` URLs also work. Strings live in
  `src/i18n/dictionaries/{en,am}.json`; Ethiopic glyphs use Noto Sans Ethiopic.
- API: `SetLocale` middleware reads `X-Locale` (then `Accept-Language`),
  limited to `en`/`am`, and sets `Content-Language`. Strings in `lang/{en,am}`.
- Translatable database content is JSON: `{"en": "...", "am": "..."}`.
- Dates are stored Gregorian in UTC and shown in `Africa/Addis_Ababa`.
  An Ethiopian-calendar display option is an open question.

---

## 3. Schema conventions

- Primary keys `BIGINT UNSIGNED` auto-increment; anything exposed in a URL or
  QR code also has a random **`ulid`**.
- **Money is `BIGINT` santim**, never floats. Currency column defaults to `ETB`.
- **Snapshot pricing**: memberships and invoice items copy name/price/tax at
  the moment of sale.
- Every tenant table has `organization_id NOT NULL` (FK + index); branch data
  also has `branch_id`. Eloquent global scopes enforce the organization, and
  policies enforce branch access.
- Composite indexes lead with `organization_id` / `branch_id`.
- FKs: `RESTRICT` on financial records, `CASCADE` on pure child rows; soft
  deletes on master data (members, plans, products).

## 4. Master database schema

### A. Tenancy & settings
```
organizations   id · ulid · name · slug UQ · status · timezone · default_locale(en|am) · currency(ETB) · tin_number · plan_tier
branches        id · organization_id FK · name · code · phone · address · city · capacity · status
                UQ(organization_id, code)
settings        id · organization_id FK · branch_id FK NULL · key · value JSON
                UQ(organization_id, branch_id, key)
                ↳ freeze.enabled, lockers.enabled, tax.enabled, tax.prices_inclusive, chapa.enabled,
                  booking.window_hours, booking.cancel_cutoff_hours … (secrets encrypted)
opening_hours   id · branch_id FK · weekday(0-6) · opens_at · closes_at · is_closed       UQ(branch_id, weekday)
branch_closures id · branch_id FK · date · opens_at NULL · closes_at NULL · reason         UQ(branch_id, date)
```

### B. Identity & roles
```
users        id · ulid · organization_id FK NULL(platform admin) · account_type(owner|staff|member)
             · name · phone(E.164) · phone_verified_at · email NULL · password · locale(en|am)
             · photo_path · status(active|disabled) · last_login_at
             UQ(organization_id, phone) · UQ(organization_id, email) · IDX(organization_id, account_type, status)
branch_user  branch_id FK · user_id FK · role(manager|receptionist|trainer)    PK(branch_id, user_id)
audit_logs   id · organization_id · branch_id NULL · user_id NULL · action · subject_type · subject_id
             · changes JSON · ip · created_at       IDX(organization_id, subject_type, subject_id)
otp_codes    see §7 (Telegram one-time codes)
```

**Roles (as built in M1).** `owner` is an organization-wide account type with
access to every branch. Branch staff are `account_type = staff` with one role
per branch in `branch_user`: a person can be a trainer in one branch and a
receptionist in another. Laravel policies (`BranchPolicy`, `UserPolicy`)
implement the rules; spatie/laravel-permission was not needed for four fixed
roles and can be added if custom roles are ever required.

| Action | Owner | Manager | Receptionist | Trainer |
|---|---|---|---|---|
| See branches | all | assigned | assigned | assigned |
| Create branches, change branch status | ✓ | | | |
| Edit branch details, hours, closures | ✓ | own branches | | |
| Tax / feature settings (org and branch) | ✓ | read | read | read |
| Manage staff | everyone | receptionists & trainers in own branches | | |
| Activity log | ✓ | | | |

**Tenant isolation.** `ResolveTenant` middleware sets the organization from the
signed-in user before route-model binding, and the `BelongsToOrganization`
global scope filters every tenant model, so another gym's records resolve to
404. Code that runs without a signed-in user (login, Telegram webhook, queued
jobs) filters by organization explicitly.

### C. Members
```
members        id · ulid · organization_id FK · home_branch_id FK · user_id FK NULL UQ · member_no
               · first_name · last_name · gender NULL · date_of_birth NULL · phone NULL · email NULL · photo_path NULL
               · emergency_name · emergency_phone · status(active|inactive|banned) · qr_secret(encrypted)
               · joined_at · notes · created_by FK NULL · timestamps · deleted_at
               UQ(organization_id, member_no) · IDX(organization_id, phone) · IDX(organization_id, first_name)
               · IDX(organization_id, last_name) · IDX(organization_id, home_branch_id, status)
member_groups         id · ulid · organization_id FK · type(couple|family|corporate) · name · primary_member_id FK NULL
member_group_members  member_group_id FK · member_id FK UQ        PK(member_group_id, member_id)
organizations.next_member_no   per-gym counter, row-locked when a member is registered
```

**As built in M2.**
- Member numbers are sequential per gym (`0001`, shown on the bib) and never reused, even after a delete.
- Phone is not unique: families share phones. A repeated number is a warning the desk confirms (`phone_duplicate`), re-checked only when the number changes.
- A member belongs to at most one group, so group discounts in M3 stay unambiguous. Sizes: couple 2, family 2–12, company 2–500.
- Any staff member can find and view members from every branch (the desk serves visitors from other branches). Registering and editing needs the owner or a manager/receptionist of the member's home branch; deleting (soft) is owners and managers.
- Photos are centre-cropped to 512px JPEG (camera metadata stripped), kept on the private disk and served only through the authorized `/members/{id}/photo` endpoint.
- Search: each word prefixes the first or father's name; digits match the member number exactly or, with five or more significant digits, the phone number.

### D. Catalog, pricing & tax
```
plans          id · ulid · organization_id FK · name JSON · type(time|visits|time_visits)
               · duration_days NULL · visit_count NULL · price_cents
               · access_scope(home_branch|all_branches|selected) · freeze_allowed · max_freeze_days
               · max_freezes · class_credits NULL(NULL = unlimited) · is_active · sort
plan_branch    plan_id FK · branch_id FK                   PK(plan_id, branch_id)
fees           id · organization_id FK · name JSON · kind(registration|card_replacement|other) · amount_cents · is_active
discount_rules id · organization_id FK · name JSON · kind(student|couple|family|corporate|promo)
               · value_type(percent|fixed) · value · min_group_size NULL · requires_proof · promo_code NULL
               · plan_id FK NULL · valid_from · valid_until · is_active      UQ(organization_id, promo_code)
tax_rates      id · organization_id FK · name(VAT|TOT) · rate DECIMAL(5,2) · is_active
```

### E. Memberships
```
memberships         id · ulid · organization_id FK · branch_id FK(sold at) · member_id FK · plan_id FK
                    · plan_snapshot JSON · starts_on · ends_on NULL · visits_total NULL · visits_remaining NULL
                    · status(pending_payment|active|frozen|expired|cancelled) · invoice_id FK NULL · sold_by FK NULL
                    IDX(member_id, status, ends_on) · IDX(organization_id, branch_id, status, ends_on)
membership_freezes  id · membership_id FK · starts_on · ends_on · days · reason
                    · status(requested|approved|rejected|active|completed) · requested_by · approved_by
```

### F. Billing & payments
```
invoice_sequences    branch_id FK · year · next_number     PK(branch_id, year)  (row-locked, gap-free)
invoices             id · ulid · organization_id FK · branch_id FK · member_id FK NULL · number
                     · status(draft|issued|partially_paid|paid|void|refunded)
                     · subtotal_cents · discount_cents · tax_cents · total_cents · tax_name · tax_rate
                     · tax_inclusive · currency · issued_at · created_by
                     UQ(branch_id, number) · IDX(organization_id, branch_id, status, issued_at)
invoice_items        id · invoice_id FK CASCADE · itemable_type · itemable_id(plan|fee|product|pt_package)
                     · description · qty · unit_price_cents · discount_cents · discount_rule_id NULL · tax_cents · total_cents
payments             id · ulid · organization_id FK · branch_id FK · invoice_id FK · member_id FK NULL
                     · method(cash|pos_card|bank_transfer|telebirr|chapa) · amount_cents
                     · status(pending|awaiting_verification|completed|rejected|failed|refunded)
                     · reference NULL(FT no. / Telebirr txn / Chapa tx_ref) · proof_path NULL
                     · received_by · verified_by · verified_at · rejection_reason
                     UQ(organization_id, method, reference)  ← blocks reused FT / Telebirr numbers
                     IDX(organization_id, branch_id, status, created_at)
gateway_transactions id · payment_id FK · provider(chapa) · tx_ref UQ · checkout_url · status
                     · request JSON · webhook_payload JSON · verified_at
refunds              id · payment_id FK · amount_cents · reason · refunded_by · created_at
```

### G. Check-in & lockers
```
lockers        id · branch_id FK · number · zone(male|female|unisex) · status(available|out_of_order)   UQ(branch_id, number)
check_ins      id · organization_id FK · branch_id FK · member_id FK · membership_id FK NULL
               · method(qr|search|kiosk|manual) · result(allowed|denied|overridden) · denial_reason NULL
               · locker_id FK NULL · checked_in_at · checked_out_at NULL · handled_by NULL
               IDX(branch_id, checked_in_at) · IDX(member_id, checked_in_at) · IDX(branch_id, checked_out_at)
kiosk_devices  id · branch_id FK · name · token_hash · last_seen_at
```
"Currently inside" = `checked_out_at IS NULL`. Replaces legacy
`temp_loggeduser` and `workout_stats`.

### H. Classes
```
class_types     id · organization_id FK · name JSON · description JSON · color · default_duration_min · default_capacity
rooms           id · branch_id FK · name · capacity
class_schedules id · branch_id FK · class_type_id FK · trainer_id FK · room_id FK NULL · weekday · start_time
                · duration_min · capacity · valid_from · valid_until NULL         (recurring template)
class_sessions  id · ulid · branch_id FK · class_type_id FK · schedule_id FK NULL · trainer_id FK · room_id FK NULL
                · starts_at · ends_at · capacity · booked_count · status(scheduled|cancelled|completed)
                IDX(branch_id, starts_at) · IDX(trainer_id, starts_at)
class_bookings  id · class_session_id FK · member_id FK · membership_id FK
                · status(booked|waitlisted|cancelled|attended|no_show) · waitlist_position NULL · booked_at · cancelled_at
                UQ(class_session_id, member_id) · IDX(member_id, status)
```
A nightly job materializes schedules into sessions for the next N weeks;
booking locks the session row to prevent overbooking.

### I. Trainers & personal training
```
trainers        id · organization_id FK · user_id FK UQ · bio JSON · specialties JSON · photo_path · is_public · status
trainer_branch  trainer_id FK · branch_id FK                 PK(trainer_id, branch_id)
pt_packages     id · organization_id FK · trainer_id FK NULL · name JSON · sessions_count · price_cents · validity_days
pt_enrollments  id · member_id FK · trainer_id FK · pt_package_id FK · invoice_id FK · sessions_total
                · sessions_remaining · starts_on · ends_on · status
pt_sessions     id · pt_enrollment_id FK · trainer_id FK · member_id FK · branch_id FK · starts_at · ends_at
                · status(scheduled|completed|cancelled|no_show) · trainer_notes(private)     IDX(trainer_id, starts_at)
```
Trainer policies only expose rows linked to their own `trainer_id`.

### J. POS & inventory (ported from legacy)
```
products         id · organization_id FK · sku · barcode NULL · name JSON · price_cents · is_active   UQ(organization_id, barcode)
product_stocks   product_id FK · branch_id FK · quantity       PK(product_id, branch_id)
stock_movements  id · product_id FK · branch_id FK · change · reason(sale|restock|adjust|transfer) · reference · user_id
```

### K. Notifications (Telegram / WhatsApp preferences)
```
notification_preferences  id · organization_id FK · user_id FK
                          · channel(telegram|whatsapp|sms|email|push) · destination
                            (telegram chat_id | E.164 phone | email) · is_preferred · verified_at NULL · opted_out_at NULL
                          UQ(user_id, channel) · IDX(organization_id, channel)
                          (at most one is_preferred = true per user, enforced in the service layer)
telegram_link_tokens      id · user_id FK · token_hash UQ · expires_at · used_at NULL
message_logs              id · organization_id FK · user_id FK NULL · channel · template · locale
                          · status(queued|sent|delivered|read|failed) · provider_message_id NULL · error NULL · created_at
                          IDX(organization_id, created_at) · IDX(user_id, created_at)
notifications             Laravel default table (in-app / PWA notifications)
```
- **Telegram linking**: the PWA opens `https://t.me/{bot}?start={token}`; the
  bot webhook receives `/start {token}`, matches `telegram_link_tokens`, and
  stores the `chat_id` as a verified `telegram` destination.
- **WhatsApp**: Meta WhatsApp Business Cloud API. Business-initiated messages
  (reminders, approvals) must use pre-approved templates in `en` and `am`.
  The number is verified with a one-time code before use.
- **Routing**: a `NotificationRouter` sends through the member's preferred
  verified channel and falls back to SMS, then email, on failure. Every attempt
  is written to `message_logs`.
- M0 ships the PWA toggle UI (stored on the device); M5 persists it through
  the API.

---

## 5. Key flows

**QR check-in with the live desk pop-up**
1. The member PWA renders a **rotating QR** (HMAC of `qr_secret` and a 30–60 s
   time window). It works offline, and screenshots expire.
2. A desk webcam, USB scanner or entrance kiosk posts it to
   `POST /api/v1/branches/{branch}/check-ins/scan`.
3. In one transaction: validate the token → active, unfrozen membership →
   opening hours → not already inside → decrement visits → optional locker.
4. Reverb broadcasts `CheckInScanned` on `branch.{id}.desk`. The desk pops up
   the photo, name, green/amber/red status and days/visits left. Denials offer
   a logged override.

**Manual Telebirr / bank transfer**: the member picks a plan → invoice
`pending_payment` → uploads a screenshot and FT/transaction number → payment
`awaiting_verification` in the desk queue → approve (membership activates,
notification sent on the preferred channel) or reject with a reason. The
unique `reference` index blocks reused transfers.

**Chapa**: initialize with `tx_ref` = payment ULID → hosted checkout → signed
webhook plus server-side verify → idempotent completion → membership activates.
v1 renewals are expiry reminders with a one-tap Chapa link (recurring card
charges depend on what Chapa supports).

**Tax**: per invoice line from the branch's `tax.enabled`, its `tax_rates`
row and `tax.prices_inclusive`; the rate is snapshotted on the invoice.

---

## 6. Roadmap

| # | Milestone | Exit criteria | Status |
|---|---|---|---|
| **M0** | Foundation: monorepo, Laravel API, Next.js sections, en/am i18n, Docker stack, env templates, CI | API + web build and run; CI green | ✅ Done |
| **M1** | Tenancy, auth & roles: organizations, branches, users, `branch_user`, policies, branch switcher, audit log, Telegram linking + OTP password reset | Owner creates 2 branches; staff see only their branch | ✅ Done |
| **M2** | Members: CRUD, photos, member numbers, groups, search | Desk registers and finds members quickly | ✅ Done |
| **M3** | Plans, fees, discounts, tax engine, invoices, cash/POS payments, Amharic PDF receipts, activation | Desk sale yields a correct invoice with VAT on/off | |
| **M4** | Check-in & live desk: rotating QR, scan endpoint, Reverb pop-up, lockers toggle, occupancy, kiosk mode | Scan shows the desk pop-up in under 1 s | |
| **M5** | Member PWA v1: login, QR card, status, invoices, install prompt, offline QR, **notification preference saved via API** | Installs on Android/iOS; QR works offline | |
| **M6** | Manual Telebirr / bank verification queue | Upload → approve → active; duplicate FT blocked | |
| — | **MVP line: one real branch can run on M0–M6** | Pilot at one branch | |
| **M7** | Membership freezing (toggle, rules, approvals, auto-unfreeze) | | |
| **M8** | Classes: types, rooms, schedules, sessions, booking, waitlist, attendance | | |
| **M9** | Trainer portal & PT packages / sessions | | |
| **M10** | Chapa integration & renewal links | | |
| **M11** | POS & per-branch inventory (ported) | | |
| **M12** | Reports (revenue by branch/method, VAT, attendance, expiring) + **Telegram bot & WhatsApp delivery**, SMS fallback | | |
| **M13** | Hardening & launch: security review, backups, monitoring, legacy data migration script, production deploy | | |

### Legacy porting map

| Legacy (`legacy/`) | New |
|---|---|
| `users` | `members` + `users` |
| `current_tickets` | `memberships` (matched to `plans` by name) |
| `tickets` | `plans` |
| `workers` | `users` + `branch_user` roles |
| `invoices` | `invoices` (imported as legacy, read-only) |
| `temp_loggeduser`, `workout_stats` | `check_ins` |
| `lockers` | `lockers` |
| `timetable` | `class_schedules` |
| `trainers` | `trainers` |
| `products` | `products` + `product_stocks` |
| `opening_hours`, `opening_hours_exceptions` | `opening_hours`, `branch_closures` |
| `admin/dashboard/process.php` check-in transaction | M4 check-in service |
| `crontab/send_reminders.php` | M12 scheduled reminder job |

---

## 7. Decisions log

| Date | Decision |
|---|---|
| 2026-10-27 | **Login**: phone + password for members and staff. One-time codes (phone verification, password reset) are delivered through the **Telegram bot**; members without a linked Telegram reset at the front desk (SMS OTP can be added later). |
| 2026-10-27 | **Cross-branch access**: decided **per plan** — home branch only, all branches, or selected branches (`plans.access_scope` + `plan_branch`). |
| 2026-10-27 | **VAT pricing**: **configurable per organization** — `tax.prices_inclusive` setting (inclusive by default), overridable per branch. |

Supporting table for Telegram OTP (added to §4B):
```
otp_codes   id · user_id FK · purpose(phone_verify|password_reset) · channel(telegram)
            · code_hash · attempts · expires_at · consumed_at NULL · created_at
            IDX(user_id, purpose, expires_at)   (5-minute expiry, max 5 attempts, rate-limited)
```

## 8. Open decisions

1. ~~Cross-branch access~~ → decided (see log).
2. ~~Member login~~ → decided (see log).
3. Tax: ~~inclusive vs exclusive~~ decided (configurable). Still open: applicable TOT rate, and whether Ministry of Revenue fiscal-printer integration is required. (Confirm with an accountant.)
4. Keep the legacy prepaid wallet balance?
5. Show Ethiopian-calendar dates alongside Gregorian?
6. **Licensing**: the legacy code is under the GYM One License (`legacy/LICENSE`), which restricts rebranding and sublicensing. Decide a license for the new code, and keep ported logic clean-room or attributed.
