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
final class requests_test extends \advanced_testcase {
    /**
     * A teacher asks, the admin corrects the name and approves: the course exists and the teacher is enrolled.
     */
    public function test_request_and_approve(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enablecourserequests', 1);
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
        $res = approve_course_request::execute($r['requestid'], '2026 12FI2 SebH Objektorientierte Programmierung',
            '2026 12FI2 SebH OOP', $cat->id);
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
        set_config('enablecourserequests', 1);
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
        set_config('enablecourserequests', 1);
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
        set_config('enablecourserequests', 1);
        $this->setUser($this->getDataGenerator()->create_user());
        $r = request_course::execute('2026 11FI2 SebH Netzwerke', '2026 11FI2 SebH NW');
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        approve_course_request::execute($r['requestid']);
    }
}
