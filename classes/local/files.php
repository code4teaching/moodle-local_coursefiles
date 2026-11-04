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

namespace local_coursefiles\local;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Helpers for files sent as base64 to the web service functions.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files {
    /**
     * Parameter structure for one file.
     *
     * @return external_single_structure
     */
    public static function file_parameter(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'File name, e.g. worksheet.pdf'),
            'content' => new external_value(PARAM_RAW, 'File content, base64 encoded'),
        ]);
    }

    /**
     * Parameter structure for several files.
     *
     * @return external_multiple_structure
     */
    public static function files_parameter(): external_multiple_structure {
        return new external_multiple_structure(self::file_parameter(), 'Files');
    }

    /**
     * Decodes and checks the files: valid base64, a file name, not larger than the upload limit of the course.
     *
     * @param array $files list of ['filename' => ..., 'content' => base64]
     * @param \stdClass $course
     * @param \context $context
     * @return array list of ['filename' => ..., 'content' => binary]
     */
    public static function decode(array $files, \stdClass $course, \context $context): array {
        global $CFG;
        if (!$files) {
            throw new \moodle_exception('nofiles', 'local_coursefiles');
        }
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $course->maxbytes);
        $result = [];
        $names = [];
        foreach ($files as $file) {
            $filename = clean_param($file['filename'], PARAM_FILE);
            if ($filename === '' || isset($names[$filename])) {
                throw new \moodle_exception('invalidfilename', 'local_coursefiles', '', s($file['filename']));
            }
            $content = base64_decode($file['content'], true);
            if ($content === false) {
                throw new \moodle_exception('invalidcontent', 'local_coursefiles', '', $filename);
            }
            if ($maxbytes > 0 && strlen($content) > $maxbytes) {
                throw new \moodle_exception('filetoobig', 'local_coursefiles', '', (object)[
                    'filename' => $filename, 'size' => display_size(strlen($content)), 'max' => display_size($maxbytes),
                ]);
            }
            $names[$filename] = true;
            $result[] = ['filename' => $filename, 'content' => $content];
        }
        return $result;
    }

    /**
     * Puts the files into a new draft area of the current user, as the file picker would.
     *
     * @param array $files decoded files
     * @return int draft item id
     */
    public static function to_draft(array $files): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        foreach ($files as $file) {
            $fs->create_file_from_string([
                'contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft',
                'itemid' => $draftitemid, 'filepath' => '/', 'filename' => $file['filename'],
            ], $file['content']);
        }
        return $draftitemid;
    }

    /**
     * Replaces all files of a module file area.
     *
     * @param \context_module $context
     * @param string $component mod_resource or mod_folder
     * @param array $files decoded files
     */
    public static function replace_area(\context_module $context, string $component, array $files): void {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, $component, 'content');
        foreach ($files as $i => $file) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => $component, 'filearea' => 'content',
                'itemid' => 0, 'filepath' => '/', 'filename' => $file['filename'], 'sortorder' => $i === 0 ? 1 : 0,
            ], $file['content']);
        }
    }
}
