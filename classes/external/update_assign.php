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
use local_coursefiles\local\files;
use local_coursefiles\local\grading;

/**
 * Changes an existing assignment: name, task description, attached files and due date.
 * Only what is passed changes; submissions, grades and all other settings stay.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_assign extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of the assignment'),
            'name' => new external_value(PARAM_TEXT, 'New name ("" = unchanged)', VALUE_DEFAULT, ''),
            'changeintro' => new external_value(PARAM_BOOL, 'Replace the task description with intro', VALUE_DEFAULT, false),
            'intro' => new external_value(PARAM_RAW, 'Task description (HTML)', VALUE_DEFAULT, ''),
            'changefiles' => new external_value(PARAM_BOOL, 'Change attached files (keepfiles, files)', VALUE_DEFAULT, false),
            'keepfiles' => new external_multiple_structure(
                new external_value(PARAM_FILE, 'File name'),
                'Attached files to keep; all others are removed',
                VALUE_DEFAULT,
                []
            ),
            'files' => new external_multiple_structure(
                files::file_parameter(),
                'Files to attach (a file with the same name is replaced)',
                VALUE_DEFAULT,
                []
            ),
            'duedate' => new external_value(PARAM_INT, 'New due date (timestamp, 0 = none, -1 = unchanged)', VALUE_DEFAULT, -1),
            'changegrade' => new external_value(
                PARAM_BOOL,
                'Change the grade type (grade, gradescale); only while nobody has a grade',
                VALUE_DEFAULT,
                false
            ),
            'grade' => new external_value(PARAM_INT, 'Maximum points, 0 = no grade', VALUE_DEFAULT, 100),
            'gradescale' => new external_value(PARAM_TEXT, 'Grade with the scale of this name instead', VALUE_DEFAULT, ''),
            'markingworkflow' => new external_value(PARAM_INT, 'Marking workflow: 1 on, 0 off, -1 unchanged', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Changes the assignment.
     *
     * @param int $cmid
     * @param string $name
     * @param bool $changeintro
     * @param string $intro
     * @param bool $changefiles
     * @param array $keepfiles
     * @param array $files
     * @param int $duedate
     * @param bool $changegrade
     * @param int $grade
     * @param string $gradescale
     * @param int $markingworkflow
     * @return array
     */
    public static function execute(
        int $cmid,
        string $name = '',
        bool $changeintro = false,
        string $intro = '',
        bool $changefiles = false,
        array $keepfiles = [],
        array $files = [],
        int $duedate = -1,
        bool $changegrade = false,
        int $grade = 100,
        string $gradescale = '',
        int $markingworkflow = -1
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'name' => $name, 'changeintro' => $changeintro, 'intro' => $intro,
            'changefiles' => $changefiles, 'keepfiles' => $keepfiles, 'files' => $files, 'duedate' => $duedate,
            'changegrade' => $changegrade, 'grade' => $grade, 'gradescale' => $gradescale,
            'markingworkflow' => $markingworkflow,
        ]);
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $record = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $name = trim($params['name']);
        if ($name !== '') {
            $record->name = $name;
        }
        if ($params['changeintro']) {
            $record->intro = $params['intro'];
            $record->introformat = FORMAT_HTML;
        }
        if ($params['duedate'] >= 0) {
            $record->duedate = $params['duedate'];
            // A cut-off date before the new due date would block submissions, so it goes.
            if ($record->cutoffdate && $params['duedate'] && $record->cutoffdate < $params['duedate']) {
                $record->cutoffdate = 0;
            }
        }
        if ($params['changefiles']) {
            $fs = get_file_storage();
            $keep = array_flip(array_map(fn($f) => clean_param($f, PARAM_FILE), $params['keepfiles']));
            $new = $params['files'] ? files::decode($params['files'], $course, $context) : [];
            $newnames = array_flip(array_column($new, 'filename'));
            foreach ($fs->get_area_files($context->id, 'mod_assign', 'introattachment', 0, 'filename', false) as $file) {
                if (!isset($keep[$file->get_filename()]) || isset($newnames[$file->get_filename()])) {
                    $file->delete();
                }
            }
            foreach ($new as $file) {
                $fs->create_file_from_string([
                    'contextid' => $context->id, 'component' => 'mod_assign', 'filearea' => 'introattachment',
                    'itemid' => 0, 'filepath' => '/', 'filename' => $file['filename'],
                ], $file['content']);
            }
        }
        $gradechanged = false;
        if ($params['changegrade']) {
            $value = grading::grade_value($params['grade'], $params['gradescale'], $course);
            if ($value !== (int)$record->grade) {
                if (grading::graded($record->id)) {
                    throw new \moodle_exception('gradingstarted', 'local_coursefiles');
                }
                $record->grade = $value;
                $gradechanged = true;
            }
        }
        if ($params['markingworkflow'] >= 0) {
            $record->markingworkflow = $params['markingworkflow'] ? 1 : 0;
        }
        $record->timemodified = time();
        $DB->update_record('assign', $record);
        if ($gradechanged) {
            require_once($CFG->dirroot . '/mod/assign/lib.php');
            $record->cmidnumber = $cm->idnumber;
            $record->courseid = $cm->course;
            assign_grade_item_update($record);
        }

        if ($params['duedate'] >= 0) {
            $assign = new \assign($context, $cm, $course);
            $assign->update_calendar($cm->id);
        }
        rebuild_course_cache($cm->course, true);
        \core\event\course_module_updated::create_from_cm($cm, $context)->trigger();

        $attached = get_file_storage()->get_area_files($context->id, 'mod_assign', 'introattachment', 0, 'filename', false);
        return [
            'cmid' => (int)$cm->id,
            'name' => $record->name,
            'duedate' => (int)$record->duedate,
            'files' => array_values(array_map(fn($f) => $f->get_filename(), $attached)),
            'grade' => (int)$record->grade,
            'markingworkflow' => (int)$record->markingworkflow,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Name now'),
            'duedate' => new external_value(PARAM_INT, 'Due date now'),
            'files' => new external_multiple_structure(new external_value(PARAM_FILE, 'File name'), 'Attached files now'),
            'grade' => new external_value(PARAM_INT, 'Grade type now: maximum points, 0 = none, negative = scale ID'),
            'markingworkflow' => new external_value(PARAM_INT, 'Marking workflow now'),
        ]);
    }
}
