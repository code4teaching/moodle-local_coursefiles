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
 * Creates a Moodle backup (.mbz) of a course without user data and returns a download address.
 *
 * Moodle core cannot create course backups through web services. The backup contains the course structure,
 * activities, resources and settings, but no enrolled users, submissions, grades, logs or other user data.
 * If the site forces user data into backups, the function refuses instead of creating one.
 *
 * The file is kept in the plugin's file area of the calling user (one file per course, replaced by the next
 * backup) and can be downloaded with the token through webservice/pluginfile.php.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_course extends external_api {
    /** @var string[] Backup settings that would add user data. */
    const USER_SETTINGS = ['users', 'role_assignments', 'comments', 'badges', 'userscompletion', 'logs',
        'grade_histories', 'xapistate'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Creates the backup.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/backup:backupcourse', $context);
        require_capability('moodle/backup:downloadfile', $context);

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            $plan = $bc->get_plan();
            foreach (self::USER_SETTINGS as $name) {
                if ($plan->setting_exists($name)) {
                    $setting = $plan->get_setting($name);
                    if ($setting->get_status() == \base_setting::NOT_LOCKED) {
                        $setting->set_value(false);
                    }
                }
            }
            if ($plan->setting_exists('users') && $plan->get_setting('users')->get_value()) {
                throw new \moodle_exception('backupwithusers', 'local_coursefiles');
            }
            $bc->execute_plan();
            $results = $bc->get_results();
        } finally {
            $bc->destroy();
        }

        $file = $results['backup_destination'] ?? null;
        if (!$file instanceof \stored_file) {
            throw new \moodle_exception('backupfailed', 'local_coursefiles');
        }

        // Keep only this backup for this course in the plugin area of the user, then remove Moodle's copy.
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $fs->delete_area_files($usercontext->id, 'local_coursefiles', 'backup', $course->id);
        $copy = $fs->create_file_from_storedfile([
            'contextid' => $usercontext->id,
            'component' => 'local_coursefiles',
            'filearea' => 'backup',
            'itemid' => $course->id,
            'filepath' => '/',
            'filename' => $file->get_filename(),
        ], $file);
        $file->delete();

        $url = \moodle_url::make_webservice_pluginfile_url(
            $usercontext->id,
            'local_coursefiles',
            'backup',
            $course->id,
            '/',
            $copy->get_filename()
        );
        return [
            'filename' => $copy->get_filename(),
            'filesize' => (int)$copy->get_filesize(),
            'fileurl' => $url->out(false),
        ];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'Name of the backup file (.mbz)'),
            'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
            'fileurl' => new external_value(PARAM_URL, 'Download address, needs the token as parameter'),
        ]);
    }
}
