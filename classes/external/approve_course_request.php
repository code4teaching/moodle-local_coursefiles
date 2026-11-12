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
namespace local_coursefiles\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursefiles\local\requests;

/**
 * Approves a course request, optionally with a corrected name and another category. Moodle creates the course,
 * enrols the requester as teacher and notifies them.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class approve_course_request extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, 'Request ID'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name (empty = as requested)', VALUE_DEFAULT, ''),
            'shortname' => new external_value(PARAM_TEXT, 'Short name (empty = as requested)', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Category for the course (0 = as requested)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $requestid
     * @param string $fullname
     * @param string $shortname
     * @param int $categoryid
     * @return array
     */
    public static function execute(int $requestid, string $fullname = '', string $shortname = '', int $categoryid = 0): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'requestid' => $requestid, 'fullname' => $fullname, 'shortname' => $shortname, 'categoryid' => $categoryid,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:approvecourse', $context);

        $record = $DB->get_record('course_request', ['id' => $params['requestid']]);
        if (!$record) {
            throw new \moodle_exception('unknowncourserequest', 'error');
        }
        $update = (object)['id' => $record->id];
        if (trim($params['fullname']) !== '') {
            $update->fullname = trim($params['fullname']);
        }
        if (trim($params['shortname']) !== '') {
            $update->shortname = trim($params['shortname']);
        }
        if ($params['categoryid']) {
            $category = \core_course_category::get($params['categoryid'], MUST_EXIST, true);
            require_capability('moodle/site:approvecourse', $category->get_context());
            $update->category = $category->id;
        }
        requests::check_shortname($update->shortname ?? $record->shortname, (int)$record->id);

        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('course_request', $update);
        $request = requests::load((int)$record->id);
        if (!$request->can_approve()) {
            throw new \required_capability_exception($context, 'moodle/site:approvecourse', 'nopermissions', '');
        }
        $courseid = $request->approve();
        $transaction->allow_commit();

        $course = get_course($courseid);
        return ['courseid' => (int)$courseid, 'fullname' => $course->fullname, 'shortname' => $course->shortname];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'ID of the new course'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name of the new course'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name of the new course'),
        ]);
    }
}
