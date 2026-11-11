# Changelog

## 0.2.0-beta2 — 2026-11-11

- Restored GitHub Actions CI to the source tree, with clean Moodle installs and 5.0–5.2/PHP matrix.
- Added PostgreSQL and MySQL integration runs and enabled PHPCS, PHPDoc, savepoints and PHPUnit checks.
- Added mentee visibility/search/pagination regressions; expanded final-grade security tests for missing capabilities, hidden/excluded grades, enrolment revocation and out-of-range inputs.
- Kept all 0.2.0-beta1 features and database schema unchanged.

## 0.2.0-beta1 — 2026-11-11

- Added opt-in `block/menteesplus:editfinalgrades` capability for directly assigned mentees.
- Added a dedicated per-student final course grade editor without broad gradebook rights.
- Validates relationship, capability, active enrolment, item/course scope and locks on every save.
- Validates numeric ranges and Moodle grade scales; rejects stale grade forms.
- Uses native grade history and a logged plugin event.
- Added bilingual UI messages and per-student access/regression tests.
- No database changes; old block instances and filters are preserved.

## 0.1.5 — 2026-10-12

- Added package-level GPL-3.0-or-later license.
- Fixed concatenated language strings in English and Russian.
- Limited displayed and searchable identity fields to those allowed by Moodle's site settings and permissions.
- Rechecked identity access in the repository, with regression tests.
- Kept the existing user, cohort, status and pagination functionality.

## 0.1.4

- Responsive results grid, wrapped actions, stable avatar fallback and mobile cards.
