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
 * Shows or hides any activity or resource of a course (core_course_edit_module is not available to web services).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_visibility extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)'),
        ]);
    }

    /**
     * Shows or hides the activity.
     *
     * @param int $cmid
     * @param int $visible
     * @return array
     */
    public static function execute(int $cmid, int $visible): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'visible' => $visible]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:activityvisibility', $context);

        $visible = $params['visible'] ? 1 : 0;
        set_coursemodule_visible($cm->id, $visible);
        $updated = get_coursemodule_from_id('', $cm->id, 0, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($updated, $context)->trigger();

        return ['cmid' => (int)$cm->id, 'visible' => $visible];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)'),
        ]);
    }
}
