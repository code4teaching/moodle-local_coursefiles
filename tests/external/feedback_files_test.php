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
 * Tests for giving students feedback files in an assignment.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(set_feedback_files::class)]
final class feedback_files_test extends \advanced_testcase {
    /**
     * A teacher gives a student a corrected file, then replaces it with two others.
     */
    public function test_teacher_gives_and_replaces_feedback(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $assign = $gen->create_module('assign', ['course' => $course->id, 'assignfeedback_file_enabled' => 1]);
        $this->setUser($teacher);

        $file = ['filename' => 'korrigiert.pdf', 'content' => base64_encode('%PDF-1.4 korrigiert')];
        $result = set_feedback_files::execute($assign->id, $student->id, [$file]);
        $this->assertSame(1, $result['numfiles']);

        $context = \context_module::instance($assign->cmid);
        $stored = get_file_storage()->get_area_files($context->id, 'assignfeedback_file', 'feedback_files',
            $result['gradeid'], 'filename', false);
        $this->assertSame(['korrigiert.pdf'], array_values(array_map(fn($f) => $f->get_filename(), $stored)));
        $this->assertSame('1', (string)$DB->get_field('assignfeedback_file', 'numfiles', ['grade' => $result['gradeid']]));

        $again = set_feedback_files::execute($assign->id, $student->id, [
            ['filename' => 'a.pdf', 'content' => base64_encode('a')],
            ['filename' => 'b.pdf', 'content' => base64_encode('b')],
        ]);
        $this->assertSame($result['gradeid'], $again['gradeid']);
        $stored = get_file_storage()->get_area_files($context->id, 'assignfeedback_file', 'feedback_files',
            $again['gradeid'], 'filename', false);
        $this->assertCount(2, $stored);
        $this->assertSame('2', (string)$DB->get_field('assignfeedback_file', 'numfiles', ['grade' => $again['gradeid']]));
    }

    /**
     * Without feedback files in the assignment the call is refused with a clear message.
     */
    public function test_feedback_files_turned_off(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $assign = $gen->create_module('assign', ['course' => $course->id, 'assignfeedback_file_enabled' => 0]);
        $this->setUser($teacher);

        $this->expectExceptionMessage(get_string('feedbackfilesdisabled', 'local_coursefiles'));
        set_feedback_files::execute($assign->id, $student->id, [['filename' => 'x.pdf', 'content' => base64_encode('x')]]);
    }

    /**
     * Students cannot give feedback.
     */
    public function test_student_refused(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $student = $gen->create_and_enrol($course, 'student');
        $other = $gen->create_and_enrol($course, 'student');
        $assign = $gen->create_module('assign', ['course' => $course->id, 'assignfeedback_file_enabled' => 1]);
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        set_feedback_files::execute($assign->id, $other->id, [['filename' => 'x.pdf', 'content' => base64_encode('x')]]);
    }
}
