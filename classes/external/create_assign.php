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
 * Creates an assignment in a course section: file submissions, a due date, the task as text and/or attached files.
 *
 * Feedback comments and feedback files are switched on, so corrected files can be given back with set_feedback_files.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_assign extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number (0-based)'),
            'name' => new external_value(PARAM_TEXT, 'Name shown in the course'),
            'intro' => new external_value(PARAM_RAW, 'Task description (HTML)', VALUE_DEFAULT, ''),
            'files' => new external_multiple_structure(
                files::file_parameter(),
                'Files attached to the task description (e.g. the worksheet)',
                VALUE_DEFAULT,
                []
            ),
            'duedate' => new external_value(PARAM_INT, 'Due date (timestamp, 0 = none)', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_INT, 'Maximum points, 0 = no grade', VALUE_DEFAULT, 100),
            'gradescale' => new external_value(
                PARAM_TEXT,
                'Grade with the scale of this name (site scale or scale of the course) instead of points',
                VALUE_DEFAULT,
                ''
            ),
            'markingworkflow' => new external_value(
                PARAM_INT,
                'Marking workflow (1): students see grades only once they are released',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Creates the assignment.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $intro
     * @param array $files
     * @param int $duedate
     * @param int $visible
     * @param int $grade
     * @param string $gradescale
     * @param int $markingworkflow
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        string $intro = '',
        array $files = [],
        int $duedate = 0,
        int $visible = 0,
        int $grade = 100,
        string $gradescale = '',
        int $markingworkflow = 0
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'sectionnum' => $sectionnum, 'name' => $name, 'intro' => $intro,
            'files' => $files, 'duedate' => $duedate, 'visible' => $visible, 'grade' => $grade,
            'gradescale' => $gradescale, 'markingworkflow' => $markingworkflow,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/assign:addinstance', $context);

        $assign = new \stdClass();
        $assign->modulename = 'assign';
        $assign->module = $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);
        $assign->course = $course->id;
        $assign->section = $params['sectionnum'];
        $assign->visible = $params['visible'] ? 1 : 0;
        $assign->name = $params['name'];
        $assign->introeditor = ['text' => $params['intro'], 'format' => FORMAT_HTML, 'itemid' => 0];
        $assign->introattachments = $params['files'] ? files::to_draft(files::decode($params['files'], $course, $context)) : 0;
        $assign->cmidnumber = '';
        $assign->alwaysshowdescription = 1;
        $assign->submissiondrafts = 0;
        $assign->requiresubmissionstatement = 0;
        $assign->sendnotifications = 0;
        $assign->sendlatenotifications = 0;
        $assign->sendstudentnotifications = 1;
        $assign->allowsubmissionsfromdate = 0;
        $assign->duedate = $params['duedate'];
        $assign->cutoffdate = 0;
        $assign->gradingduedate = 0;
        $assign->grade = grading::grade_value($params['grade'], $params['gradescale'], $course);
        $assign->completionsubmit = 0;
        $assign->teamsubmission = 0;
        $assign->requireallteammemberssubmit = 0;
        $assign->teamsubmissiongroupingid = 0;
        $assign->blindmarking = 0;
        $assign->hidegrader = 0;
        $assign->attemptreopenmethod = 'untilpass';
        $assign->maxattempts = 1;
        $assign->markingworkflow = $params['markingworkflow'] ? 1 : 0;
        $assign->markingallocation = 0;
        $assign->markinganonymous = 0;
        $assign->preventsubmissionnotingroup = 0;
        $assign->activity = '';
        $assign->activityformat = FORMAT_HTML;
        $assign->timelimit = 0;
        $assign->submissionattachments = 0;
        $assign->assignsubmission_file_enabled = 1;
        $assign->assignsubmission_file_maxfiles = 20;
        $assign->assignsubmission_file_maxsizebytes = 0;
        $assign->assignsubmission_file_filetypes = '';
        $assign->assignsubmission_onlinetext_enabled = 0;
        $assign->assignfeedback_comments_enabled = 1;
        $assign->assignfeedback_file_enabled = 1;

        $assign = create_module(set_moduleinfo_defaults($assign));

        return ['cmid' => (int)$assign->coursemodule, 'assignid' => (int)$assign->instance];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'assignid' => new external_value(PARAM_INT, 'Assignment ID (as in mod_assign_get_submissions)'),
        ]);
    }
}
