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
 * Mentees Plus block.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_menteesplus\local\mentee_repository;
use core\user as core_user;

/**
 * Mentees Plus block implementation.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_menteesplus extends block_base {
    /**
     * Initialise the block.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_menteesplus');
    }

    /**
     * Return block content.
     *
     * @return stdClass|null
     */
    public function get_content(): ?stdClass {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $limit = (int) get_config('block_menteesplus', 'blocklimit');
        if ($limit < 1) {
            $limit = 8;
        }

        $repository = new mentee_repository();
        $result = $repository->get_mentees($USER->id, '', 0, 'all', false, 0, $limit);
        $users = $result['users'];
        $total = $result['total'];

        if (!$users) {
            $this->content->text = html_writer::div(
                get_string('nomentees', 'block_menteesplus'),
                'text-muted small'
            );
            return $this->content;
        }

        $cohortsbyuser = $repository->get_cohorts_for_users(array_keys($users));
        $items = [];

        foreach ($users as $user) {
            $profileurl = core_user::get_profile_url($user);
            $displayname = core_user::get_fullname($user, $this->context);
            if ((int) $user->picture > 0) {
                $picture = $OUTPUT->user_picture($user, [
                    'size' => 35,
                    'class' => 'rounded-circle menteesplus-block-avatar-image',
                    'alttext' => false,
                ]);
            } else {
                $initials = core_text::strtoupper(
                    core_text::substr($user->firstname, 0, 1) . core_text::substr($user->lastname, 0, 1)
                );
                $picture = html_writer::span(s($initials), 'menteesplus-block-avatar-fallback', [
                    'aria-hidden' => 'true',
                ]);
            }
            if ($profileurl) {
                $name = html_writer::link($profileurl, $displayname, ['class' => 'fw-semibold']);
            } else {
                $name = html_writer::span($displayname, 'fw-semibold');
            }

            $cohorthtml = '';
            $usercohorts = $cohortsbyuser[$user->id] ?? [];
            if ($usercohorts) {
                $badges = [];
                $shown = 0;
                foreach ($usercohorts as $cohort) {
                    if ($shown >= 2) {
                        break;
                    }
                    $badges[] = html_writer::span(s($cohort->name), 'badge rounded-pill text-bg-light border me-1');
                    $shown++;
                }
                if (count($usercohorts) > 2) {
                    $badges[] = html_writer::span('+' . (count($usercohorts) - 2), 'badge rounded-pill text-bg-secondary');
                }
                $cohorthtml = html_writer::div(implode('', $badges), 'mt-1');
            } else {
                $cohorthtml = html_writer::div(get_string('nocohort', 'block_menteesplus'), 'small text-muted mt-1');
            }

            $details = html_writer::div($name . $cohorthtml, 'flex-grow-1 min-w-0');
            $items[] = html_writer::div($picture . $details, 'd-flex align-items-start gap-2 py-2 menteesplus-item');
        }

        $this->content->text = html_writer::div(implode('', $items), 'menteesplus-list');

        $url = new moodle_url('/blocks/menteesplus/index.php');
        $label = get_string('viewall', 'block_menteesplus', $total);
        $this->content->footer = html_writer::link($url, $label, ['class' => 'btn btn-outline-primary btn-sm w-100']);

        return $this->content;
    }

    /**
     * Allow the block on the user dashboard and site pages.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'all' => true,
            'tag' => false,
        ];
    }

    /**
     * Do not allow multiple instances on one page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * This plugin has global settings.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }
}
