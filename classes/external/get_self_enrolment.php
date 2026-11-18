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
use local_coursefiles\local\selfenrol;

/**
 * Reads the self enrolment of a course: on or off and the enrolment key (Moodle core only offers this to students).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_self_enrolment extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Reads the self enrolment.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('enrol/self:config', $context);
        return selfenrol::describe($course, selfenrol::instance($course->id));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::structure();
    }

    /**
     * Shared return structure of get and set.
     *
     * @return external_single_structure
     */
    public static function structure(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'siteenabled' => new external_value(PARAM_INT, '1 if the self enrolment plugin is enabled on the site'),
            'exists' => new external_value(PARAM_INT, '1 if the course has a self enrolment method'),
            'instanceid' => new external_value(PARAM_INT, 'ID of the method, 0 if none'),
            'enabled' => new external_value(PARAM_INT, '1 if students can enrol themselves'),
            'password' => new external_value(PARAM_RAW, 'Enrolment key, empty if none'),
            'coursevisible' => new external_value(PARAM_INT, '1 if the course is visible to students'),
            'url' => new external_value(PARAM_URL, 'Where students enrol'),
        ]);
    }
}
