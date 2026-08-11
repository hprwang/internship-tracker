# 🎓 InternTrack — Demo Guide

Everything you need to run, verify, and present InternTrack for a demo or
viva on XAMPP. Works out of the box with a fresh import of the unified schema
plus the demo seed.

---

## 1. One-time setup (5 minutes)

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`.
3. Import `sql/unified_schema.sql` (creates the `internship_tracker1` database,
   all tables, and a few starter rows).
4. Run the demo seed from the project root:

   ```bash
   php sql/seed_demo.php
   ```

   The seeder is **idempotent** — safe to re-run any number of times. It
   resets demo-owned rows and re-inserts realistic data (6 companies, 4 demo
   students, 10 student internships with weekly progress logs, 6 job postings
   with applications in every status, and sample notifications).

5. Open `http://localhost/internship-tracker/`.

> **Existing installs:** if you already had a database from before, run
> `php sql/migrate_unify.php` first (see `sql/`), then the seed above.

---

## 2. Demo accounts

| Role    | Login email                              | Password     | Lands on               |
|---------|------------------------------------------|--------------|------------------------|
| Student | `demo_student1@interntracker.com`        | `Student@123` | `dashboard.php`        |
| Student | `demo_student2@interntracker.com`        | `Student@123` | `dashboard.php`        |
| Student | `demo_student3@interntracker.com`        | `Student@123` | `dashboard.php`        |
| Student | `demo_student4@interntracker.com`        | `Student@123` | `dashboard.php`        |
| Company | `demo_company@interntracker.com`         | `Company@123` | `php/company_dashboard.php` |
| Admin   | `admin@interntracker.com`                | `Admin@123`   | `php/admin_dashboard.php`   |

All demo students have internships, progress logs, notifications and
applications already populated, so charts and the calendar look alive from
the first login.

---

## 3. Smoke-test checklist

Run through these before presenting. Leave every box ticked.

- [ ] Fresh DB: import `sql/unified_schema.sql`, then `php sql/seed_demo.php`
      — both complete without errors.
- [ ] Login as **student** → lands on student dashboard, KPIs + analytics render.
- [ ] Login as **company** → lands on company dashboard, company name shows,
      KPIs + charts render.
- [ ] Login as **admin** → lands on admin dashboard, KPIs + charts render.
- [ ] **Student**: add an internship (dates + status), edit it, delete a test row.
- [ ] **Student**: log weekly progress on an ongoing internship (tasks, skills,
      hours, rating) and see it persist.
- [ ] **Student**: browse open postings and submit an application.
- [ ] **Student**: upload a PDF resume/offer letter (uploads/ is writable).
- [ ] **Student**: notification bell shows unread count; mark-as-read works.
- [ ] **Student**: calendar page shows dots on tracked dates; day click opens details.
- [ ] **Company**: create a posting; see applications arrive; change an
      application status; dashboard charts render.
- [ ] **Admin**: manage students / companies / internships; reports page renders;
      settings page loads.
- [ ] **Calendar & timeline**: dots on dates; click a date; timeline lists
      events for the demo account.
- [ ] **Password reset**: request reset for a demo account → link arrives
      (in-app/email) → set a new password → log in with it.
- [ ] Responsive: resize to 375px (sidebar collapses) and 1280px.

> ⚠️ SMTP is **optional**. In-app notifications work with no email server. To
> send real emails, set `SMTP_USERNAME` / `SMTP_PASSWORD` (see `README.md`).

---

## 4. 10-minute demo script

A tight, ordered walkthrough that shows off the whole product.

**Minute 0–1 — Landing & register screen.** Open the landing page, mention the
roles (Student / Company / Admin) and that one login is shared across all of
them.

**Minute 1–3 — Student.** Log in as `demo_student1@interntracker.com` /
`Student@123`.

1. Point at the **KPI cards** and the **analytics chart** on the dashboard —
   data spans the last 6 months.
2. Open **Calendar & timeline** — dots on tracked days, click one, show the
   timeline.
3. Open the **notifications bell** (unread badge), mark one read.
4. Add a quick internship and log a **weekly progress** entry — shows the
   tracker workflow (Applied → Interview → Accepted → Ongoing → Completed).
5. Browse postings and submit an application.

**Minute 3–5 — Company.** Log out, log in as `demo_company@interntracker.com`
/ `Company@123`.

1. Show the **company dashboard** (name banner, KPIs, recent applications,
   analytics).
2. Open **Applications** — filter by status, update one status, delete a test
   row.
3. Create a **posting** and show it listed.

**Minute 5–7 — Admin.** Log out, log in as `admin@interntracker.com` /
`Admin@123`.

1. Show the **admin dashboard** KPIs + charts (students, companies, internships,
   applications over time).
2. Open **Students / Companies / Internships** management and the **Reports**
   page.
3. Open **Settings** (system config, security options).

**Minute 7–9 — Deep dive (optional).** Password reset flow, the audit log, or
uploading a PDF on a student account.

**Minute 9–10 — Wrap-up.** Emphasize security: bcrypt passwords, CSRF tokens on
every form, prepared statements, output escaping, rate-limited login, and
DB errors never shown to users.

---

## 5. Troubleshooting

| Symptom                              | Fix                                                        |
|--------------------------------------|------------------------------------------------------------|
| "Database connection failed"         | MySQL not running, or `DB_NAME`/`DB_USER` env mismatch. Defaults: `internship_tracker1` / `root` / empty pass. |
| Seed script won't run                | Run from CLI (`php sql/seed_demo.php`), not the browser.   |
| Charts empty                         | Seed data missing — re-run `php sql/seed_demo.php`.        |
| Email never arrives                  | SMTP not configured; notifications still work in-app.      |
| Uploads fail                         | Ensure `uploads/` exists and is writable (XAMPP: yes by default). |
