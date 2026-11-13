<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_coursefiles\local;

/**
 * Gives a site role (e.g. "Web service user") to everybody who teaches in a course (site admins need no role).
 *
 * Capabilities such as moodle/webservice:createtoken are checked in the system context, so a course role
 * like Teacher cannot grant them. This keeps a system role in step with the teacher role in courses.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacherrole {
    /**
     * The configured roles, or null when the feature is off.
     *
     * @return array|null [teacher role id, site role id]
     */
    public static function roles(): ?array {
        $teacher = (int)get_config('local_coursefiles', 'teacherrole');
        $site = (int)get_config('local_coursefiles', 'siterole');
        if (!$teacher || !$site || $teacher === $site) {
            return null;
        }
        return [$teacher, $site];
    }

    /**
     * Gives the site role to one user if they do not have it yet.
     *
     * @param int $userid
     * @return bool true if the role was assigned now
     */
    public static function grant(int $userid): bool {
        global $DB;
        $roles = self::roles();
        if (!$roles || isguestuser($userid) || is_siteadmin($userid)) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user) {
            return false;
        }
        $system = \context_system::instance();
        if (user_has_role_assignment($userid, $roles[1], $system->id)) {
            return false;
        }
        role_assign($roles[1], $userid, $system->id, 'local_coursefiles');
        return true;
    }

    /**
     * Gives the site role to everybody who has the teacher role in any course.
     *
     * @return int number of users who got the role now
     */
    public static function sync(): int {
        global $DB;
        $roles = self::roles();
        if (!$roles) {
            return 0;
        }
        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :course
                 WHERE ra.roleid = :teacher";
        $userids = $DB->get_fieldset_sql($sql, ['course' => CONTEXT_COURSE, 'teacher' => $roles[0]]);
        $count = 0;
        foreach ($userids as $userid) {
            if (self::grant((int)$userid)) {
                $count++;
            }
        }
        return $count;
    }
}
