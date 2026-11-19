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
use local_coursefiles\local\files;

/**
 * Gives one student feedback files in an assignment, e.g. their corrected and annotated submission.
 *
 * Replaces the feedback files of this student (feedback type "Feedback files" must be enabled in the assignment).
 * The grade itself is not changed.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_feedback_files extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'assignid' => new external_value(PARAM_INT, 'Assignment instance ID (as in mod_assign_get_submissions)'),
            'userid' => new external_value(PARAM_INT, 'The student'),
            'files' => files::files_parameter(),
        ]);
    }

    /**
     * Replaces the feedback files of the student.
     *
     * @param int $assignid
     * @param int $userid
     * @param array $files
     * @return array
     */
    public static function execute(int $assignid, int $userid, array $files): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid, 'userid' => $userid, 'files' => $files,
        ]);

        $cm = get_coursemodule_from_instance('assign', $params['assignid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/assign:grade', $context);

        $assign = new \assign($context, $cm, null);
        $plugin = $assign->get_feedback_plugin_by_type('file');
        if (!$plugin || !$plugin->is_enabled() || !$plugin->is_visible()) {
            throw new \moodle_exception('feedbackfilesdisabled', 'local_coursefiles');
        }
        if (!$assign->get_participant($params['userid'])) {
            throw new \moodle_exception('notparticipant', 'local_coursefiles');
        }

        $decoded = files::decode($params['files'], get_course($cm->course), $context);
        $grade = $assign->get_user_grade($params['userid'], true);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'assignfeedback_file', 'feedback_files', $grade->id);
        foreach ($decoded as $file) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'assignfeedback_file', 'filearea' => 'feedback_files',
                'itemid' => $grade->id, 'filepath' => '/', 'filename' => $file['filename'],
            ], $file['content']);
        }

        $record = $DB->get_record('assignfeedback_file', ['grade' => $grade->id]);
        if ($record) {
            $record->numfiles = count($decoded);
            $DB->update_record('assignfeedback_file', $record);
        } else {
            $DB->insert_record('assignfeedback_file', (object)[
                'assignment' => $assign->get_instance()->id, 'grade' => $grade->id, 'numfiles' => count($decoded),
            ]);
        }
        $assign->update_grade($grade);

        return ['gradeid' => (int)$grade->id, 'numfiles' => count($decoded)];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'gradeid' => new external_value(PARAM_INT, 'ID of the grade record the files belong to'),
            'numfiles' => new external_value(PARAM_INT, 'Number of feedback files now'),
        ]);
    }
}
