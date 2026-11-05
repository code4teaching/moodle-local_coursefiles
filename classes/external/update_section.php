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
 * Changes name, summary or visibility of a course section; values not given stay as they are.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'New name (null = keep)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'summary' => new external_value(PARAM_RAW, 'New summary in HTML (null = keep)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'visible' => new external_value(PARAM_INT, 'Visible (1), hidden (0) or keep (-1)', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string|null $name
     * @param string|null $summary
     * @param int $visible
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        ?string $name = null,
        ?string $summary = null,
        int $visible = -1
    ): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'sectionnum' => $sectionnum, 'name' => $name,
            'summary' => $summary, 'visible' => $visible,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:update', $context);

        $info = get_fast_modinfo($course->id)->get_section_info($params['sectionnum'], MUST_EXIST);
        $fields = [];
        if ($params['name'] !== null) {
            $fields['name'] = $params['name'];
        }
        if ($params['summary'] !== null) {
            $fields['summary'] = $params['summary'];
            $fields['summaryformat'] = FORMAT_HTML;
        }
        if ($params['visible'] !== -1) {
            require_capability('moodle/course:sectionvisibility', $context);
            $fields['visible'] = $params['visible'] ? 1 : 0;
        }
        if ($fields) {
            \core_courseformat\formatactions::section($course)->update($info, $fields);
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
