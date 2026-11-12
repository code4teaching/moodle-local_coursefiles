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
 * Helpers for course requests (Moodle core has no web service functions for them).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requests {
    /**
     * Name of the core class: \core_course\course_request since Moodle 5.2, \course_request before.
     *
     * @return string
     */
    public static function classname(): string {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        return class_exists('\\core_course\\course_request') ? '\\core_course\\course_request' : '\\course_request';
    }

    /**
     * Loads a pending request.
     *
     * @param int $id
     * @return object course_request
     */
    public static function load(int $id) {
        $cls = self::classname();
        return new $cls($id);
    }

    /**
     * Throws if the short name is used by a course or by another pending request.
     *
     * @param string $shortname
     * @param int $exceptrequest request id to ignore
     */
    public static function check_shortname(string $shortname, int $exceptrequest = 0): void {
        global $DB;
        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            throw new \moodle_exception('shortnametaken', 'error', '', $shortname);
        }
        $sql = 'shortname = :s AND id <> :id';
        if ($DB->record_exists_select('course_request', $sql, ['s' => $shortname, 'id' => $exceptrequest])) {
            throw new \moodle_exception('shortnametaken', 'error', '', $shortname);
        }
    }

    /**
     * Data of a request for the web service.
     *
     * @param \stdClass $r record from course_request
     * @return array
     */
    public static function export(\stdClass $r): array {
        global $DB;
        $user = $DB->get_record('user', ['id' => $r->requester]);
        $cat = $r->category ? \core_course_category::get($r->category, IGNORE_MISSING, true) : null;
        return [
            'id' => (int)$r->id,
            'fullname' => $r->fullname,
            'shortname' => $r->shortname,
            'reason' => (string)$r->reason,
            'categoryid' => $cat ? (int)$cat->id : 0,
            'categoryname' => $cat ? $cat->get_nested_name(false) : '',
            'requesterid' => (int)$r->requester,
            'requestername' => $user ? fullname($user) : '',
        ];
    }

    /**
     * Return structure of one request.
     *
     * @return \core_external\external_single_structure
     */
    public static function structure(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new \core_external\external_value(PARAM_INT, 'Request ID'),
            'fullname' => new \core_external\external_value(PARAM_TEXT, 'Requested full name'),
            'shortname' => new \core_external\external_value(PARAM_TEXT, 'Requested short name'),
            'reason' => new \core_external\external_value(PARAM_TEXT, 'Reason given by the requester'),
            'categoryid' => new \core_external\external_value(PARAM_INT, 'Category (0 = default request category)'),
            'categoryname' => new \core_external\external_value(PARAM_TEXT, 'Category name'),
            'requesterid' => new \core_external\external_value(PARAM_INT, 'User who asked for the course'),
            'requestername' => new \core_external\external_value(PARAM_TEXT, 'Name of the requester'),
        ]);
    }
}
