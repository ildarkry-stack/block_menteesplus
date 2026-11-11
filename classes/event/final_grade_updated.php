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
 * Event for a restricted mentor final grade update.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_menteesplus\event;

/**
 * Logged when a mentor changes an assigned student's course-total grade.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class final_grade_updated extends \core\event\base {
    /**
     * Define Moodle event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grade_grades';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventfinalgradeupdated', 'block_menteesplus');
    }

    /**
     * Event description for the standard logs.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' updated the course-total grade item with id " .
            "'{$this->other['gradeitemid']}' for the mentee with id '{$this->relateduserid}'.";
    }

    /**
     * Link to the affected course.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }
}
