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
 * Creates a file resource (e.g. a PDF worksheet) in a course section.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_resource extends external_api {
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
            'file' => files::file_parameter(),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Creates the resource.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param array $file
     * @param string $intro
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $sectionnum, string $name, array $file, string $intro = '',
            int $visible = 0): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'sectionnum' => $sectionnum, 'name' => $name, 'file' => $file,
            'intro' => $intro, 'visible' => $visible,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/resource:addinstance', $context);

        $decoded = files::decode([$params['file']], $course, $context);
        $config = get_config('resource');

        $resource = new \stdClass();
        $resource->modulename = 'resource';
        $resource->module = $DB->get_field('modules', 'id', ['name' => 'resource'], MUST_EXIST);
        $resource->course = $course->id;
        $resource->section = $params['sectionnum'];
        $resource->visible = $params['visible'] ? 1 : 0;
        $resource->name = $params['name'];
        $resource->introeditor = ['text' => $params['intro'], 'format' => FORMAT_HTML, 'itemid' => 0];
        $resource->cmidnumber = '';
        $resource->files = files::to_draft($decoded);
        $resource->display = (int)($config->display ?? RESOURCELIB_DISPLAY_AUTO);
        $resource->printintro = (int)($config->printintro ?? 1);
        $resource->showsize = (int)($config->showsize ?? 0);
        $resource->showtype = (int)($config->showtype ?? 0);
        $resource->showdate = (int)($config->showdate ?? 0);
        $resource->filterfiles = (int)($config->filterfiles ?? 0);
        $resource->revision = 1;

        $resource = create_module(set_moduleinfo_defaults($resource));

        return ['cmid' => (int)$resource->coursemodule, 'resourceid' => (int)$resource->instance];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'resourceid' => new external_value(PARAM_INT, 'Resource ID'),
        ]);
    }
}
