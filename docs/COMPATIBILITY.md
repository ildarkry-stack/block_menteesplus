# Compatibility

Target Moodle branches: 5.0, 5.1, 5.2. All version-independent URLs use Moodle's URL API; no filesystem paths to `/public/` are hardcoded. The implementation uses existing Moodle APIs `enrol_get_users_courses`, `grade_item::fetch` and `grade_item::update_final_grade`, not `grade_update` (which cannot edit course totals).

GitHub source CI in `.github/workflows/ci.yml` checks Moodle 5.0 (PHP 8.2/8.4), Moodle 5.1 (PHP 8.2/8.4) and Moodle 5.2 (PHP 8.3/8.4) on PostgreSQL 17; Moodle 5.2/PHP 8.3 also runs on MySQL 8.4. These targets follow Moodle PHP compatibility requirements; passing builds are not claimed until workflow jobs complete. The Moodle-installation ZIP omits `.github`, but the source ZIP retains it.
