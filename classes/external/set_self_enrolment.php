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
 * Turns self enrolment of a course on or off and sets its enrolment key. Adds the method (role: student, site
 * defaults) if the course has none. Moodle core offers no web service for this.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_self_enrolment extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'enabled' => new external_value(PARAM_INT, '1 = students can enrol themselves, 0 = off'),
            'password' => new external_value(PARAM_RAW, 'Enrolment key, empty = none', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Sets the self enrolment.
     *
     * @param int $courseid
     * @param int $enabled
     * @param string $password
     * @return array
     */
    public static function execute(int $courseid, int $enabled, string $password = ''): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'enabled' => $enabled, 'password' => $password]
        );
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('enrol/self:config', $context);

        $password = trim($params['password']);
        if (\core_text::strlen($password) > 50) {
            throw new \invalid_parameter_exception('The enrolment key is too long (at most 50 characters).');
        }
        $instance = selfenrol::set($course, (bool)$params['enabled'], $password);
        return selfenrol::describe($course, $instance);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_self_enrolment::structure();
    }
}
