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
use local_coursefiles\local\autoapprove;

/**
 * Lists courses created automatically from requests, for the approver to check.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_autoapproved_courses extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'withchecked' => new external_value(PARAM_INT, '1 = also the courses already checked', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $withchecked
     * @return array
     */
    public static function execute(int $withchecked = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), ['withchecked' => $withchecked]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:approvecourse', $context);
        return [
            'enabled' => autoapprove::enabled(),
            'courses' => autoapprove::list((bool)$params['withchecked']),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether automatic approval is turned on'),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Entry ID'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
                'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID'),
                'categoryname' => new external_value(PARAM_TEXT, 'Category name with its parents'),
                'visible' => new external_value(PARAM_INT, '1 = visible to students'),
                'teachers' => new external_value(PARAM_TEXT, 'Teachers of the course (the requester)'),
                'timecreated' => new external_value(PARAM_INT, 'When the course was created'),
                'timechecked' => new external_value(PARAM_INT, 'When it was marked as checked, 0 = not yet'),
            ])),
        ]);
    }
}
