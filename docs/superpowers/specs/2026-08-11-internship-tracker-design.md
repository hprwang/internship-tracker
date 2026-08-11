# InternTrack — Productization Design Spec

**Date:** 2026-08-11
**Status:** Approved for planning

## Purpose

The existing InternTrack codebase works but has grown organically: it spans **three
separate MySQL databases** with duplicated tables, mixes roles inconsistently, and has
known glitches. The goal is to productize it into a single, polished, reliable
internship-tracking website and make it demo-ready for a college submission.

**Scope (all four):** add new features, fix bugs & harden, polish UI/UX, clean up the
codebase. This is a **productize-in-place** effort (Approach A) — not a rewrite.

## Constraints & context

- **Purpose:** college project — must demo well, look polished, be reliable on XAMPP.
- **Current state:** runs but has issues.
- **Stack (unchanged):** PHP 8, MySQL, vanilla JS + Chart.js (CDN), PHPMailer
  (already in `composer.json`), CSS3 (dark theme).
- **Success criteria:** one coherent database; working analytics, calendar/timeline,
  and notification features; all existing flows repaired; a single consistent UI;
  a demo walkthrough (`DEMO.md`) and seed data.

---

## Section 1 — Unified data model (foundation)

**Today:** data lives in three disconnected databases — `internship_tracker1` (students),
`internship_tracker_admin`, `internship_tracker_company` — with duplicated
`users`/`admin_users`, `companies`, and `internships` tables and no foreign keys across
databases. This is the root of most glitches and blocks cross-role analytics.

**Target:** one database, `internship_tracker1`, with proper foreign keys. A one-time
migration script copies existing data across (student records survive intact).

| Current (scattered) | Unified table | Change |
|---|---|---|
| `users` (main) + `admin_users` (admin DB) + `admin_users` (company DB) | `users`, role `student \| company \| admin` | One account per person; admin & company accounts are users with a role + optional `company_id` |
| `companies` (main) + `companies` (company DB) | `companies` | One canonical list |
| `internships` (main, student-tracked) + `internships` (company DB, job postings) | `internships` (student track records) + **`company_internships`** (job postings) | Separate the two meanings of "internship" |
| `applications` (company DB, denormalized) | `applications`, FK-linked to students | Companies see a real applicant profile |
| `progress_logs`, `documents`, `activity_log`, `settings`, `password_resets`, `login_rate_limits` | unchanged | Carried over as-is |
| — | **`notifications`** (new) | In-app notifications + email demo fallback |

### Proposed unified schema

```sql
-- users: one table for all people
id INT PK AUTO_INCREMENT
username VARCHAR(80) UNIQUE
email VARCHAR(150) UNIQUE
password_hash VARCHAR(255)
role ENUM('student','company','admin') DEFAULT 'student'
full_name VARCHAR(150)
company_id INT NULL REFERENCES companies(id) ON DELETE SET NULL  -- for company accounts
is_active TINYINT(1) DEFAULT 1
last_login TIMESTAMP NULL
created_at / updated_at TIMESTAMP

-- companies: canonical list
id, name UNIQUE, industry, description TEXT, website, location,
email, phone, contact_person, logo_url, status ENUM('active','inactive','pending'),
created_at / updated_at

-- internships: student-tracked records (as today)
id, student_id REFERENCES users(id) CASCADE, company_id REFERENCES companies(id) RESTRICT,
title, description TEXT, start_date, end_date,
status ENUM('applied','interview','accepted','ongoing','completed','rejected','withdrawn'),
stipend DECIMAL(10,2), work_mode ENUM('remote','onsite','hybrid'),
offer_letter_path, resume_path, cover_letter_path, transcripts_path, notes TEXT,
created_at / updated_at

-- company_internships: job postings published by companies
id, company_id REFERENCES companies(id) CASCADE,
title, description TEXT, requirements TEXT, location, duration VARCHAR(100),
stipend DECIMAL(10,2), status ENUM('active','closed','pending'),
created_at / updated_at

-- applications: students applying to company_internships
id, company_internship_id REFERENCES company_internships(id) CASCADE,
student_id REFERENCES users(id) CASCADE, cover_letter TEXT, resume TEXT,
status ENUM('pending','under_review','accepted','rejected'),
notes TEXT, applied_at, updated_at

-- progress_logs, documents, activity_log, settings, password_resets, login_rate_limits
--   unchanged (carried over)

-- notifications: NEW
id, user_id REFERENCES users(id) CASCADE,
title VARCHAR(200), message TEXT,
type ENUM('info','warning','error','success') DEFAULT 'info',
channel ENUM('in_app','email','both') DEFAULT 'in_app',
is_read TINYINT(1) DEFAULT 0, created_at
```

### Auth

One login page → look up role → redirect to student / company / admin dashboard.
`getAdminConnection()` / `getCompanyConnection()` fallback hacks are deleted; the
`Database` singleton keeps a single connection. `config.php` is updated accordingly
(remove `ADMIN_DB_NAME`, `COMPANY_DB_NAME`).

---

## Section 2 — New features

### 2.1 Analytics dashboards (Chart.js via CDN)

- **Student** (analytics section on `dashboard.php`): status donut, applications
  over time (line/area), stipend-by-company (bar), weekly hours + rating trend (line).
- **Admin** (`admin_dashboard.php`): KPI cards (students / companies / internships /
  applications), registrations-over-time (bar), platform-wide status donut, top-companies
  by internships (bar).
- **Company** (`company_dashboard.php`): applications-per-posting (bar), application
  status donut, applications-over-time (line).

All via simple aggregated SQL on the unified DB. Charts render with Chart.js; no build
step. Each page gets an `analytics.js` module for chart setup; data passed as JSON from
the PHP page (matching the existing `$dashboardData` pattern).

### 2.2 Calendar & timeline (new page — `calendar.php`)

- Month-grid calendar with color-coded markers for: application dates, interview days
  (status = `interview` → start_date), internship start/end spans, progress-log days.
- Scrollable timeline list grouped by month, with status badges.
- Click a date → event list for that day (JS-driven, data embedded as JSON).
- Student sees their own data; admin sees all students (with a student filter).

### 2.3 Email + in-app notifications

- `notify()` helper in `config.php` (or `php/notify.php`):
  - Always insert a `notifications` row.
  - Attempt SMTP email via PHPMailer only if `SMTP_USERNAME`/`SMTP_PASSWORD` are set;
    on failure or absence, degrade gracefully (log to `uploads/emails/`, keep the
    in-app notification). The demo never breaks because email isn't configured.
- Notification bell in header: unread count badge, dropdown list, mark-as-read, "view all".
- **Triggers:**
  - Student ← application status change; internship ending within 7 days.
  - Company ← new application received on one of its postings.
  - Admin ← new company / new user registration.
- Admin settings page: toggle which notification types are **emailed** vs in-app only.

---

## Section 3 — Bug fixes, hardening, polish, testing

### 3.1 Bug fixes & hardening (priority order)

1. Remove separate-DB connection fallbacks after unification; fix queries that referenced
   them (`getAdminConnection`, `getCompanyConnection`).
2. Auth: correct role-based redirects, single logout, proper session handling.
3. File uploads: size/type validation, safe filenames, cleanup on record delete.
4. CSRF on every POST form (audit all forms).
5. XSS — all output escaped via `e()` (audit).
6. Password-reset flow verified end-to-end.
7. Date validation (start ≤ end); sensible status transitions.
8. Delete dead/duplicate files: `php/tempCodeRunnerFile.php`, `reset_pw.php`,
   legacy `student-dashboard.html`.

### 3.2 UI/UX polish

- Shared layout partials: `php/partials/header.php`, `sidebar.php`, `footer.php` with
  role-aware navigation.
- Move per-page `<style>` blocks into unified `css/style.css` + `css/responsive.css`.
- Consistent cards, badges, empty states, toasts, loading states; notification bell;
  breadcrumbs; refined dark-green theme (keep InternTrack brand).

### 3.3 Testing & demo

- Seed-data script so all three roles have enough records to make charts/calendar look
  alive.
- Smoke-test checklist: log in as each role → exercise each major flow.
- `DEMO.md` with login credentials + "what to show" walkthrough.
- Verify every feature on XAMPP.

### Error handling

- Keep centralized DB error handling in `config.php` (log, never expose raw errors).
- try/catch around new-feature queries.
- Emails degrade gracefully (in-app notification always persists).

---

## Out of scope (for this pass)

- Job-posting + apply flow (already exists; only touched to fix the DB split).
- Full rewrite / framework migration (rejected as Approach B).
- Mobile app / API for external clients.

## Acceptance checklist

- [ ] Single database `internship_tracker1`; no code references to other DB names.
- [ ] All three roles log in from one auth path and land on the right dashboard.
- [ ] Student, admin, company analytics render with real data.
- [ ] `calendar.php` shows markers + day event lists; timeline renders.
- [ ] Notifications: bell shows unread count; emails attempt when configured; app works
      without SMTP.
- [ ] No dead files; uploads validated; CSRF + XSS audit clean.
- [ ] `DEMO.md` + seed data; smoke test passes on XAMPP.
