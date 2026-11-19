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
 * Changes the due date of an assignment (for everybody; user and group overrides stay).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_assign_duedate extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'duedate' => new external_value(PARAM_INT, 'New due date (timestamp, 0 = none)'),
        ]);
    }

    /**
     * Sets the due date.
     *
     * @param int $cmid
     * @param int $duedate
     * @return array
     */
    public static function execute(int $cmid, int $duedate): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'duedate' => $duedate]);
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $record = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $record->duedate = $params['duedate'];
        // A cut-off date before the new due date would block submissions, so it goes.
        if ($record->cutoffdate && $params['duedate'] && $record->cutoffdate < $params['duedate']) {
            $record->cutoffdate = 0;
        }
        $record->timemodified = time();
        $DB->update_record('assign', $record);

        $assign = new \assign($context, $cm, get_course($cm->course));
        $assign->update_calendar($cm->id);
        rebuild_course_cache($cm->course, true);
        \core\event\course_module_updated::create_from_cm($cm, $context)->trigger();

        return ['cmid' => (int)$cm->id, 'duedate' => (int)$record->duedate];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'duedate' => new external_value(PARAM_INT, 'Due date now'),
        ]);
    }
}
