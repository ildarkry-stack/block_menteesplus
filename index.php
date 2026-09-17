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
 * Full mentees list with search and filters.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_menteesplus\local\mentee_repository;

require_login();

$q = optional_param('q', '', PARAM_NOTAGS);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$status = optional_param('status', 'all', PARAM_ALPHA);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = optional_param('perpage', 25, PARAM_INT);

if (!in_array($status, ['all', 'active', 'suspended'], true)) {
    $status = 'all';
}
if (!in_array($perpage, [25, 50, 100], true)) {
    $perpage = 25;
}

$context = context_system::instance();
$url = new moodle_url('/blocks/menteesplus/index.php', [
    'q' => $q,
    'cohortid' => $cohortid,
    'status' => $status,
    'perpage' => $perpage,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('allmentees', 'block_menteesplus'));
$PAGE->set_heading(get_string('allmentees', 'block_menteesplus'));

$canviewidentity = has_capability('moodle/site:viewuseridentity', $context);
$repository = new mentee_repository();
$cohorts = $repository->get_available_cohorts($USER->id);
$result = $repository->get_mentees(
    $USER->id,
    $q,
    $cohortid,
    $status,
    $canviewidentity,
    $page * $perpage,
    $perpage
);
$users = $result['users'];
$total = $result['total'];
$cohortsbyuser = $repository->get_cohorts_for_users(array_keys($users));

echo $OUTPUT->header();

echo html_writer::start_div('card mb-4 menteesplus-filter-card');
echo html_writer::start_div('card-body');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/blocks/menteesplus/index.php'))->out(false),
    'class' => 'menteesplus-filters',
]);

echo html_writer::start_div('menteesplus-filter menteesplus-filter-search');
echo html_writer::label(get_string('search', 'block_menteesplus'), 'id_q', true, ['class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'name' => 'q',
    'id' => 'id_q',
    'value' => $q,
    'class' => 'form-control',
    'placeholder' => get_string(
        $canviewidentity ? 'searchplaceholderidentity' : 'searchplaceholder',
        'block_menteesplus'
    ),
]);
echo html_writer::end_div();

echo html_writer::start_div('menteesplus-filter menteesplus-filter-cohort');
echo html_writer::label(get_string('cohortfilter', 'block_menteesplus'), 'id_cohortid', true, ['class' => 'form-label']);
$cohortoptions = [
    0 => get_string('allcohorts', 'block_menteesplus'),
    -1 => get_string('nocohort', 'block_menteesplus'),
];
foreach ($cohorts as $cohort) {
    $cohortoptions[$cohort->id] = format_string($cohort->name);
}
echo html_writer::select($cohortoptions, 'cohortid', $cohortid, false, ['id' => 'id_cohortid', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div('menteesplus-filter menteesplus-filter-status');
echo html_writer::label(get_string('statusfilter', 'block_menteesplus'), 'id_status', true, ['class' => 'form-label']);
$statusoptions = [
    'all' => get_string('statusall', 'block_menteesplus'),
    'active' => get_string('statusactive', 'block_menteesplus'),
    'suspended' => get_string('statussuspended', 'block_menteesplus'),
];
echo html_writer::select($statusoptions, 'status', $status, false, ['id' => 'id_status', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div('menteesplus-filter menteesplus-filter-perpage');
echo html_writer::label(get_string('perpage', 'block_menteesplus'), 'id_perpage', true, ['class' => 'form-label']);
echo html_writer::select([25 => '25', 50 => '50', 100 => '100'], 'perpage', $perpage, false, [
    'id' => 'id_perpage',
    'class' => 'form-select',
]);
echo html_writer::end_div();

echo html_writer::start_div('menteesplus-filter-actions');
echo html_writer::tag('button', get_string('applyfilters', 'block_menteesplus'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
$reseturl = new moodle_url('/blocks/menteesplus/index.php');
echo html_writer::link($reseturl, get_string('resetfilters', 'block_menteesplus'), [
    'class' => 'btn btn-outline-secondary',
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::div(
    get_string('resultcount', 'block_menteesplus', $total),
    'mb-3 text-muted menteesplus-result-count'
);

if (!$users) {
    echo $OUTPUT->notification(get_string('nomatches', 'block_menteesplus'), 'info', false);
} else {
    echo html_writer::start_div('menteesplus-results', ['role' => 'table']);

    echo html_writer::start_div('menteesplus-results-header', ['role' => 'row']);
    echo html_writer::div(get_string('student', 'block_menteesplus'), 'menteesplus-cell menteesplus-cell-student', [
        'role' => 'columnheader',
    ]);
    echo html_writer::div(get_string('cohorts', 'block_menteesplus'), 'menteesplus-cell menteesplus-cell-cohorts', [
        'role' => 'columnheader',
    ]);
    echo html_writer::div(get_string('accountstatus', 'block_menteesplus'), 'menteesplus-cell menteesplus-cell-status', [
        'role' => 'columnheader',
    ]);
    echo html_writer::div(get_string('actions', 'block_menteesplus'), 'menteesplus-cell menteesplus-cell-actions', [
        'role' => 'columnheader',
    ]);
    echo html_writer::end_div();

    foreach ($users as $user) {
        $profileurl = \core\user::get_profile_url($user);
        $displayname = \core\user::get_fullname($user, $context);
        $messageurl = new moodle_url('/message/index.php', ['id' => $user->id]);

        if ((int) $user->picture > 0) {
            $avatar = $OUTPUT->user_picture($user, [
                'size' => 44,
                'class' => 'rounded-circle menteesplus-avatar-image',
                'alttext' => false,
            ]);
        } else {
            $initials = core_text::strtoupper(
                core_text::substr($user->firstname, 0, 1) . core_text::substr($user->lastname, 0, 1)
            );
            $avatar = html_writer::span(s($initials), 'menteesplus-avatar-fallback', [
                'aria-hidden' => 'true',
            ]);
        }

        if ($profileurl) {
            $name = html_writer::link($profileurl, $displayname, ['class' => 'menteesplus-student-name']);
        } else {
            $name = html_writer::span($displayname, 'menteesplus-student-name');
        }

        $identity = '';
        if ($canviewidentity) {
            $email = html_writer::div(
                html_writer::span(get_string('email') . ': ', 'menteesplus-identity-label') . s($user->email),
                'menteesplus-identity-item'
            );
            $username = html_writer::div(
                html_writer::span(get_string('username') . ': ', 'menteesplus-identity-label') . s($user->username),
                'menteesplus-identity-item'
            );
            $identity = html_writer::div($email . $username, 'menteesplus-identity');
        }

        $studenthtml = html_writer::div(
            $avatar . html_writer::div($name . $identity, 'menteesplus-student-main'),
            'menteesplus-student'
        );

        $cohorthtml = '';
        $usercohorts = $cohortsbyuser[$user->id] ?? [];
        if ($usercohorts) {
            $badges = [];
            foreach ($usercohorts as $cohort) {
                $title = $cohort->idnumber ? $cohort->name . ' (' . $cohort->idnumber . ')' : $cohort->name;
                $badges[] = html_writer::span(s($title), 'badge rounded-pill text-bg-light border menteesplus-cohort-badge');
            }
            $cohorthtml = html_writer::div(implode('', $badges), 'menteesplus-cohort-list');
        } else {
            $cohorthtml = html_writer::span(get_string('nocohort', 'block_menteesplus'), 'text-muted');
        }

        if ($user->suspended) {
            $statushtml = html_writer::span(
                get_string('statussuspended', 'block_menteesplus'),
                'badge text-bg-secondary'
            );
        } else {
            $statushtml = html_writer::span(
                get_string('statusactive', 'block_menteesplus'),
                'badge text-bg-success'
            );
        }

        $actionlinks = [];
        if ($profileurl) {
            $actionlinks[] = html_writer::link($profileurl, get_string('openprofile', 'block_menteesplus'), [
                'class' => 'btn btn-sm btn-outline-primary',
            ]);
        }
        $actionlinks[] = html_writer::link($messageurl, get_string('sendmessage', 'block_menteesplus'), [
            'class' => 'btn btn-sm btn-outline-secondary',
        ]);
        $actions = html_writer::div(implode('', $actionlinks), 'menteesplus-actions');

        echo html_writer::start_div('menteesplus-result-row', ['role' => 'row']);

        echo html_writer::start_div('menteesplus-cell menteesplus-cell-student', ['role' => 'cell']);
        echo html_writer::span(get_string('student', 'block_menteesplus'), 'menteesplus-mobile-label');
        echo $studenthtml;
        echo html_writer::end_div();

        echo html_writer::start_div('menteesplus-cell menteesplus-cell-cohorts', ['role' => 'cell']);
        echo html_writer::span(get_string('cohorts', 'block_menteesplus'), 'menteesplus-mobile-label');
        echo $cohorthtml;
        echo html_writer::end_div();

        echo html_writer::start_div('menteesplus-cell menteesplus-cell-status', ['role' => 'cell']);
        echo html_writer::span(get_string('accountstatus', 'block_menteesplus'), 'menteesplus-mobile-label');
        echo $statushtml;
        echo html_writer::end_div();

        echo html_writer::start_div('menteesplus-cell menteesplus-cell-actions', ['role' => 'cell']);
        echo html_writer::span(get_string('actions', 'block_menteesplus'), 'menteesplus-mobile-label');
        echo $actions;
        echo html_writer::end_div();

        echo html_writer::end_div();
    }

    echo html_writer::end_div();

    $pagingurl = new moodle_url('/blocks/menteesplus/index.php', [
        'q' => $q,
        'cohortid' => $cohortid,
        'status' => $status,
        'perpage' => $perpage,
    ]);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $pagingurl);
}

echo $OUTPUT->footer();
