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
 * Language strings.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['availabilitydisabled'] = 'Restrict access is turned off on this site (Site administration → Advanced features → Enable restricted access), so a release date would have no effect.';
$string['backupfailed'] = 'The backup could not be created.';
$string['backupwithusers'] = 'The site settings force user data into course backups. No backup without user data is possible.';
$string['connectbutton'] = 'Connect to VisuDesk';
$string['connectinvalid'] = 'This link to connect an app is not valid. Start again in the app with “Connect to Moodle”.';
$string['connectnoservice'] = 'The web service {$a} is not available for connecting apps. Please contact the Moodle administration.';
$string['connectnotallowed'] = 'You cannot get a key for the web service {$a} yet. Please contact the Moodle administration.';
$string['connectquestion'] = '<p>VisuDesk on this computer wants to work with your Moodle account <strong>{$a->name}</strong> (web service “{$a->service}”).</p><p>VisuDesk can then do in your courses what you can do in Moodle: upload files, create quizzes and look at submissions. The key goes only to VisuDesk on this computer and is stored in its keychain.</p><p>You can remove the key at any time under Preferences → Security keys.</p>';
$string['connecttitle'] = 'Connect VisuDesk';
$string['filetoobig'] = 'The file {$a->filename} ({$a->size}) is larger than the upload limit of this course ({$a->max}).';
$string['invalidcontent'] = 'The content of {$a} is not valid base64.';
$string['invalidfilename'] = 'Invalid or duplicate file name: {$a}';
$string['nofiles'] = 'No files given.';
$string['notfilemodule'] = 'This activity is neither a file resource nor a folder.';
$string['onefileonly'] = 'A file resource takes exactly one file.';
$string['pluginname'] = 'Course Files';
$string['privacy:metadata'] = 'The Course Files plugin does not store any personal data.';
$string['selfenrolcannotadd'] = 'Self enrolment cannot be added to this course.';
$string['selfenroldisabled'] = 'Self enrolment is turned off on this site (Site administration → Plugins → Enrolments → Manage enrol plugins).';
$string['selfenrolkeyrequired'] = 'This site requires an enrolment key for self enrolment.';
$string['siterole'] = 'Site role for teachers';
$string['siterole_desc'] = 'This role is given in the system context to everybody who has the teacher role above in any course, e.g. a role "Web service user" with moodle/webservice:createtoken and webservice/mcp:use. When you change this setting, all current teachers get the role at once; later the role follows every new teacher. Removing a teacher from a course does not take the role away. "None" turns this off.';
$string['task_syncteacherrole'] = 'Give the site role to all teachers';
$string['teacherrole'] = 'Teacher role in courses';
$string['teacherrole_desc'] = 'Everybody with this role in at least one course gets the site role below.';
$string['teacherroleheading'] = 'Site role for teachers';
$string['teacherroleheading_desc'] = 'Capabilities such as creating a web service key are checked for the whole site, so a course role like Teacher cannot grant them. Course Files can give teachers a site role automatically.';
