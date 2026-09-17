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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Data access for mentees and cohort memberships.
 *
 * @package   block_menteesplus
 * @copyright 2026 MIA Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mentee_repository {
    /**
     * Return mentees assigned to the mentor in user contexts.
     *
     * @param int $mentorid Mentor user id.
     * @param string $search Search string.
     * @param int $cohortid Cohort id, 0 for all, -1 for no cohort.
     * @param string $status all, active, or suspended.
     * @param bool $includeidentity Include email and username in search.
     * @param int $offset Result offset.
     * @param int $limit Result limit.
     * @return array{users: array, total: int}
     */
    public function get_mentees(
        int $mentorid,
        string $search = '',
        int $cohortid = 0,
        string $status = 'all',
        bool $includeidentity = false,
        int $offset = 0,
        int $limit = 25
    ): array {
        global $DB;

        $params = [
            'mentorid' => $mentorid,
            'contextlevel' => CONTEXT_USER,
            'mentoruser' => $mentorid,
        ];

        $where = [
            'u.deleted = 0',
            'u.id <> :mentoruser',
        ];

        $search = trim($search);
        if ($search !== '') {
            $likeparts = [];
            $searchfields = ['firstname', 'lastname', 'middlename', 'alternatename'];
            if ($includeidentity) {
                $searchfields[] = 'email';
                $searchfields[] = 'username';
            }
            foreach ($searchfields as $field) {
                $paramname = 'search_' . $field;
                $likeparts[] = $DB->sql_like('u.' . $field, ':' . $paramname, false, false);
                $params[$paramname] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $fullnameparam = 'search_fullname';
            $fullnamesql = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $likeparts[] = $DB->sql_like($fullnamesql, ':' . $fullnameparam, false, false);
            $params[$fullnameparam] = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . implode(' OR ', $likeparts) . ')';
        }

        if ($cohortid > 0) {
            $where[] = 'EXISTS (
                SELECT 1
                  FROM {cohort_members} cmfilter
                 WHERE cmfilter.userid = u.id
                   AND cmfilter.cohortid = :cohortid
            )';
            $params['cohortid'] = $cohortid;
        } elseif ($cohortid === -1) {
            $where[] = 'NOT EXISTS (
                SELECT 1
                  FROM {cohort_members} cmfilter
                 WHERE cmfilter.userid = u.id
            )';
        }

        if ($status === 'active') {
            $where[] = 'u.suspended = 0';
        } elseif ($status === 'suspended') {
            $where[] = 'u.suspended = 1';
        }

        $from = 'FROM {user} u
                JOIN {context} ctx
                  ON ctx.instanceid = u.id
                 AND ctx.contextlevel = :contextlevel
                JOIN {role_assignments} ra
                  ON ra.contextid = ctx.id
                 AND ra.userid = :mentorid';

        $wheresql = implode(' AND ', $where);

        $countsql = "SELECT COUNT(DISTINCT u.id)
                       {$from}
                      WHERE {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $params);

        $fieldsql = 'u.id, u.username, u.email, u.firstname, u.lastname, u.firstnamephonetic,
                     u.lastnamephonetic, u.middlename, u.alternatename, u.picture, u.imagealt,
                     u.suspended';

        $sql = "SELECT DISTINCT {$fieldsql}
                  {$from}
                 WHERE {$wheresql}
              ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";

        $users = $DB->get_records_sql($sql, $params, $offset, $limit);

        return [
            'users' => $users,
            'total' => $total,
        ];
    }

    /**
     * Return cohorts for a set of users in one query.
     *
     * @param int[] $userids User ids.
     * @return array<int, array<int, \stdClass>>
     */
    public function get_cohorts_for_users(array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        $sql = "SELECT cm.id AS membershipid, cm.userid, c.id, c.name, c.idnumber, c.visible
                  FROM {cohort_members} cm
                  JOIN {cohort} c ON c.id = cm.cohortid
                 WHERE cm.userid {$insql}
              ORDER BY c.name ASC";

        $records = $DB->get_records_sql($sql, $params);
        $result = [];

        foreach ($records as $record) {
            if (!isset($result[$record->userid])) {
                $result[$record->userid] = [];
            }
            $result[$record->userid][] = $record;
        }

        return $result;
    }

    /**
     * Return all cohorts represented among this mentor's mentees.
     *
     * @param int $mentorid Mentor user id.
     * @return array<int, \stdClass>
     */
    public function get_available_cohorts(int $mentorid): array {
        global $DB;

        $sql = 'SELECT DISTINCT c.id, c.name, c.idnumber
                  FROM {cohort} c
                  JOIN {cohort_members} cm ON cm.cohortid = c.id
                  JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
                  JOIN {context} ctx
                    ON ctx.instanceid = u.id
                   AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra
                    ON ra.contextid = ctx.id
                   AND ra.userid = :mentorid
                 WHERE u.id <> :mentoruser
              ORDER BY c.name ASC';

        return $DB->get_records_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'mentorid' => $mentorid,
            'mentoruser' => $mentorid,
        ]);
    }
}
