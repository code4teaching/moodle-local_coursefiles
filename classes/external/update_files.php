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
 * Replaces the files of a file resource or a folder, e.g. with a newer version of a worksheet.
 *
 * The activity keeps its ID, place, settings and completion; only the files (and optionally the name) change.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_files extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of a file resource or folder'),
            'files' => files::files_parameter(),
            'name' => new external_value(PARAM_TEXT, 'New name (empty = keep)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Replaces the files.
     *
     * @param int $cmid
     * @param array $files
     * @param string $name
     * @return array
     */
    public static function execute(int $cmid, array $files, string $name = ''): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'files' => $files, 'name' => $name,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if (!in_array($cm->modname, ['resource', 'folder'])) {
            throw new \moodle_exception('notfilemodule', 'local_coursefiles');
        }
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        if ($cm->modname === 'resource' && count($params['files']) !== 1) {
            throw new \moodle_exception('onefileonly', 'local_coursefiles');
        }

        $course = get_course($cm->course);
        $decoded = files::decode($params['files'], $course, $context);
        files::replace_area($context, 'mod_' . $cm->modname, $decoded);

        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
        $instance->revision++;
        $instance->timemodified = time();
        if ($params['name'] !== '') {
            $instance->name = $params['name'];
        }
        $DB->update_record($cm->modname, $instance);

        rebuild_course_cache($cm->course, true);
        \core\event\course_module_updated::create_from_cm($cm, $context)->trigger();

        return ['cmid' => (int)$cm->id, 'revision' => (int)$instance->revision];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'revision' => new external_value(PARAM_INT, 'New revision of the files'),
        ]);
    }
}
