# Mentees Plus (`block_menteesplus`)

An enhanced Moodle **Mentees** block. In version 0.2.0-beta2, authorised mentors can also edit **only the final course-total grade** of directly assigned mentees without obtaining broad access to a course gradebook.

## Features

- Uses existing role assignments in `CONTEXT_USER`; no separate student/mentor mapping.
- Searchable, paginated mentee list with cohorts, status filtering, profile and messaging actions.
- One **Final grades / Итоговые оценки** link per student, visible only when the mentor has the new capability.
- A dedicated list of active **visible** courses in which the selected student is enrolled; only course-total grade items are displayed.
- Supports numeric course grade ranges and Moodle course-total scales; prevents invalid or out-of-range values.
- Blocks direct access to other students, forged grade item IDs, locked/excluded/hidden grades, and stale form submissions.
- Uses Moodle's `grade_item::update_final_grade` for accurate history and override handling, and triggers a plugin event for Moodle logs.
- Stores no separate student mapping or grade records.

## Compatibility

- Target: Moodle 5.0, 5.1 and 5.2 with a single codebase.
- PHP versions according to the selected Moodle version. The source distribution CI checks Moodle 5.0 / PHP 8.2 and 8.4, Moodle 5.1 / PHP 8.2 and 8.4, Moodle 5.2 / PHP 8.3 and 8.4 (PostgreSQL 17), plus Moodle 5.2 / PHP 8.3 (MySQL 8.4).
- Test results must be confirmed in a real Moodle instance/CI before production rollout.

## Installation / upgrade

Install the `menteesplus` directory in `blocks/menteesplus` (under `public/` for Moodle versions/layouts that use it) or upload a ZIP with exactly one root `menteesplus/` directory in **Site administration → Plugins → Install plugins**.

For an existing installation, keep your original block instances and upgrade normally at **Site administration → Notifications**. No additional tables, schema migration or student reassignment are required. Back up the Moodle DB before upgrading in production.

## Assigning restricted rights

1. In **Site administration → Users → Permissions → Define roles**, open the existing mentor/curator role.
2. Allow **`block/menteesplus:editfinalgrades`** for that role. This capability is deliberately **not granted by default** to any role.
3. Ensure the mentor's role is actually assigned in each student's **user context**, as it already is for the original Mentees block. **Giving a system-level capability without a direct per-student role assignment is not sufficient.**
4. Avoid assigning `moodle/grade:viewall`, `moodle/grade:edit`, `moodle/grade:manage`, `gradereport/grader:view` or other broad gradebook/participants rights to this mentor role. Otherwise Moodle *outside this plugin* can still grant wider access.
5. In **Mentees Plus → View all**, choose **Final grades** beside an authorised student. Save one course total at a time.

**Note:** The new capability must be granted on the role participating in the student-context assignment (or otherwise effective in that context). Administrators/managers are not automatically exempt from the direct-relationship restriction in this plugin.

## What is edited

Only the Moodle **course total** grade item (`grade_items.itemtype = 'course'`) for a student enrolled in that course can be edited. Quiz, assignment, activity, category, and other students' grades cannot be edited through this interface.

**Manual modification overrides automatic recalculation** of that student's course total. To resume automatic course-total calculation, an authorised gradebook administrator must remove the **overridden** flag in the native Moodle gradebook.

This version does not provide bulk grading, unenrolled students' grades, hidden courses/grades, custom text grade items or the ability to clear an existing grade.

## Security and audit

Both the display and the write path check the individual student-context capability. The write path independently verifies the direct role assignment, active course enrolment, course-grade item type and ID, lock/visibility/exclusion status, numeric constraints, current grade state and Moodle sesskey. If the mentor's relationship is revoked before saving, the save is rejected. Grade history is stored by Moodle's native gradebook; an additional `block_menteesplus\\event\\final_grade_updated` event is emitted to the standard Moodle event log.

Do **not** remove or bypass the new permission checks and do **not** grant general course gradebook editing capabilities to achieve this workflow.

## Tests

`tests/mentee_visibility_security_test.php` verifies isolated list/search/pagination, system-role boundary; `tests/final_grade_service_test.php` covers direct assignment, unrelated students and courses, successful updates, stale edits, locked totals, invalid inputs and revoked assignments. See `.github/workflows/ci.yml` in the GitHub source distribution for automatic checks on pushes, pull requests and manual runs. The Moodle-installation ZIP intentionally does not include `.github`. A local PHP syntax check alone does not establish Moodle runtime compatibility.

## Known limitations

The current editor displays **one mentee at a time**. Active enrolment and existing course grade items are required. It does not expose the native grader report to mentors. Moodle administrators with independent capabilities may still see/manage grades through Moodle core, outside the scope of this plugin.
