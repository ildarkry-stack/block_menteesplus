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
 * Regression tests for isolated mentee visibility.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_menteesplus\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Ensure filters and inherited roles never leak unrelated students.
 *
 * @package   block_menteesplus
 * @category  test
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(mentee_repository::class)]
final class mentee_visibility_security_test extends \advanced_testcase {
    /**
     * List only users assigned directly to the current mentor.
     *
     * @return void
     */
    public function test_unrelated_student_never_appears_in_mentee_results(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $mentor = $generator->create_user();
        $assigned = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Assigned']);
        $other = $generator->create_user(['firstname' => 'Boris', 'lastname' => 'Other']);
        $roleid = create_role('Mentee visibility', 'menteesvisibility', 'Mentee visibility');
        role_assign($roleid, $mentor->id, \context_user::instance($assigned->id)->id);

        $repository = new mentee_repository();
        $all = $repository->get_mentees($mentor->id);
        $this->assertSame(1, $all['total']);
        $this->assertArrayHasKey($assigned->id, $all['users']);
        $this->assertArrayNotHasKey($other->id, $all['users']);

        $search = $repository->get_mentees($mentor->id, 'Boris');
        $this->assertSame(0, $search['total']);
    }

    /**
     * Global or inherited role assignments must not list every Moodle user.
     *
     * @return void
     */
    public function test_system_role_does_not_create_mentee_relationship(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $mentor = $generator->create_user();
        $generator->create_user();
        $roleid = create_role('Mentee visibility', 'menteesvisibility', 'Mentee visibility');
        role_assign($roleid, $mentor->id, \context_system::instance()->id);

        $repository = new mentee_repository();
        $all = $repository->get_mentees($mentor->id);
        $this->assertSame(0, $all['total']);
        $this->assertEmpty($all['users']);
    }

    /**
     * Pagination must preserve the same visibility boundary.
     *
     * @return void
     */
    public function test_pagination_contains_only_assigned_students(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $mentor = $generator->create_user();
        $first = $generator->create_user(['firstname' => 'Anna', 'lastname' => 'Alpha']);
        $second = $generator->create_user(['firstname' => 'Boris', 'lastname' => 'Beta']);
        $unrelated = $generator->create_user(['firstname' => 'Zoe', 'lastname' => 'Zulu']);
        $roleid = create_role('Mentee visibility', 'menteesvisibility', 'Mentee visibility');
        role_assign($roleid, $mentor->id, \context_user::instance($first->id)->id);
        role_assign($roleid, $mentor->id, \context_user::instance($second->id)->id);

        $repository = new mentee_repository();
        $page = $repository->get_mentees($mentor->id, '', 0, 'all', false, 1, 1);
        $this->assertSame(2, $page['total']);
        $this->assertCount(1, $page['users']);
        $this->assertArrayHasKey($second->id, $page['users']);
        $this->assertArrayNotHasKey($unrelated->id, $page['users']);
    }
}
