# TODO - Review & Fix `sql/database.sql`

- [x] Fix malformed seed INSERT for `superadmin` + `manager` in `sql/database.sql`
- [x] Add missing foreign key constraint for `users.company_id` -> `companies(id)`
- [x] Make `progress_logs.rating` CHECK compatible (remove/adjust if needed)
- [ ] Validate by running the updated SQL in MySQL/phpMyAdmin (no syntax errors)



