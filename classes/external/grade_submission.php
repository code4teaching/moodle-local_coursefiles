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
 * Grades one participant of an assignment: grade and feedback comment, and with marking workflow its state
 * (e.g. "readyforrelease"; students only see the grade once it is "released"). Feedback files stay
 * (see set_feedback_files).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_submission extends external_api {
    /** @var string[] Marking workflow states that can be set. */
    const STATES = ['notmarked', 'inmarking', 'readyforreview', 'inreview', 'readyforrelease', 'released'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'userid' => new external_value(PARAM_INT, 'The participant'),
            'changegrade' => new external_value(PARAM_BOOL, 'Set the grade', VALUE_DEFAULT, true),
            'grade' => new external_value(
                PARAM_FLOAT,
                'Points, or for a scale the item number (1 = worst); -1 = none',
                VALUE_DEFAULT,
                -1
            ),
            'changefeedback' => new external_value(PARAM_BOOL, 'Replace the feedback comment', VALUE_DEFAULT, false),
            'feedback' => new external_value(PARAM_RAW, 'Feedback comment (HTML)', VALUE_DEFAULT, ''),
            'workflowstate' => new external_value(PARAM_ALPHA, 'Marking workflow state ("" = unchanged)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Saves the grade.
     *
     * @param int $cmid
     * @param int $userid
     * @param bool $changegrade
     * @param float $grade
     * @param bool $changefeedback
     * @param string $feedback
     * @param string $workflowstate
     * @return array
     */
    public static function execute(
        int $cmid,
        int $userid,
        bool $changegrade = true,
        float $grade = -1,
        bool $changefeedback = false,
        string $feedback = '',
        string $workflowstate = ''
    ): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'userid' => $userid, 'changegrade' => $changegrade, 'grade' => $grade,
            'changefeedback' => $changefeedback, 'feedback' => $feedback, 'workflowstate' => $workflowstate,
        ]);
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/assign:grade', $context);

        $assign = new \assign($context, $cm, get_course($cm->course));
        $instance = $assign->get_instance();
        if (!$assign->get_participant($params['userid'])) {
            throw new \moodle_exception('notparticipant', 'local_coursefiles');
        }
        $state = $params['workflowstate'];
        if ($state !== '' && (!$instance->markingworkflow || !in_array($state, self::STATES, true))) {
            throw new \invalid_parameter_exception('workflowstate needs marking workflow and one of: ' .
                implode(', ', self::STATES));
        }

        $record = $assign->get_user_grade($params['userid'], true);
        if ($params['changegrade']) {
            $value = $params['grade'];
            if ($value < 0) {
                $value = -1;
            } else if ((int)$instance->grade > 0) {
                if ($value > (float)$instance->grade) {
                    throw new \moodle_exception('gradetoohigh', 'local_coursefiles', '', format_float($instance->grade, -1));
                }
            } else if ((int)$instance->grade < 0) {
                $items = (int)count(explode(',', (string)$DB->get_field('scale', 'scale', ['id' => -(int)$instance->grade])));
                if ($value != round($value) || $value < 1 || $value > $items) {
                    throw new \moodle_exception('gradenotinscale', 'local_coursefiles');
                }
            } else {
                throw new \moodle_exception('nogradetype', 'local_coursefiles');
            }
            $record->grade = $value;
            $record->grader = $USER->id;
        }
        if ($params['changefeedback']) {
            $comment = $DB->get_record('assignfeedback_comments', ['grade' => $record->id]);
            if ($comment) {
                $comment->commenttext = $params['feedback'];
                $comment->commentformat = FORMAT_HTML;
                $DB->update_record('assignfeedback_comments', $comment);
            } else {
                $DB->insert_record('assignfeedback_comments', (object)[
                    'assignment' => $instance->id, 'grade' => $record->id,
                    'commenttext' => $params['feedback'], 'commentformat' => FORMAT_HTML,
                ]);
            }
        }
        if ($state !== '') {
            $flags = $assign->get_user_flags($params['userid'], true);
            $flags->workflowstate = $state;
            $assign->update_user_flags($flags);
        }
        // Saves the grade and passes it to the gradebook (with marking workflow only once released).
        $assign->update_grade($record);

        $flags = $assign->get_user_flags($params['userid'], false);
        return [
            'grade' => $record->grade !== null ? (float)$record->grade : -1.0,
            'workflowstate' => $flags && $flags->workflowstate ? $flags->workflowstate : '',
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'grade' => new external_value(PARAM_FLOAT, 'Grade now, -1 = none'),
            'workflowstate' => new external_value(PARAM_ALPHA, 'Marking workflow state now'),
        ]);
    }
}
