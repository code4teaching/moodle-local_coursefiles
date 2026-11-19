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

/**
 * Releases grades of an assignment with marking workflow: the participants (all graded ones if none are given) get
 * the state "released", and their grades go to the gradebook, so students see them.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class release_grades extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Participant'),
                'Participants to release; empty = everyone with a grade',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Releases the grades.
     *
     * @param int $cmid
     * @param array $userids
     * @return array
     */
    public static function execute(int $cmid, array $userids = []): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'userids' => $userids]);
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/assign:grade', $context);
        require_capability('mod/assign:releasegrades', $context);

        $assign = new \assign($context, $cm, get_course($cm->course));
        $instance = $assign->get_instance();
        if (!$instance->markingworkflow) {
            throw new \moodle_exception('nomarkingworkflow', 'local_coursefiles');
        }
        $ids = array_map('intval', $params['userids']);
        if (!$ids) {
            $ids = $DB->get_fieldset_select('assign_grades', 'DISTINCT userid', 'assignment = ? AND grade >= 0', [$instance->id]);
        }
        $released = [];
        foreach (array_unique($ids) as $userid) {
            if (!$assign->get_participant($userid)) {
                continue;
            }
            $flags = $assign->get_user_flags($userid, true);
            $flags->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
            $assign->update_user_flags($flags);
            $grade = $assign->get_user_grade($userid, false);
            if ($grade) {
                $assign->update_grade($grade);
            }
            $released[] = (int)$userid;
        }
        return ['released' => $released];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'released' => new external_multiple_structure(new external_value(PARAM_INT, 'Participant'), 'Released now'),
        ]);
    }
}
