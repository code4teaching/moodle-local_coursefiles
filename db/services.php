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
    'local_coursefiles_set_visibility' => [
        'classname'    => 'local_coursefiles\external\set_visibility',
        'description'  => 'Shows or hides an activity or resource.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:activityvisibility',
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
