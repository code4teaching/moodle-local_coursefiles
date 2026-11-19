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
 * Connects VisuDesk to the signed-in user's account (see \local_coursefiles\local\connect).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursefiles\local\connect;

require(__DIR__ . '/../../config.php');

$passport = required_param('passport', PARAM_ALPHANUM);
$return = required_param('return', PARAM_RAW);
$servicename = optional_param('service', 'visudesk', PARAM_ALPHANUMEXT);

$url = new moodle_url('/local/coursefiles/connect.php', ['passport' => $passport, 'return' => $return,
    'service' => $servicename]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('connecttitle', 'local_coursefiles'));
$PAGE->set_heading(get_string('connecttitle', 'local_coursefiles'));

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
if (strlen($passport) < 16 || !connect::valid_return($return)) {
    throw new moodle_exception('connectinvalid', 'local_coursefiles');
}
$service = connect::service($servicename);

if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    $location = connect::callback($return, $passport, connect::token($service));
    // Not redirect(): it would show the address (with the token) on a page if headers were already sent.
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('Location: ' . $location, true, 303);
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('connectquestion', 'local_coursefiles', ['name' => fullname($USER), 'service' => format_string($service->name)]),
    new moodle_url($url, ['confirm' => 1]),
    new moodle_url('/'),
    ['continuestr' => get_string('connectbutton', 'local_coursefiles')]
);
echo $OUTPUT->footer();
