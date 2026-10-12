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
 * Tests for the mentee repository.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_menteesplus\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the mentee repository.
 *
 * @package    block_menteesplus
 * @category   test
 * @copyright  2026 MIA Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(mentee_repository::class)]
final class mentee_repository_test extends \advanced_testcase {
    /**
     * Test mentee discovery, search, cohort filtering, and account status filtering.
     *
     * @return void
     */
    public function test_filters(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/cohort/lib.php');

        $this->resetAfterTest(true);

        $mentor = $this->getDataGenerator()->create_user([
            'firstname' => 'Mentor',
            'lastname' => 'One',
        ]);
        $studentone = $this->getDataGenerator()->create_user([
            'firstname' => 'Anna',
            'lastname' => 'Alpha',
            'email' => 'anna.alpha@example.test',
        ]);
        $studenttwo = $this->getDataGenerator()->create_user([
            'firstname' => 'Boris',
            'lastname' => 'Beta',
            'email' => 'boris.beta@example.test',
        ]);

        $roleid = create_role('Mentor test role', 'mentortest', 'Mentor test role');
        role_assign($roleid, $mentor->id, \context_user::instance($studentone->id)->id);
        role_assign($roleid, $mentor->id, \context_user::instance($studenttwo->id)->id);

        $cohortid = cohort_add_cohort((object) [
            'contextid' => \context_system::instance()->id,
            'name' => 'Test cohort',
            'idnumber' => 'test-cohort',
        ]);
        cohort_add_member($cohortid, $studentone->id);

        $DB->set_field('user', 'suspended', 1, ['id' => $studenttwo->id]);

        $repository = new mentee_repository();

        $all = $repository->get_mentees($mentor->id, '', 0, 'all', false, 0, 25);
        $this->assertSame(2, $all['total']);
        $this->assertCount(2, $all['users']);

        $bycohort = $repository->get_mentees($mentor->id, '', $cohortid, 'all', false, 0, 25);
        $this->assertSame(1, $bycohort['total']);
        $this->assertArrayHasKey($studentone->id, $bycohort['users']);

        $withoutcohort = $repository->get_mentees($mentor->id, '', -1, 'all', false, 0, 25);
        $this->assertSame(1, $withoutcohort['total']);
        $this->assertArrayHasKey($studenttwo->id, $withoutcohort['users']);

        $suspended = $repository->get_mentees($mentor->id, '', 0, 'suspended', false, 0, 25);
        $this->assertSame(1, $suspended['total']);
        $this->assertArrayHasKey($studenttwo->id, $suspended['users']);

        $name = $repository->get_mentees($mentor->id, 'Alpha', 0, 'all', false, 0, 25);
        $this->assertSame(1, $name['total']);
        $this->assertArrayHasKey($studentone->id, $name['users']);

        $emailhidden = $repository->get_mentees($mentor->id, 'anna.alpha@example.test', 0, 'all', false, 0, 25);
        $this->assertSame(0, $emailhidden['total']);

        // Requested identity fields must never bypass Moodle permissions or site settings.
        set_config('showuseridentity', 'email');
        $withoutpermission = $repository->get_mentees($mentor->id, 'anna.alpha@example.test', 0, 'all', true, 0, 25);
        $this->assertSame(0, $withoutpermission['total']);

        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $mentor->id, \context_system::instance()->id);

        $emailvisible = $repository->get_mentees($mentor->id, 'anna.alpha@example.test', 0, 'all', true, 0, 25);
        $this->assertSame(1, $emailvisible['total']);
        $this->assertArrayHasKey($studentone->id, $emailvisible['users']);

        $usernamehidden = $repository->get_mentees($mentor->id, $studentone->username, 0, 'all', true, 0, 25);
        $this->assertSame(0, $usernamehidden['total']);

        set_config('showuseridentity', '');
        $emaildisallowed = $repository->get_mentees($mentor->id, 'anna.alpha@example.test', 0, 'all', true, 0, 25);
        $this->assertSame(0, $emaildisallowed['total']);

        $cohorts = $repository->get_cohorts_for_users([$studentone->id, $studenttwo->id]);
        $this->assertArrayHasKey($studentone->id, $cohorts);
        $studentcohorts = $cohorts[$studentone->id];
        $firstcohort = reset($studentcohorts);
        $this->assertSame('Test cohort', $firstcohort->name);
        $this->assertArrayNotHasKey($studenttwo->id, $cohorts);
    }
}
