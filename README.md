# Mentees Plus (`block_menteesplus`)

Enhanced replacement for Moodle's standard **Mentees** block.

## Features

- Uses the same underlying mentee relationship as Moodle's standard Mentees block: role assignments in user contexts.
- Shows each mentee's cohort memberships.
- Provides a full searchable list.
- Filters by cohort, no cohort, and account status.
- Supports 25 / 50 / 100 users per page.
- Provides quick links to the user profile and Moodle messaging.
- Uses batched cohort queries to avoid N+1 database access.
- Stores no additional personal data.

## Compatibility

- Moodle 5.0+
- Declared support: Moodle 5.0–5.2

## Installation

Copy the plugin directory to:

`public/blocks/menteesplus`

On installations where Moodle's web root is the Moodle directory itself, use:

`blocks/menteesplus`

Then open **Site administration → Notifications** and complete the upgrade.

After installation, add **Mentees Plus / Подопечные+** to the Dashboard or another page.

## Assignment model

The plugin does not create a new mentor/student mapping table. It detects mentees from existing role assignments where the current mentor has a role assigned in the student's `CONTEXT_USER`, preserving the behaviour of the standard Moodle Mentees block.

## Privacy

The plugin stores no personal data. It reads existing user, role assignment, and cohort membership records.


## 0.1.4
Responsive results grid, wrapped actions, stable avatar fallback and mobile cards.
