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
use local_coursefiles\local\requests;

/**
 * Rejects a course request. Moodle sends the reason to the requester and deletes the request.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reject_course_request extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestid' => new external_value(PARAM_INT, 'Request ID'),
            'reason' => new external_value(PARAM_TEXT, 'Reason, sent to the requester'),
        ]);
    }

    /**
     * Runs the function.
     *
     * @param int $requestid
     * @param string $reason
     * @return array
     */
    public static function execute(int $requestid, string $reason): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['requestid' => $requestid, 'reason' => $reason]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:approvecourse', $context);
        if (trim($params['reason']) === '') {
            throw new \invalid_parameter_exception('reason is required');
        }
        if (!$DB->record_exists('course_request', ['id' => $params['requestid']])) {
            throw new \moodle_exception('unknowncourserequest', 'error');
        }
        requests::load($params['requestid'])->reject(trim($params['reason']));
        return ['ok' => 1];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_INT, '1 when done'),
        ]);
    }
}
