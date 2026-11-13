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

namespace local_coursefiles;

/**
 * Tests for the site role for teachers.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(local\teacherrole::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(task\sync_teacherrole::class)]
final class teacherrole_test extends \advanced_testcase {
    /**
     * A system role like "Web service user" and the settings pointing to it.
     *
     * @return int role id
     */
    private function setup_roles(): int {
        global $DB;
        $siterole = create_role('Web service user', 'wsuser', '');
        set_role_contextlevels($siterole, [CONTEXT_SYSTEM]);
        set_config('teacherrole', $DB->get_field('role', 'id', ['shortname' => 'editingteacher']), 'local_coursefiles');
        set_config('siterole', $siterole, 'local_coursefiles');
        return $siterole;
    }

    /**
     * Enrolling someone as teacher gives them the site role; students do not get it.
     */
    public function test_observer(): void {
        $this->resetAfterTest();
        $siterole = $this->setup_roles();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $system = \context_system::instance();
        $this->assertTrue(user_has_role_assignment($teacher->id, $siterole, $system->id));
        $this->assertFalse(user_has_role_assignment($student->id, $siterole, $system->id));
    }

    /**
     * The daily task catches teachers who were there before the feature was set up.
     */
    public function test_sync(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $siterole = $this->setup_roles();
        $system = \context_system::instance();
        $this->assertFalse(user_has_role_assignment($teacher->id, $siterole, $system->id));

        $this->assertSame(1, local\teacherrole::sync());
        $this->assertTrue(user_has_role_assignment($teacher->id, $siterole, $system->id));
        $this->assertSame(0, local\teacherrole::sync());
    }

    /**
     * Site admins have every capability anyway and do not get the role.
     */
    public function test_admin_skipped(): void {
        $this->resetAfterTest();
        $siterole = $this->setup_roles();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user(get_admin()->id, $course->id, 'editingteacher');
        $this->assertFalse(user_has_role_assignment(get_admin()->id, $siterole, \context_system::instance()->id));
    }

    /**
     * Saving the setting gives the role to all current teachers at once.
     */
    public function test_setting_callback(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $siterole = $this->setup_roles();
        $callback = [local\teacherrole::class, 'sync'];
        $this->assertTrue(is_callable($callback));
        $callback('local_coursefiles/siterole');
        $this->assertTrue(user_has_role_assignment($teacher->id, $siterole, \context_system::instance()->id));
    }

    /**
     * Without a site role set nothing happens.
     */
    public function test_off(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $gen->create_and_enrol($course, 'editingteacher');
        $this->assertNull(local\teacherrole::roles());
        $this->assertSame(0, local\teacherrole::sync());
    }
}
