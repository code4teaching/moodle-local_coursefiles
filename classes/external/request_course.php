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
 * Asks for a new course (Moodle course request). An approver creates it later.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_course extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name of the course (unique)'),
            'reason' => new external_value(PARAM_TEXT, 'Reason or note for the approver', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param string $fullname
     * @param string $shortname
     * @param string $reason
     * @return array
     */
    public static function execute(string $fullname, string $shortname, string $reason = ''): array {
        global $CFG;
        $params = self::validate_parameters(self::execute_parameters(), [
            'fullname' => $fullname, 'shortname' => $shortname, 'reason' => $reason,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        $cls = requests::classname();
        if (empty($CFG->enablecourserequests)) {
            throw new \moodle_exception('courserequestdisabled', 'error');
        }
        if (!$cls::can_request($context)) {
            throw new \required_capability_exception($context, 'moodle/course:request', 'nopermissions', '');
        }
        $full = trim($params['fullname']);
        $short = trim($params['shortname']);
        if ($full === '' || $short === '') {
            throw new \invalid_parameter_exception('fullname and shortname are required');
        }
        requests::check_shortname($short);

        $data = (object)[
            'fullname' => $full,
            'shortname' => $short,
            'reason' => $params['reason'],
            'category' => (int)($CFG->defaultrequestcategory ?? 0),
            'summary_editor' => ['text' => '', 'format' => FORMAT_HTML],
        ];
        $request = $cls::create($data);
        return ['requestid' => (int)$request->id];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'requestid' => new external_value(PARAM_INT, 'ID of the pending request'),
        ]);
    }
}
