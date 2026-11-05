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
 * Moves a course section to another position; the activities move with it.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Current section number'),
            'position' => new external_value(PARAM_INT, 'New section number (1 or higher)'),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param int $position
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        int $position
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'sectionnum' => $sectionnum, 'position' => $position,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:movesections', $context);
        require_capability('moodle/course:update', $context);

        $modinfo = get_fast_modinfo($course->id);
        $last = max(array_keys($modinfo->get_section_info_all()));
        if ($params['sectionnum'] < 1 || $params['position'] < 1 || $params['position'] > $last) {
            throw new \invalid_parameter_exception('Sections can be moved between 1 and ' . $last . '.');
        }
        $info = $modinfo->get_section_info($params['sectionnum'], MUST_EXIST);
        if ($params['position'] !== $params['sectionnum']) {
            $actions = \core_courseformat\formatactions::section($course);
            if (method_exists($actions, 'move_at')) {
                $actions->move_at($info, $params['position']);
            } else {
                move_section_to($course, $params['sectionnum'], $params['position'], true);
            }
        }
        $info = get_fast_modinfo($course->id)->get_section_info_by_id($info->id, MUST_EXIST);

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
