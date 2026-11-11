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
 * Restricted per-mentee course-total grade editor.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_menteesplus\local\final_grade_service;

require_login();

$studentid = required_param('userid', PARAM_INT);
$service = new final_grade_service();
$service->require_student_access($USER->id, $studentid);

$url = new moodle_url('/blocks/menteesplus/grades.php', ['userid' => $studentid]);
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($studentid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('finalgrades', 'block_menteesplus'));
$PAGE->set_heading(get_string('finalgrades', 'block_menteesplus'));

if (data_submitted() && optional_param('savegrade', 0, PARAM_BOOL)) {
    require_sesskey();
    $itemid = required_param('itemid', PARAM_INT);
    $gradevalue = required_param('gradevalue', PARAM_RAW_TRIMMED);
    $expected = required_param('expected', PARAM_ALPHANUM);
    $service->save_grade($USER->id, $studentid, $itemid, $gradevalue, $expected);
    redirect($url, get_string('gradesaved', 'block_menteesplus'));
}

$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0], '*', MUST_EXIST);
$grades = $service->get_course_grades($USER->id, $studentid);
$scales = [];

// Loading each distinct scale once is enough for all of the student's courses.
foreach ($grades as $grade) {
    if ((int) $grade->gradetype === GRADE_TYPE_SCALE && !isset($scales[$grade->scaleid])) {
        $scales[$grade->scaleid] = $service->get_scale_options((int) $grade->scaleid);
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('menteesplus-page menteesplus-grades-page');
echo html_writer::link(
    new moodle_url('/blocks/menteesplus/index.php'),
    get_string('backtomentees', 'block_menteesplus'),
    ['class' => 'btn btn-outline-secondary mb-3']
);
echo html_writer::tag('h3', s(fullname($student)), ['class' => 'mb-3']);
echo $OUTPUT->notification(get_string('gradeoverridewarning', 'block_menteesplus'), 'warning', false);

if (!$grades) {
    echo $OUTPUT->notification(get_string('nogradecourses', 'block_menteesplus'), 'info', false);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable menteesplus-grades-table';
    $table->head = [
        get_string('discipline', 'block_menteesplus'),
        get_string('currentfinalgrade', 'block_menteesplus'),
        get_string('newfinalgrade', 'block_menteesplus'),
    ];

    foreach ($grades as $grade) {
        $options = $scales[$grade->scaleid] ?? [];
        if ($grade->finalgrade === null) {
            $current = get_string('notgraded', 'block_menteesplus');
        } else if ((int) $grade->gradetype === GRADE_TYPE_SCALE) {
            $current = s($options[(int) $grade->finalgrade] ?? (string) $grade->finalgrade);
        } else {
            $current = s((string) $grade->finalgrade);
        }

        $course = html_writer::div(format_string($grade->coursename), 'fw-semibold');
        $course .= html_writer::div(s($grade->courseshortname), 'small text-muted');

        if (!$grade->editable) {
            $editor = html_writer::span(
                get_string('gradelocked', 'block_menteesplus'),
                'text-muted'
            );
        } else {
            $fieldid = 'gradevalue_' . $grade->id;
            if ((int) $grade->gradetype === GRADE_TYPE_SCALE) {
                $options = ['' => get_string('choosegrade', 'block_menteesplus')] + $options;
                $input = html_writer::select(
                    $options,
                    'gradevalue',
                    $grade->finalgrade === null ? '' : (string) (int) $grade->finalgrade,
                    false,
                    ['id' => $fieldid, 'class' => 'form-select form-select-sm', 'required' => 'required']
                );
            } else {
                $input = html_writer::empty_tag('input', [
                    'type' => 'number',
                    'name' => 'gradevalue',
                    'id' => $fieldid,
                    'value' => $grade->finalgrade === null ? '' : (string) $grade->finalgrade,
                    'min' => (string) $grade->grademin,
                    'max' => (string) $grade->grademax,
                    'step' => 'any',
                    'required' => 'required',
                    'class' => 'form-control form-control-sm',
                    'aria-label' => get_string('newfinalgrade', 'block_menteesplus'),
                ]);
            }
            $form = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $url->out(false),
                'class' => 'd-flex flex-wrap align-items-center gap-2',
            ]);
            $form .= html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey(),
            ]);
            $form .= html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'userid', 'value' => $studentid,
            ]);
            $form .= html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'itemid', 'value' => $grade->id,
            ]);
            $form .= html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'expected', 'value' => $grade->expected,
            ]);
            $form .= $input;
            if ((int) $grade->gradetype === GRADE_TYPE_VALUE) {
                $range = (object) ['min' => $grade->grademin, 'max' => $grade->grademax];
                $form .= html_writer::span(
                    get_string('graderange', 'block_menteesplus', $range),
                    'small text-muted'
                );
            }
            $form .= html_writer::tag('button', get_string('savegrade', 'block_menteesplus'), [
                'type' => 'submit', 'name' => 'savegrade', 'value' => '1', 'class' => 'btn btn-sm btn-primary',
            ]);
            $form .= html_writer::end_tag('form');
            $editor = $form;
        }
        $table->data[] = [$course, $current, $editor];
    }

    echo html_writer::start_div('table-responsive');
    echo html_writer::table($table);
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
