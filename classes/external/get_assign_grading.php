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
use local_coursefiles\local\grading;

/**
 * Grading overview of an assignment: grade type (points, scale with its items, or none), marking workflow, and for
 * each participant the grade, the marking workflow state, the feedback comment and the number of feedback files.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_assign_grading extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
        ]);
    }

    /**
     * Reads the grading state.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/assign:grade', $context);

        $assign = new \assign($context, $cm, get_course($cm->course));
        $instance = $assign->get_instance();
        $fileplugin = $assign->get_feedback_plugin_by_type('file');
        $users = [];
        foreach ($assign->list_participants(0, true) as $participant) {
            $grade = $assign->get_user_grade($participant->id, false);
            $flags = $assign->get_user_flags($participant->id, false);
            $comment = $grade ? $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]) : null;
            $numfiles = $grade ? (int)$DB->get_field('assignfeedback_file', 'numfiles', ['grade' => $grade->id]) : 0;
            $users[] = [
                'userid' => (int)$participant->id,
                'grade' => $grade && $grade->grade !== null ? (float)$grade->grade : -1.0,
                'workflowstate' => $flags && $flags->workflowstate ? $flags->workflowstate : '',
                'feedback' => $comment ? (string)$comment->commenttext : '',
                'numfeedbackfiles' => $numfiles,
                'timemodified' => $grade ? (int)$grade->timemodified : 0,
            ];
        }
        return grading::describe($instance) + [
            'assignid' => (int)$instance->id,
            'markingworkflow' => (int)$instance->markingworkflow,
            'duedate' => (int)$instance->duedate,
            'feedbackfiles' => $fileplugin && $fileplugin->is_enabled() ? 1 : 0,
            'users' => $users,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'point, scale or none'),
            'grademax' => new external_value(PARAM_FLOAT, 'Maximum points (number of scale items for a scale)'),
            'scalename' => new external_value(PARAM_TEXT, 'Name of the scale'),
            'scaleitems' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Scale item'),
                'Scale items from worst to best; a scale grade n is item n'
            ),
            'assignid' => new external_value(PARAM_INT, 'Assignment ID'),
            'markingworkflow' => new external_value(PARAM_INT, 'Marking workflow on (1): grades show after release'),
            'duedate' => new external_value(PARAM_INT, 'Due date'),
            'feedbackfiles' => new external_value(PARAM_INT, 'Feedback files enabled'),
            'users' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Participant'),
                'grade' => new external_value(PARAM_FLOAT, 'Grade, -1 = none'),
                'workflowstate' => new external_value(PARAM_ALPHA, 'Marking workflow state'),
                'feedback' => new external_value(PARAM_RAW, 'Feedback comment (HTML)'),
                'numfeedbackfiles' => new external_value(PARAM_INT, 'Number of feedback files'),
                'timemodified' => new external_value(PARAM_INT, 'Grade last changed'),
            ])),
        ]);
    }
}
