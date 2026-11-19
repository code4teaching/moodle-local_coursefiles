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

/**
 * Shows or hides a whole course for students. Only this one setting: core_course_update_courses can change much more
 * (names, category, dates), which teachers should not get through an app just to show their course.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_course_visibility extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)'),
        ]);
    }

    /**
     * Shows or hides the course.
     *
     * @param int $courseid
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $visible): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'visible' => $visible]);
        if ($params['courseid'] == SITEID) {
            throw new \moodle_exception('invalidcourseid');
        }
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:visibility', $context);

        course_change_visibility($course->id, (bool)$params['visible']);
        return ['courseid' => (int)$course->id, 'visible' => (int)get_course($course->id)->visible];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)'),
        ]);
    }
}
