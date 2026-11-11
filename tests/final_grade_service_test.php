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
 * Integration tests for the mentee-only final grade editor.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_menteesplus\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Enforces per-mentee and per-course grade-editing boundaries.
 *
 * @package   block_menteesplus
 * @category  test
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(final_grade_service::class)]
final class final_grade_service_test extends \advanced_testcase {
    /**
     * Create minimal test enrolments and student-context mentor role assignments.
     *
     * @return array
     */
    private function create_fixture(): array {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $mentor = $generator->create_user();
        $student = $generator->create_user();
        $other = $generator->create_user();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $generator->enrol_user($student->id, $course->id);
        $generator->enrol_user($other->id, $course->id);
        $generator->enrol_user($other->id, $othercourse->id);
        $roleid = create_role('Mentor grade editor', 'menteesplusgradeeditor', 'Mentor grade editor');
        assign_capability(
            'block/menteesplus:editfinalgrades',
            CAP_ALLOW,
            $roleid,
            \context_system::instance()
        );
        role_assign($roleid, $mentor->id, \context_user::instance($student->id)->id);
        $this->setUser($mentor);
        $item = \grade_item::fetch_course_item($course->id);
        $otheritem = \grade_item::fetch_course_item($othercourse->id);

        return [$mentor, $student, $other, $course, $item, $otheritem];
    }

    /**
     * The same student-context relationship used for display gates grade editing.
     *
     * @return void
     */
    public function test_editable_only_for_directly_assigned_students(): void {
        [$mentor, $student, $other, $course, $item, $otheritem] = $this->create_fixture();
        $service = new final_grade_service();
        $this->assertTrue($service->can_edit_student($mentor->id, $student->id));
        $this->assertFalse($service->can_edit_student($mentor->id, $other->id));
        $this->assertFalse($service->can_edit_student($mentor->id, $mentor->id));

        $grades = $service->get_course_grades($mentor->id, $student->id);
        $this->assertArrayHasKey($item->id, $grades);
        $this->assertArrayNotHasKey($otheritem->id, $grades);
        $this->assertTrue($grades[$item->id]->editable);
    }

    /**
     * An inherited system-level capability alone does not create a mentor relationship.
     *
     * @return void
     */
    public function test_inherited_capability_without_assignment_is_not_sufficient(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $assignment = $DB->get_record('role_assignments', [
            'userid' => $mentor->id,
            'contextid' => \context_user::instance($student->id)->id,
        ], '*', MUST_EXIST);
        role_assign($assignment->roleid, $mentor->id, \context_system::instance()->id);
        $service = new final_grade_service();
        $this->assertTrue(has_capability(
            'block/menteesplus:editfinalgrades',
            \context_user::instance($other->id),
            $mentor->id
        ));
        $this->assertFalse($service->can_edit_student($mentor->id, $other->id));
    }

    /**
     * A forged student id must never return or change another user's grade.
     *
     * @return void
     */
    public function test_forbidden_student_cannot_be_edited(): void {
        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $other->id, $item->id, '95', 'forged');
    }

    /**
     * A forged item id from a course not taken by the assigned student must be rejected.
     *
     * @return void
     */
    public function test_forbidden_course_item_cannot_be_edited(): void {
        [$mentor, $student, $other, $course, $item, $otheritem] = $this->create_fixture();
        $service = new final_grade_service();
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $otheritem->id, '95', 'forged');
    }

    /**
     * Save the real course total and protect it from stale subsequent updates.
     *
     * @return void
     */
    public function test_updates_only_assigned_grade_and_rejects_stale_form(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $original = $grades[$item->id];
        $otherbefore = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $other->id]);
        $sink = $this->redirectEvents();
        $service->save_grade($mentor->id, $student->id, $item->id, '87', $original->expected);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \block_menteesplus\event\final_grade_updated
        ));
        $this->assertCount(1, $events);
        $this->assertSame((int) $student->id, (int) $events[0]->relateduserid);

        $saved = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $student->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(87, $saved->finalgrade, 0.001);
        $this->assertNotEmpty($saved->overridden);
        $otherafter = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $other->id]);
        $this->assertSame(
            $otherbefore ? $otherbefore->finalgrade : null,
            $otherafter ? $otherafter->finalgrade : null
        );

        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '88', $original->expected);
    }

    /**
     * Locked final grades must be read-only even if the mentor is allowed to grade the student.
     *
     * @return void
     */
    public function test_locked_course_total_is_not_editable(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $DB->set_field('grade_items', 'locked', 1, ['id' => $item->id]);
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $this->assertFalse($grades[$item->id]->editable);
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '90', $grades[$item->id]->expected);
    }

    /**
     * Reject out-of-range grade values before they reach Moodle gradebook code.
     *
     * @return void
     */
    public function test_invalid_value_is_rejected(): void {
        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '1000000', $grades[$item->id]->expected);
    }

    /**
     * An assignment removed after a grade form is opened revokes editing immediately.
     *
     * @return void
     */
    public function test_removed_mentee_assignment_denies_old_form(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $studentcontext = \context_user::instance($student->id);
        $assignments = $DB->get_records('role_assignments', [
            'userid' => $mentor->id, 'contextid' => $studentcontext->id,
        ]);
        foreach ($assignments as $assignment) {
            role_unassign($assignment->roleid, $mentor->id, $studentcontext->id);
        }
        $this->assertFalse($service->can_edit_student($mentor->id, $student->id));
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '90', $grades[$item->id]->expected);
    }

    /**
     * A direct mentor role without the new write capability is never enough.
     *
     * @return void
     */
    public function test_missing_edit_capability_denies_access_and_saving(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $assignment = $DB->get_record('role_assignments', [
            'userid' => $mentor->id,
            'contextid' => \context_user::instance($student->id)->id,
        ], '*', MUST_EXIST);
        assign_capability(
            'block/menteesplus:editfinalgrades',
            CAP_PREVENT,
            $assignment->roleid,
            \context_system::instance()->id,
            true
        );
        // Role changes inside one PHPUnit request require cache invalidation.
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($mentor);

        $service = new final_grade_service();
        $this->assertFalse($service->can_edit_student($mentor->id, $student->id));
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '75', 'forged');
    }

    /**
     * A student who is unenrolled between opening and submitting a form cannot be graded.
     *
     * @return void
     */
    public function test_unenrolled_student_cannot_be_graded_using_old_form(): void {
        global $CFG;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $this->assertArrayHasKey($item->id, $grades);
        $expected = $grades[$item->id]->expected;

        require_once($CFG->libdir . '/enrollib.php');
        $manual = enrol_get_plugin('manual');
        $this->assertNotFalse($manual);
        $unenrolled = false;
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $manual->unenrol_user($instance, $student->id);
                $unenrolled = true;
            }
        }
        $this->assertTrue($unenrolled);
        $this->assertArrayNotHasKey($item->id, $service->get_course_grades($mentor->id, $student->id));
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '80', $expected);
    }

    /**
     * A course made hidden after the form was opened is no longer accessible.
     *
     * @return void
     */
    public function test_hidden_course_denies_old_form(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $expected = $grades[$item->id]->expected;
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $this->assertArrayNotHasKey($item->id, $service->get_course_grades($mentor->id, $student->id));
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '80', $expected);
    }

    /**
     * Hidden course totals are never leaked through the restricted grade editor.
     *
     * @return void
     */
    public function test_hidden_grade_item_is_not_visible_or_editable(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $expected = $grades[$item->id]->expected;
        $DB->set_field('grade_items', 'hidden', 1, ['id' => $item->id]);
        $this->assertArrayNotHasKey($item->id, $service->get_course_grades($mentor->id, $student->id));
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '80', $expected);
    }

    /**
     * Excluded grade records must remain read-only after a successful override.
     *
     * @return void
     */
    public function test_excluded_grade_is_not_editable(): void {
        global $DB;

        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $service->save_grade($mentor->id, $student->id, $item->id, '79', $grades[$item->id]->expected);
        $DB->set_field('grade_grades', 'excluded', 1, ['itemid' => $item->id, 'userid' => $student->id]);
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $this->assertFalse($grades[$item->id]->editable);
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '80', $grades[$item->id]->expected);
    }

    /**
     * Badly formatted input cannot reach the gradebook even with a valid form token.
     *
     * @return void
     */
    public function test_non_numeric_value_is_rejected(): void {
        [$mentor, $student, $other, $course, $item] = $this->create_fixture();
        $service = new final_grade_service();
        $grades = $service->get_course_grades($mentor->id, $student->id);
        $this->expectException(\moodle_exception::class);
        $service->save_grade($mentor->id, $student->id, $item->id, '90hello', $grades[$item->id]->expected);
    }
}
