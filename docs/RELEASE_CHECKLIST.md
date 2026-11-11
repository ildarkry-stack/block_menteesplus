# Release checklist

- [x] New capability denied by default; direct student-context assignment required on every save
- [x] Student and grade item parameters validated; sesskey on writes
- [x] Grade locks, hidden grades, deleted items, active enrolment and numeric bounds checked
- [x] PHP syntax checked locally
- [x] EN/RU language keys checked for parity
- [x] Original mentee list and filters preserved
- [x] Moodle version increased; no DB migration required
- [x] Source GitHub workflow added, with 7 CI matrix jobs and expanded security regression tests (18 PHPUnit methods total)
- [x] Release ZIP contains one correctly named root directory
- [ ] Actual Moodle 5.0–5.2 clean installation/upgrade test
- [ ] `moodle-plugin-ci phpcs`, `phpdoc`, `validate`, `savepoints`, `phpunit`
- [ ] Staging test with the site's custom mentor role, normal and scale course grades
- [ ] PostgreSQL and MySQL real runtime test

This build remains a beta until the pending integration checks pass.
