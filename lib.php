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

/**
 * Library functions of Course Files.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves course backups created by local_coursefiles_backup_course, only to the user who created them
 * and only while that user may still download backups of the course.
 *
 * @param stdClass|null $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file was not found
 */
function local_coursefiles_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;
    if ($filearea !== 'backup' || $context->contextlevel != CONTEXT_USER || $context->instanceid != $USER->id) {
        return false;
    }
    require_login();
    $courseid = (int)array_shift($args);
    $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
    if (!$coursecontext || !has_capability('moodle/backup:downloadfile', $coursecontext)) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_coursefiles', 'backup', $courseid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    \core\session\manager::write_close();
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Called when the site role for teachers is changed in the settings: give it to all current teachers right away.
 */
function local_coursefiles_sync_teacherrole(): void {
    \local_coursefiles\local\teacherrole::sync();
}
