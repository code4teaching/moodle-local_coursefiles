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
 * Settings: site role for teachers, automatic approval of course requests.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coursefiles', get_string('pluginname', 'local_coursefiles'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $none = [0 => get_string('none')];
        $allroles = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINAL, true);
        $courseroles = $none + array_intersect_key($allroles, array_flip(get_roles_for_contextlevels(CONTEXT_COURSE)));
        $systemroles = $none + array_intersect_key($allroles, array_flip(get_roles_for_contextlevels(CONTEXT_SYSTEM)));
        $editingteacher = get_archetype_roles('editingteacher');

        $settings->add(new admin_setting_heading(
            'local_coursefiles/teacherroleheading',
            get_string('teacherroleheading', 'local_coursefiles'),
            get_string('teacherroleheading_desc', 'local_coursefiles')
        ));
        $settings->add(new admin_setting_configselect(
            'local_coursefiles/teacherrole',
            get_string('teacherrole', 'local_coursefiles'),
            get_string('teacherrole_desc', 'local_coursefiles'),
            $editingteacher ? reset($editingteacher)->id : 0,
            $courseroles
        ));
        $siterole = new admin_setting_configselect(
            'local_coursefiles/siterole',
            get_string('siterole', 'local_coursefiles'),
            get_string('siterole_desc', 'local_coursefiles'),
            0,
            $systemroles
        );
        $siterole->set_updatedcallback([\local_coursefiles\local\teacherrole::class, 'sync']);
        $settings->add($siterole);

        $settings->add(new admin_setting_heading(
            'local_coursefiles/autoapproveheading',
            get_string('autoapproveheading', 'local_coursefiles'),
            get_string('autoapproveheading_desc', 'local_coursefiles')
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_coursefiles/autoapprove',
            get_string('autoapprove', 'local_coursefiles'),
            get_string('autoapprove_desc', 'local_coursefiles'),
            0
        ));
        $settings->add(new \local_coursefiles\admin\setting_namepattern(
            'local_coursefiles/autoapprovefullname',
            get_string('autoapprovefullname', 'local_coursefiles'),
            get_string('autoapprovefullname_desc', 'local_coursefiles'),
            \local_coursefiles\local\autoapprove::FULLNAME,
            PARAM_RAW,
            80
        ));
        $settings->add(new \local_coursefiles\admin\setting_namepattern(
            'local_coursefiles/autoapproveshortname',
            get_string('autoapproveshortname', 'local_coursefiles'),
            get_string('autoapproveshortname_desc', 'local_coursefiles'),
            \local_coursefiles\local\autoapprove::SHORTNAME,
            PARAM_RAW,
            80
        ));
        $settings->add(new admin_setting_configtext(
            'local_coursefiles/autoapprovearchive',
            get_string('autoapprovearchive', 'local_coursefiles'),
            get_string('autoapprovearchive_desc', 'local_coursefiles'),
            \local_coursefiles\local\autoapprove::ARCHIVE,
            PARAM_TEXT
        ));
        $settings->add(new admin_setting_configtext(
            'local_coursefiles/autoapprovenotify',
            get_string('autoapprovenotify', 'local_coursefiles'),
            get_string('autoapprovenotify_desc', 'local_coursefiles'),
            '',
            PARAM_USERNAME
        ));
    }
}
