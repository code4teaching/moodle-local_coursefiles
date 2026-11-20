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
 * Marks an automatically created course as checked (or not checked again).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_autoapproved_course extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Entry ID from get_autoapproved_courses'),
            'checked' => new external_value(PARAM_INT, '1 = checked, 0 = not checked again', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $id
     * @param int $checked
     * @return array
     */
    public static function execute(int $id, int $checked = 1): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id, 'checked' => $checked]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:approvecourse', $context);
        $record = $DB->get_record('local_coursefiles_autoappr', ['id' => $params['id']], '*', MUST_EXIST);
        $record->timechecked = $params['checked'] ? time() : 0;
        $DB->update_record('local_coursefiles_autoappr', $record);
        return ['timechecked' => (int)$record->timechecked];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'timechecked' => new external_value(PARAM_INT, 'When it was marked as checked, 0 = not checked'),
        ]);
    }
}
