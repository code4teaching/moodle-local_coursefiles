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
 * Web service functions.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursefiles_create_resource' => [
        'classname'    => 'local_coursefiles\external\create_resource',
        'description'  => 'Creates a file resource (e.g. a PDF worksheet) in a course section. The file is sent base64 encoded. ' .
            'New resources are hidden unless visible=1.',
        'type'         => 'write',
        'capabilities' => 'mod/resource:addinstance',
    ],
    'local_coursefiles_create_folder' => [
        'classname'    => 'local_coursefiles\external\create_folder',
        'description'  => 'Creates a folder with several files (base64 encoded) in a course section.',
        'type'         => 'write',
        'capabilities' => 'mod/folder:addinstance',
    ],
    'local_coursefiles_update_files' => [
        'classname'    => 'local_coursefiles\external\update_files',
        'description'  => 'Replaces the file of a file resource or all files of a folder, e.g. with a newer version. ' .
            'The activity keeps its ID, place and settings.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_coursefiles_create_section' => [
        'classname'    => 'local_coursefiles\external\create_section',
        'description'  => 'Creates a course section at the end or at a given position (hidden unless visible=1).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
    ],
    'local_coursefiles_update_section' => [
        'classname'    => 'local_coursefiles\external\update_section',
        'description'  => 'Changes name, summary or visibility of a course section.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update, moodle/course:sectionvisibility',
    ],
    'local_coursefiles_move_section' => [
        'classname'    => 'local_coursefiles\external\move_section',
        'description'  => 'Moves a course section to another position; its activities move with it.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:movesections',
    ],
    'local_coursefiles_set_visibility' => [
        'classname'    => 'local_coursefiles\external\set_visibility',
        'description'  => 'Shows or hides an activity or resource.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:activityvisibility',
    ],
    'local_coursefiles_backup_course' => [
        'classname'    => 'local_coursefiles\\external\\backup_course',
        'description'  => 'Creates a Moodle backup (.mbz) of a course without user data and returns a download address. ' .
            'Refuses if the site forces user data into backups.',
        'type'         => 'write',
        'capabilities' => 'moodle/backup:backupcourse, moodle/backup:downloadfile',
    ],
    'local_coursefiles_request_course' => [
        'classname'    => 'local_coursefiles\\external\\request_course',
        'description'  => 'Asks for a new course (Moodle course request). Needs course requests to be enabled on the site.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:request',
    ],
    'local_coursefiles_get_course_requests' => [
        'classname'    => 'local_coursefiles\\external\\get_course_requests',
        'description'  => 'Lists pending course requests: the caller\'s own, or all of them for approvers (all=1).',
        'type'         => 'read',
    ],
    'local_coursefiles_approve_course_request' => [
        'classname'    => 'local_coursefiles\\external\\approve_course_request',
        'description'  => 'Approves a course request, optionally with a corrected name and another category. ' .
            'Moodle creates the course, enrols the requester as teacher and notifies them.',
        'type'         => 'write',
        'capabilities' => 'moodle/site:approvecourse',
    ],
    'local_coursefiles_reject_course_request' => [
        'classname'    => 'local_coursefiles\\external\\reject_course_request',
        'description'  => 'Rejects a course request; the reason is sent to the requester.',
        'type'         => 'write',
        'capabilities' => 'moodle/site:approvecourse',
    ],
];

$services = [
    'Course Files Service' => [
        'functions'       => array_keys($functions),
        'shortname'       => 'local_coursefiles',
        'restrictedusers' => 1,
        'enabled'         => 1,
        'uploadfiles'     => 1,
        'downloadfiles'   => 1,
    ],
];
