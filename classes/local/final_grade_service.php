<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Restricted final course grade editing for assigned mentees.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_menteesplus\local;

/**
 * Authorisation and gradebook service, with no broad course grading capabilities.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class final_grade_service {
    /**
     * Check the direct mentor relationship AND the effective capability at the student's context.
     * A system-wide capability alone is never sufficient.
     *
     * @param int $mentorid Logged-in mentor id.
     * @param int $studentid Student id.
     * @return bool
     */
    public function can_edit_student(int $mentorid, int $studentid): bool {
        global $DB;

        if ($mentorid <= 0 || $studentid <= 0 || $mentorid === $studentid) {
            return false;
        }
        if (!$DB->record_exists('user', ['id' => $studentid, 'deleted' => 0])) {
            return false;
        }

        $studentcontext = \context_user::instance($studentid, IGNORE_MISSING);
        if (!$studentcontext || !has_capability('block/menteesplus:editfinalgrades', $studentcontext, $mentorid)) {
            return false;
        }

        // Match the existing Mentees Plus repository relationship: direct role assignment
        // in the student's user context, not merely inherited roles or cohort membership.
        return $DB->record_exists('role_assignments', [
            'contextid' => $studentcontext->id,
            'userid' => $mentorid,
        ]);
    }

    /**
     * Require direct access to a particular student without revealing other students.
     *
     * @param int $mentorid Mentor id.
     * @param int $studentid Student id.
     * @return void
     */
    public function require_student_access(int $mentorid, int $studentid): void {
        if (!$this->can_edit_student($mentorid, $studentid)) {
            throw new \moodle_exception('gradeaccessdenied', 'block_menteesplus');
        }
    }

    /**
     * List only editable course-total grade items for courses the mentee is actively enrolled in.
     * Hidden courses/items/grades are not exposed in the dedicated mentor interface.
     *
     * @param int $mentorid Mentor id.
     * @param int $studentid Student id.
     * @return array<int, \stdClass> Course-grade items indexed by grade item id.
     */
    public function get_course_grades(int $mentorid, int $studentid): array {
        global $CFG, $DB;

        $this->require_student_access($mentorid, $studentid);
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $courses = enrol_get_users_courses($studentid, true, 'id, fullname, shortname, visible');
        $visiblecourseids = [];
        foreach ($courses as $course) {
            if ((int) $course->id !== SITEID && !empty($course->visible)) {
                $visiblecourseids[] = (int) $course->id;
            }
        }
        if (!$visiblecourseids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($visiblecourseids, SQL_PARAMS_NAMED, 'courseid');
        $params['studentid'] = $studentid;
        $params['itemtype'] = 'course';

        $sql = "SELECT gi.id, gi.courseid, gi.gradetype, gi.grademin, gi.grademax,
                       gi.scaleid, gi.hidden AS itemhidden, gi.locked AS itemlocked,
                       gi.locktime AS itemlocktime, gi.timemodified AS itemmodified,
                       gg.id AS gradeid, gg.finalgrade, gg.timemodified AS grademodified,
                       gg.hidden AS gradehidden, gg.locked AS gradelocked,
                       gg.overridden AS gradeoverridden, gg.excluded AS gradeexcluded
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :studentid
                 WHERE gi.courseid {$insql}
                   AND gi.itemtype = :itemtype
                   AND gi.deleted = 0
              ORDER BY gi.courseid ASC, gi.id ASC";
        $records = $DB->get_records_sql($sql, $params);
        $result = [];
        $now = time();

        foreach ($records as $record) {
            if (!in_array((int) $record->gradetype, [GRADE_TYPE_VALUE, GRADE_TYPE_SCALE], true)) {
                continue;
            }
            if ($record->itemhidden || $record->gradehidden) {
                continue;
            }
            $course = $courses[$record->courseid];
            $record->coursename = $course->fullname;
            $record->courseshortname = $course->shortname;
            $record->editable = !(
                $record->itemlocked || $record->gradelocked || $record->gradeexcluded
                || ($record->itemlocktime > 0 && $record->itemlocktime <= $now)
            );
            $record->expected = self::grade_state_token($record);
            $result[$record->id] = $record;
        }
        return $result;
    }

    /**
     * Get the options of a Moodle scale without treating display labels as grades.
     *
     * @param int $scaleid Moodle scale id.
     * @return array<int, string> 1-based numeric values matching Moodle grade_scale.
     */
    public function get_scale_options(int $scaleid): array {
        global $DB;

        $scale = $DB->get_field('scale', 'scale', ['id' => $scaleid], IGNORE_MISSING);
        if ($scale === false) {
            return [];
        }
        $labels = explode(',', $scale);
        $options = [];
        foreach ($labels as $index => $label) {
            $options[$index + 1] = trim($label);
        }
        return $options;
    }

    /**
     * Change one course-total final grade. Every mutable identifier is verified anew.
     *
     * @param int $mentorid Mentor id.
     * @param int $studentid Student id.
     * @param int $itemid Submitted Moodle grade_items.id.
     * @param string $rawvalue New numeric or scale grade value.
     * @param string $expected Value from the previously displayed grade state.
     * @return void
     */
    public function save_grade(int $mentorid, int $studentid, int $itemid, string $rawvalue, string $expected): void {
        global $CFG, $DB;

        $items = $this->get_course_grades($mentorid, $studentid);
        if (!isset($items[$itemid])) {
            throw new \moodle_exception('gradeaccessdenied', 'block_menteesplus');
        }
        $item = $items[$itemid];
        if (!$item->editable) {
            throw new \moodle_exception('gradelocked', 'block_menteesplus');
        }
        if (!hash_equals($item->expected, $expected)) {
            throw new \moodle_exception('gradeconflict', 'block_menteesplus');
        }

        $rawvalue = trim(str_replace(',', '.', $rawvalue));
        if (!preg_match('/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/D', $rawvalue)) {
            throw new \moodle_exception('gradeinvalid', 'block_menteesplus');
        }
        $newgrade = (float) $rawvalue;
        if (!is_finite($newgrade)) {
            throw new \moodle_exception('gradeinvalid', 'block_menteesplus');
        }
        if ((int) $item->gradetype === GRADE_TYPE_SCALE) {
            $options = $this->get_scale_options((int) $item->scaleid);
            if (!ctype_digit($rawvalue) || !array_key_exists((int) $rawvalue, $options)) {
                throw new \moodle_exception('gradeinvalid', 'block_menteesplus');
            }
        } else if ($newgrade < (float) $item->grademin || $newgrade > (float) $item->grademax) {
            throw new \moodle_exception('gradeinvalid', 'block_menteesplus');
        }

        // Use the gradebook API to preserve overriding semantics and grade history.
        // grade_update() explicitly cannot modify course-total items.
        require_once($CFG->libdir . '/gradelib.php');
        $gradeitem = \grade_item::fetch(['id' => $itemid]);
        if (
            !$gradeitem
            || (int) $gradeitem->courseid !== (int) $item->courseid
            || $gradeitem->itemtype !== 'course'
        ) {
            throw new \moodle_exception('gradeaccessdenied', 'block_menteesplus');
        }
        if (!$gradeitem->update_final_grade($studentid, $newgrade, 'block_menteesplus', false, FORMAT_MOODLE, $mentorid)) {
            throw new \moodle_exception('gradesavefailed', 'block_menteesplus');
        }

        $saved = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => $studentid], 'id', MUST_EXIST);
        $event = \block_menteesplus\event\final_grade_updated::create([
            'context' => \context_course::instance($item->courseid),
            'objectid' => $saved->id,
            'relateduserid' => $studentid,
            'other' => ['gradeitemid' => $itemid],
        ]);
        $event->trigger();
    }

    /**
     * Concurrency token: prevents accidentally overwriting edits made since the page was opened.
     * This token is not used as an authorisation credential.
     *
     * @param \stdClass $item Grade item and grade state.
     * @return string
     */
    private static function grade_state_token(\stdClass $item): string {
        return hash('sha256', implode('|', [
            $item->id,
            $item->itemmodified ?? '',
            $item->gradeid ?? '',
            $item->finalgrade ?? '',
            $item->grademodified ?? '',
            $item->gradeoverridden ?? '',
            $item->gradelocked ?? '',
            $item->itemlocked ?? '',
            $item->grademin,
            $item->grademax,
            $item->scaleid ?? '',
        ]));
    }
}
