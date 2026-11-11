# Security policy

Do not grant ordinary gradebook-wide capabilities to the mentor role. The dedicated capability `block/menteesplus:editfinalgrades` is a high-impact permission and must be explicitly approved per existing curator role. Every write revalidates the student-context mentor relation and course grade item.

Report security issues privately to the site's Moodle administrators before disclosing details. Always validate changes in a staging Moodle instance and retain a database backup before deployment.
