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
 * Tests for reading and setting the self enrolment of a course.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_self_enrolment::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_self_enrolment::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursefiles\local\selfenrol::class)]
final class selfenrol_test extends \advanced_testcase {
    /**
     * Turns the self enrolment plugin on for the site.
     */
    private function enable_self(): void {
        $enabled = enrol_get_plugins(true);
        $enabled['self'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
    }

    /**
     * A course without the method gets one; key and status can be changed; a student can then enrol with the key.
     */
    public function test_set_and_get(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_self();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $DB->delete_records('enrol', ['courseid' => $course->id, 'enrol' => 'self']);

        $result = get_self_enrolment::execute($course->id);
        $result = external_api::clean_returnvalue(get_self_enrolment::execute_returns(), $result);
        $this->assertSame(0, $result['exists']);

        $result = set_self_enrolment::execute($course->id, 1, ' Schleife42 ');
        $result = external_api::clean_returnvalue(set_self_enrolment::execute_returns(), $result);
        $this->assertSame(1, $result['exists']);
        $this->assertSame(1, $result['enabled']);
        $this->assertSame('Schleife42', $result['password']);
        $instance = $DB->get_record('enrol', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $this->assertEquals(ENROL_INSTANCE_ENABLED, $instance->status);
        $this->assertEquals(get_config('enrol_self', 'roleid'), $instance->roleid);

        $result = set_self_enrolment::execute($course->id, 0, 'Neu');
        $this->assertSame(0, $result['enabled']);
        $this->assertSame('Neu', $DB->get_field('enrol', 'password', ['id' => $instance->id]));
        $this->assertEquals(1, $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'self']));

        $result = external_api::clean_returnvalue(get_self_enrolment::execute_returns(),
            get_self_enrolment::execute($course->id));
        $this->assertSame('Neu', $result['password']);
        $this->assertSame(0, $result['enabled']);
    }

    /**
     * Refused when the plugin is off on the site.
     */
    public function test_site_disabled(): void {
        $this->resetAfterTest();
        $enabled = enrol_get_plugins(true);
        unset($enabled['self']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\moodle_exception::class);
        set_self_enrolment::execute($course->id, 1, 'x');
    }

    /**
     * A student may not read or change it.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        $this->enable_self();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_self_enrolment::execute($course->id);
    }
}
