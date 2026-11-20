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

/**
 * Tests for the course request functions.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(request_course::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_course_requests::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(approve_course_request::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(reject_course_request::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursefiles\local\requests::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursefiles\local\autoapprove::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_autoapproved_courses::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(check_autoapproved_course::class)]
final class requests_test extends \advanced_testcase {
    /**
     * Turns course requests on and lets all users request courses (no standard role has this capability).
     */
    private function enable_requests(): void {
        global $DB;
        set_config('enablecourserequests', 1);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('moodle/course:request', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
    }

    /**
     * A teacher asks, the admin corrects the name and approves: the course exists and the teacher is enrolled.
     */
    public function test_request_and_approve(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_requests();
        $cat = $this->getDataGenerator()->create_category(['name' => 'FI']);
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $r = request_course::execute('2026 11FI2 SebH Objektorientierte Programmierung', '2026 11FI2 SebH OOP', 'Bitte');
        $r = external_api::clean_returnvalue(request_course::execute_returns(), $r);
        $mine = external_api::clean_returnvalue(get_course_requests::execute_returns(), get_course_requests::execute());
        $this->assertCount(1, $mine['requests']);
        $this->assertSame('2026 11FI2 SebH OOP', $mine['requests'][0]['shortname']);

        $this->setAdminUser();
        $all = external_api::clean_returnvalue(get_course_requests::execute_returns(), get_course_requests::execute(1));
        $this->assertCount(1, $all['requests']);
        $res = approve_course_request::execute(
            $r['requestid'],
            '2026 12FI2 SebH Objektorientierte Programmierung',
            '2026 12FI2 SebH OOP',
            $cat->id
        );
        $res = external_api::clean_returnvalue(approve_course_request::execute_returns(), $res);
        $course = get_course($res['courseid']);
        $this->assertSame('2026 12FI2 SebH OOP', $course->shortname);
        $this->assertEquals($cat->id, $course->category);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $teacher));
        $this->assertFalse($DB->record_exists('course_request', ['id' => $r['requestid']]));
    }

    /**
     * Rejecting deletes the request.
     */
    public function test_reject(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_requests();
        $this->setUser($this->getDataGenerator()->create_user());
        $r = request_course::execute('2026 11FI2 SebH Datenbanken', '2026 11FI2 SebH DB');
        $this->setAdminUser();
        reject_course_request::execute($r['requestid'], 'Klasse fehlt');
        $this->assertFalse($DB->record_exists('course_request', ['id' => $r['requestid']]));
    }

    /**
     * Short names must be unique, also among pending requests.
     */
    public function test_shortname_taken(): void {
        $this->resetAfterTest();
        $this->enable_requests();
        $this->getDataGenerator()->create_course(['shortname' => '2026 11FI2 SebH OOP']);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        request_course::execute('2026 11FI2 SebH Objektorientierte Programmierung', '2026 11FI2 SebH OOP');
    }

    /**
     * Without the site setting there are no requests.
     */
    public function test_disabled(): void {
        $this->resetAfterTest();
        set_config('enablecourserequests', 0);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        request_course::execute('A', 'B');
    }

    /**
     * Teachers cannot see or approve other requests.
     */
    public function test_approve_requires_capability(): void {
        $this->resetAfterTest();
        $this->enable_requests();
        $this->setUser($this->getDataGenerator()->create_user());
        $r = request_course::execute('2026 11FI2 SebH Netzwerke', '2026 11FI2 SebH NW');
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        approve_course_request::execute($r['requestid']);
    }

    /**
     * With automatic approval the course is created at once, hidden, in the category of the class; the checker is told.
     */
    public function test_autoapprove(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_requests();
        $checker = $this->getDataGenerator()->create_user(['username' => 'pruefer']);
        set_config('autoapprove', 1, 'local_coursefiles');
        set_config('autoapprovenotify', 'pruefer', 'local_coursefiles');
        $cat = $this->getDataGenerator()->create_category(['name' => 'BS']);
        $archive = $this->getDataGenerator()->create_category(['name' => 'Archiv']);
        $this->getDataGenerator()->create_course(['fullname' => '2025 11FI2 MusA Datenbanken', 'category' => $cat->id]);
        $this->getDataGenerator()->create_course(['fullname' => '2024 11FI2 MusA Netzwerke', 'category' => $archive->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $sink = $this->redirectMessages();
        $r = request_course::execute('2026 11FI2 SebH Objektorientierte Programmierung', '2026 11FI2 SebH OOP');
        $r = external_api::clean_returnvalue(request_course::execute_returns(), $r);
        $this->assertSame(0, $r['requestid']);
        $this->assertGreaterThan(0, $r['courseid']);
        $course = get_course($r['courseid']);
        $this->assertEquals($cat->id, $course->category);
        $this->assertEquals(0, $course->visible);
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $teacher));
        $this->assertFalse($DB->record_exists('course_request', []));
        $mine = array_filter($sink->get_messages(), fn($m) => $m->component === 'local_coursefiles');
        $this->assertCount(1, $mine);
        $this->assertEquals($checker->id, reset($mine)->useridto);

        // The approver sees it in the list until it is marked as checked.
        $this->setAdminUser();
        $list = external_api::clean_returnvalue(get_autoapproved_courses::execute_returns(), get_autoapproved_courses::execute());
        $this->assertTrue($list['enabled']);
        $this->assertCount(1, $list['courses']);
        $entry = $list['courses'][0];
        $this->assertSame($course->id, (int)$entry['courseid']);
        $this->assertSame(fullname($teacher), $entry['teachers']);
        check_autoapproved_course::execute($entry['id']);
        $this->assertCount(0, get_autoapproved_courses::execute()['courses']);
        $this->assertCount(1, get_autoapproved_courses::execute(1)['courses']);

        // Teachers cannot read the list.
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        get_autoapproved_courses::execute();
    }

    /**
     * Deleting the course removes it from the list.
     */
    public function test_autoapproved_course_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_requests();
        set_config('autoapprove', 1, 'local_coursefiles');
        $cat = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['fullname' => '2025 11FI2 MusA Datenbanken', 'category' => $cat->id]);
        $this->setUser($this->getDataGenerator()->create_user());
        $sink = $this->redirectMessages();
        $r = request_course::execute('2026 11FI2 SebH Objektorientierte Programmierung', '2026 11FI2 SebH OOP');
        $this->assertTrue($DB->record_exists('local_coursefiles_autoappr', ['courseid' => $r['courseid']]));
        $this->setAdminUser();
        delete_course($r['courseid'], false);
        $this->assertFalse($DB->record_exists('local_coursefiles_autoappr', ['courseid' => $r['courseid']]));
        $sink->close();
    }

    /**
     * A class without a course, or a name off the scheme, stays a normal request.
     */
    public function test_autoapprove_falls_back(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_requests();
        set_config('autoapprove', 1, 'local_coursefiles');
        $this->setUser($this->getDataGenerator()->create_user());
        $r = request_course::execute('2026 12FI9 SebH Netzwerke', '2026 12FI9 SebH NW');
        $this->assertGreaterThan(0, $r['requestid']);
        $this->assertSame(0, $r['courseid']);
        $cat = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['fullname' => '2025 11FI2 MusA Datenbanken', 'category' => $cat->id]);
        $r = request_course::execute('OOP für die 11FI2', '11FI2-OOP');
        $this->assertGreaterThan(0, $r['requestid']);
        $this->assertEquals(2, $DB->count_records('course_request'));
    }
}
