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

/**
 * Self enrolment of a course: the first "Self enrolment" method, its key and whether it is on.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selfenrol {
    /**
     * The first self enrolment method of the course, or null.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function instance(int $courseid): ?\stdClass {
        foreach (enrol_get_instances($courseid, false) as $instance) {
            if ($instance->enrol === 'self') {
                return $instance;
            }
        }
        return null;
    }

    /**
     * What a caller needs to know about the self enrolment of a course.
     *
     * @param \stdClass $course
     * @param \stdClass|null $instance
     * @return array
     */
    public static function describe(\stdClass $course, ?\stdClass $instance): array {
        return [
            'courseid' => (int)$course->id,
            'siteenabled' => enrol_is_enabled('self') ? 1 : 0,
            'exists' => $instance ? 1 : 0,
            'instanceid' => $instance ? (int)$instance->id : 0,
            'enabled' => ($instance && (int)$instance->status === ENROL_INSTANCE_ENABLED) ? 1 : 0,
            'password' => $instance ? (string)$instance->password : '',
            'coursevisible' => (int)$course->visible,
            'url' => (new \moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false),
        ];
    }

    /**
     * Sets key and status. Creates the method with the site defaults (role: student) if the course has none.
     *
     * @param \stdClass $course
     * @param bool $enabled
     * @param string $password empty = no key
     * @return \stdClass the method afterwards
     */
    public static function set(\stdClass $course, bool $enabled, string $password): \stdClass {
        global $DB;
        $context = \context_course::instance($course->id);
        $plugin = enrol_get_plugin('self');
        if (!$plugin || !enrol_is_enabled('self')) {
            throw new \moodle_exception('selfenroldisabled', 'local_coursefiles');
        }
        if ($password !== '') {
            if ($plugin->get_config('usepasswordpolicy')) {
                $errmsg = '';
                if (!check_password_policy($password, $errmsg)) {
                    throw new \invalid_parameter_exception(strip_tags($errmsg));
                }
            }
        } else if ($enabled && $plugin->get_config('requirepassword')) {
            throw new \moodle_exception('selfenrolkeyrequired', 'local_coursefiles');
        }
        $status = $enabled ? ENROL_INSTANCE_ENABLED : ENROL_INSTANCE_DISABLED;

        $instance = self::instance($course->id);
        if (!$instance) {
            require_capability('moodle/course:enrolconfig', $context);
            if (!$plugin->can_add_instance($course->id)) {
                throw new \moodle_exception('selfenrolcannotadd', 'local_coursefiles');
            }
            $fields = $plugin->get_instance_defaults();
            $fields['status'] = $status;
            $fields['password'] = $password;
            $id = $plugin->add_instance($course, $fields);
            return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        }

        if ((string)$instance->password !== $password) {
            $instance->password = $password;
            $instance->timemodified = time();
            $DB->update_record('enrol', $instance);
            \core\event\enrol_instance_updated::create_from_record($instance)->trigger();
        }
        if ((int)$instance->status !== $status) {
            $plugin->update_status($instance, $status);
        }
        return $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
    }
}
