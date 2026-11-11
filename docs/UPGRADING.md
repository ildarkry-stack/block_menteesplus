# Upgrade guide: 0.1.5 → 0.2.0-beta2

1. Back up the database and Moodle code. Deploy to a staging instance first.
2. Replace the existing `blocks/menteesplus` directory with the updated `menteesplus/` content; keep existing block instances.
3. Complete **Site administration → Notifications** (plugin version increases to 2026111101).
4. In **Define roles**, enable `block/menteesplus:editfinalgrades` only for the intended curator role assigned in students' user contexts.
5. Use a test curator and two students, one assigned and one not assigned, to verify that only the assigned student's final course grade is editable.
6. Verify Moodle grade history and that normal grade recalculation is overridden only for explicitly edited course totals.

No new database tables or migrations are needed. The capability is not granted by default. If the live gradebook should retain automatic calculation, do not edit the final course total manually.
