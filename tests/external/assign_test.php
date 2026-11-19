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
 * Tests for creating assignments and changing their due date.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_assign::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_assign_duedate::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(update_assign::class)]
final class assign_test extends \advanced_testcase {
    /**
     * A teacher creates a hidden assignment with a worksheet and a due date, then moves the due date.
     */
    public function test_create_and_move_due_date(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 2]);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($teacher);

        $due = time() + DAYSECS;
        $result = create_assign::execute(
            $course->id,
            1,
            'Aufgabe Schleifen',
            '<p>Bitte bearbeiten.</p>',
            [['filename' => 'AB Schleifen.pdf', 'content' => base64_encode('%PDF-1.4')]],
            $due,
            0
        );

        $cm = get_coursemodule_from_id('assign', $result['cmid'], 0, false, MUST_EXIST);
        $this->assertSame('0', (string)$cm->visible);
        $this->assertSame(1, (int)$DB->get_field('course_sections', 'section', ['id' => $cm->section]));
        $record = $DB->get_record('assign', ['id' => $result['assignid']], '*', MUST_EXIST);
        $this->assertSame('Aufgabe Schleifen', $record->name);
        $this->assertSame($due, (int)$record->duedate);

        $context = \context_module::instance($cm->id);
        $files = get_file_storage()->get_area_files($context->id, 'mod_assign', 'introattachment', 0, 'filename', false);
        $this->assertSame(['AB Schleifen.pdf'], array_values(array_map(fn($f) => $f->get_filename(), $files)));

        $assign = new \assign($context, $cm, $course);
        $this->assertTrue((bool)$assign->get_submission_plugin_by_type('file')->is_enabled());
        $this->assertTrue((bool)$assign->get_feedback_plugin_by_type('file')->is_enabled());

        // Feedback files work on the new assignment.
        $feedback = set_feedback_files::execute(
            $result['assignid'],
            $student->id,
            [['filename' => 'korrigiert.pdf', 'content' => base64_encode('x')]]
        );
        $this->assertSame(1, $feedback['numfiles']);

        $later = $due + DAYSECS;
        $this->assertSame($later, set_assign_duedate::execute($result['cmid'], $later)['duedate']);
        $this->assertSame($later, (int)$DB->get_field('assign', 'duedate', ['id' => $result['assignid']]));
        $this->assertSame(0, set_assign_duedate::execute($result['cmid'], 0)['duedate']);
    }

    /**
     * A teacher corrects the description, renames, swaps an attached file and moves the due date; nothing else changes.
     */
    public function test_update_assign(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 1]);
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));

        $due = time() + DAYSECS;
        $result = create_assign::execute(
            $course->id,
            1,
            'Recherche',
            '<p>Recherchiere drei Beispiel.</p>',
            [
                ['filename' => 'AB.pdf', 'content' => base64_encode('%PDF-1.4')],
                ['filename' => 'Hinweise.pdf', 'content' => base64_encode('%PDF-1.4')],
            ],
            $due,
            1
        );

        // Only the description.
        $r = update_assign::execute($result['cmid'], '', true, '<p>Recherchiere drei Beispiele.</p>');
        $record = $DB->get_record('assign', ['id' => $result['assignid']], '*', MUST_EXIST);
        $this->assertSame('<p>Recherchiere drei Beispiele.</p>', $record->intro);
        $this->assertSame('Recherche', $record->name);
        $this->assertSame($due, (int)$record->duedate);
        $this->assertSame(['AB.pdf', 'Hinweise.pdf'], $r['files']);

        // Name, files (keep AB.pdf, drop Hinweise.pdf, add Quellen.pdf) and due date.
        $later = $due + DAYSECS;
        $r = update_assign::execute(
            $result['cmid'],
            'Recherche Datenschutz',
            false,
            '',
            true,
            ['AB.pdf'],
            [['filename' => 'Quellen.pdf', 'content' => base64_encode('%PDF-1.4')]],
            $later
        );
        $this->assertSame('Recherche Datenschutz', $r['name']);
        $this->assertSame(['AB.pdf', 'Quellen.pdf'], $r['files']);
        $this->assertSame($later, (int)$DB->get_field('assign', 'duedate', ['id' => $result['assignid']]));
        $this->assertSame('<p>Recherchiere drei Beispiele.</p>', $DB->get_field('assign', 'intro', ['id' => $result['assignid']]));
    }

    /**
     * Students cannot change assignments.
     */
    public function test_student_cannot_update(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));
        $result = create_assign::execute($course->id, 0, 'x', '', [], 0, 1);
        $this->setUser($gen->create_and_enrol($course, 'student'));

        $this->expectException(\required_capability_exception::class);
        update_assign::execute($result['cmid'], 'y');
    }

    /**
     * Students cannot create assignments.
     */
    public function test_student_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $this->expectException(\required_capability_exception::class);
        create_assign::execute($course->id, 0, 'x');
    }
}
