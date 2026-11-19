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

/**
 * Tests for showing and hiding a whole course.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(set_course_visibility::class)]
final class course_visibility_test extends \advanced_testcase {
    /**
     * A teacher shows and hides their own course.
     */
    public function test_teacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->assertSame(1, set_course_visibility::execute($course->id, 1)['visible']);
        $this->assertSame('1', (string)get_course($course->id)->visible);
        $this->assertSame(0, set_course_visibility::execute($course->id, 0)['visible']);
    }

    /**
     * Students and non-editing teachers cannot.
     */
    public function test_student_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->expectException(\required_capability_exception::class);
        set_course_visibility::execute($course->id, 0);
    }
}
