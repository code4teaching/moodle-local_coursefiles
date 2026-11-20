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
 * Approves course requests at once when they follow the naming scheme and the class already has a course.
 *
 * Scheme: "school year, class, initials, subject", e.g. "2026 11FI2 SebH Objektorientierte Programmierung" with the
 * short name "2026 11FI2 SebH OOP". The new course goes into the category of the newest course of the same class,
 * hidden, with the requester as teacher. One person chosen in the settings gets a message to check it.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autoapprove {
    /** @var string full name: school year, class, initials, subject */
    const FULLNAME = '/^20\d{2} [0-9A-Za-zÄÖÜäöü][0-9A-Za-zÄÖÜäöü-]{1,9} [A-Za-zÄÖÜäöüß]{2,6} \S.*$/u';
    /** @var string short name: school year, class, initials, short subject */
    const SHORTNAME = '/^20\d{2} [0-9A-Za-zÄÖÜäöü][0-9A-Za-zÄÖÜäöü-]{1,9} [A-Za-zÄÖÜäöüß]{2,6} \S{1,12}$/u';

    /**
     * Whether automatic approval is turned on.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool)get_config('local_coursefiles', 'autoapprove');
    }

    /**
     * Whether full and short name follow the scheme and agree in year, class and initials.
     *
     * @param string $fullname
     * @param string $shortname
     * @return bool
     */
    public static function follows_scheme(string $fullname, string $shortname): bool {
        if (!preg_match(self::FULLNAME, $fullname) || !preg_match(self::SHORTNAME, $shortname)) {
            return false;
        }
        $a = array_slice(explode(' ', $fullname), 0, 3);
        $b = array_slice(explode(' ', $shortname), 0, 3);
        return \core_text::strtolower(implode(' ', $a)) === \core_text::strtolower(implode(' ', $b));
    }

    /**
     * Category of the newest course of the same class, leaving out archive categories.
     *
     * @param string $class e.g. 11FI2
     * @return \core_course_category|null
     */
    public static function category_for_class(string $class): ?\core_course_category {
        global $DB;
        $class = \core_text::strtolower($class);
        $like = $DB->sql_like('fullname', ':pattern', false);
        $records = $DB->get_records_select(
            'course',
            "id <> :site AND $like",
            ['site' => SITEID, 'pattern' => '% ' . $DB->sql_like_escape($class) . ' %'],
            'id DESC',
            'id, fullname, category',
            0,
            50
        );
        foreach ($records as $record) {
            $parts = explode(' ', trim($record->fullname));
            if (count($parts) < 2 || \core_text::strtolower($parts[1]) !== $class) {
                continue;
            }
            $category = \core_course_category::get($record->category, IGNORE_MISSING, true);
            if (!$category || preg_match('/archiv/i', $category->get_nested_name(false))) {
                continue;
            }
            return $category;
        }
        return null;
    }

    /**
     * Creates the course at once if all checks pass.
     *
     * @param string $fullname
     * @param string $shortname
     * @param string $reason
     * @return array|null ['courseid' => int, 'categoryname' => string], or null if it stays a normal request
     */
    public static function try_approve(string $fullname, string $shortname, string $reason): ?array {
        global $DB, $USER;
        if (!self::enabled() || !self::follows_scheme($fullname, $shortname)) {
            return null;
        }
        $category = self::category_for_class(explode(' ', $fullname)[1]);
        if (!$category) {
            return null;
        }
        // Store the request without core's "new request" message to all approvers, then approve it.
        $transaction = $DB->start_delegated_transaction();
        $id = $DB->insert_record('course_request', (object)[
            'fullname' => $fullname,
            'shortname' => $shortname,
            'summary' => '',
            'summaryformat' => FORMAT_HTML,
            'category' => $category->id,
            'reason' => $reason,
            'requester' => $USER->id,
        ]);
        $courseid = (int)requests::load((int)$id)->approve();
        course_change_visibility($courseid, false);
        $DB->insert_record('local_coursefiles_autoappr', (object)['courseid' => $courseid, 'timecreated' => time(), 'timechecked' => 0]);
        $transaction->allow_commit();

        self::notify($courseid, $category);
        return ['courseid' => $courseid, 'categoryname' => $category->get_nested_name(false)];
    }

    /**
     * The person who checks automatically created courses.
     *
     * @return \stdClass
     */
    public static function checker(): \stdClass {
        global $DB, $CFG;
        $username = trim((string)get_config('local_coursefiles', 'autoapprovenotify'));
        if ($username !== '') {
            $user = $DB->get_record('user', ['username' => \core_text::strtolower($username),
                'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0, 'suspended' => 0]);
            if ($user) {
                return $user;
            }
        }
        return get_admin();
    }

    /**
     * Tells the checker about the new course.
     *
     * @param int $courseid
     * @param \core_course_category $category
     */
    private static function notify(int $courseid, \core_course_category $category): void {
        global $USER;
        $course = get_course($courseid);
        $url = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $a = (object)[
            'fullname' => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'category' => $category->get_nested_name(false),
            'requester' => fullname($USER),
            'url' => $url->out(false),
        ];
        $message = new \core\message\message();
        $message->component = 'local_coursefiles';
        $message->name = 'courseautoapproved';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = self::checker();
        $message->subject = get_string('autoapprovedsubject', 'local_coursefiles', $a);
        $message->fullmessage = get_string('autoapprovedbody', 'local_coursefiles', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $a->fullname;
        $message->courseid = $courseid;
        message_send($message);
    }

    /**
     * Automatically created courses for the approver, newest first. Who asked is read from the course's teachers,
     * so the plugin keeps no personal data.
     *
     * @param bool $withchecked also the ones already checked
     * @return array
     */
    public static function list(bool $withchecked): array {
        global $DB, $CFG;
        $where = $withchecked ? '' : 'WHERE a.timechecked = 0';
        $records = $DB->get_records_sql(
            "SELECT a.id, a.courseid, a.timecreated, a.timechecked, c.fullname, c.shortname, c.category, c.visible
               FROM {local_coursefiles_autoappr} a
               JOIN {course} c ON c.id = a.courseid
             $where
           ORDER BY a.timecreated DESC, a.id DESC"
        );
        $out = [];
        foreach ($records as $r) {
            $category = \core_course_category::get($r->category, IGNORE_MISSING, true);
            $context = \context_course::instance($r->courseid);
            $teachers = $CFG->creatornewroleid ? get_role_users((int)$CFG->creatornewroleid, $context, false, 'u.*') : [];
            $out[] = [
                'id' => (int)$r->id,
                'courseid' => (int)$r->courseid,
                'fullname' => $r->fullname,
                'shortname' => $r->shortname,
                'categoryid' => (int)$r->category,
                'categoryname' => $category ? $category->get_nested_name(false) : '',
                'visible' => (int)$r->visible,
                'teachers' => implode(', ', array_map('fullname', $teachers)),
                'timecreated' => (int)$r->timecreated,
                'timechecked' => (int)$r->timechecked,
            ];
        }
        return $out;
    }
}
