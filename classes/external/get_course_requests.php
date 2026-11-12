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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursefiles\local\requests;

/**
 * Lists pending course requests: all of them for approvers (all=1), otherwise the caller's own.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_requests extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'all' => new external_value(PARAM_INT, '1 = all pending requests (needs moodle/site:approvecourse)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $all
     * @return array
     */
    public static function execute(int $all = 0): array {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['all' => $all]);
        $context = \context_system::instance();
        self::validate_context($context);
        requests::classname();
        if ($params['all']) {
            require_capability('moodle/site:approvecourse', $context);
            $records = $DB->get_records('course_request', null, 'id');
        } else {
            $records = $DB->get_records('course_request', ['requester' => $USER->id], 'id');
        }
        return [
            'enabled' => empty($CFG->enablecourserequests) ? 0 : 1,
            'requests' => array_values(array_map([requests::class, 'export'], $records)),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_INT, '1 if course requests are enabled on the site'),
            'requests' => new external_multiple_structure(requests::structure()),
        ]);
    }
}
