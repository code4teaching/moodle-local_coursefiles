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
 * Creates a section in a course, at the end or at a given position.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name' => new external_value(PARAM_TEXT, 'Section name'),
            'summary' => new external_value(PARAM_RAW, 'Summary (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 0),
            'position' => new external_value(PARAM_INT, 'Section number to insert at (0 = at the end)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $courseid
     * @param string $name
     * @param string $summary
     * @param int $visible
     * @param int $position
     * @return array
     */
    public static function execute(
        int $courseid,
        string $name,
        string $summary = '',
        int $visible = 0,
        int $position = 0
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'name' => $name, 'summary' => $summary,
            'visible' => $visible, 'position' => $position,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);
        if ($params['position'] < 0) {
            throw new \invalid_parameter_exception('position');
        }

        $actions = \core_courseformat\formatactions::section($course);
        $section = $actions->create(max(0, $params['position']));
        $info = get_fast_modinfo($course->id)->get_section_info_by_id($section->id, MUST_EXIST);
        $actions->update($info, [
            'name' => $params['name'],
            'summary' => $params['summary'],
            'summaryformat' => FORMAT_HTML,
            'visible' => $params['visible'] ? 1 : 0,
        ]);
        $info = get_fast_modinfo($course->id)->get_section_info_by_id($section->id, MUST_EXIST);

        return ['sectionid' => (int)$info->id, 'sectionnum' => (int)$info->sectionnum];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Section ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (position in the course)'),
        ]);
    }
}
