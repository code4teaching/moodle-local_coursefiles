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
#[\PHPUnit\Framework\Attributes\CoversClass(get_assign_grading::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(grade_submission::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(release_grades::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursefiles\local\grading::class)]
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
        $this->assertSame(1, (int)$record->submissiondrafts);   // Submissions stay drafts until students submit.

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

    /**
     * Grading with a site scale and marking workflow: the grade reaches the gradebook only once it is released.
     */
    public function test_grade_with_scale_and_release(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $scale = $gen->create_scale(['name' => 'Noten 1–6', 'scale' => '6, 5, 4, 3, 2, 1', 'courseid' => 0]);
        $this->setUser($teacher);

        $result = create_assign::execute($course->id, 0, 'Aufgabe 1', '', [], 0, 1, 0, 'Noten 1–6', 1);
        $record = $DB->get_record('assign', ['id' => $result['assignid']], '*', MUST_EXIST);
        $this->assertSame(-(int)$scale->id, (int)$record->grade);
        $this->assertSame(1, (int)$record->markingworkflow);

        $info = get_assign_grading::execute($result['cmid']);
        $info = \core_external\external_api::clean_returnvalue(get_assign_grading::execute_returns(), $info);
        $this->assertSame('scale', $info['type']);
        $this->assertSame(['6', '5', '4', '3', '2', '1'], $info['scaleitems']);
        $this->assertSame([(int)$student->id], array_column($info['users'], 'userid'));
        $this->assertEquals(-1, $info['users'][0]['grade']);

        // Item 5 of the scale is "2"; ready for release, not yet visible.
        $r = grade_submission::execute($result['cmid'], $student->id, true, 5, true, '<p>Gut gemacht.</p>', 'readyforrelease');
        $this->assertEquals(5, $r['grade']);
        $this->assertSame('readyforrelease', $r['workflowstate']);
        $gradebook = grade_get_grades($course->id, 'mod', 'assign', $result['assignid'], $student->id);
        $this->assertNull($gradebook->items[0]->grades[$student->id]->grade);

        $this->assertSame([(int)$student->id], release_grades::execute($result['cmid'])['released']);
        $gradebook = grade_get_grades($course->id, 'mod', 'assign', $result['assignid'], $student->id);
        $this->assertEquals(5, $gradebook->items[0]->grades[$student->id]->grade);

        $info = get_assign_grading::execute($result['cmid']);
        $this->assertSame('released', $info['users'][0]['workflowstate']);
        $this->assertSame('<p>Gut gemacht.</p>', $info['users'][0]['feedback']);

        // Not an item of the scale.
        try {
            grade_submission::execute($result['cmid'], $student->id, true, 7);
            $this->fail('A grade outside the scale was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('gradenotinscale', $e->errorcode);
        }
        // Somebody has a grade, so the grade type stays.
        $this->expectException(\moodle_exception::class);
        update_assign::execute($result['cmid'], '', false, '', false, [], [], -1, true, 15);
    }

    /**
     * Points up to 15, no workflow: grades go to the gradebook at once; the type can change while nobody has a grade.
     */
    public function test_points_and_no_grade(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($teacher);

        $result = create_assign::execute($course->id, 0, 'Arbeitsblatt', '', [], 0, 1, 15);
        $this->assertSame(15, (int)$DB->get_field('assign', 'grade', ['id' => $result['assignid']]));
        try {
            grade_submission::execute($result['cmid'], $student->id, true, 16);
            $this->fail('More than the maximum was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('gradetoohigh', $e->errorcode);
        }

        // Nobody has a grade yet: switch to "no grade" and on with the workflow.
        $r = update_assign::execute($result['cmid'], '', false, '', false, [], [], -1, true, 0, '', 1);
        $this->assertSame(0, $r['grade']);
        $this->assertSame(1, $r['markingworkflow']);
        try {
            grade_submission::execute($result['cmid'], $student->id, true, 3);
            $this->fail('A grade was accepted for an ungraded assignment.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nogradetype', $e->errorcode);
        }
        // Feedback alone works.
        $r = grade_submission::execute($result['cmid'], $student->id, false, -1, true, '<p>Danke!</p>');
        $this->assertEquals(-1, $r['grade']);
        $this->assertSame('<p>Danke!</p>', get_assign_grading::execute($result['cmid'])['users'][0]['feedback']);

        // Back to points and without workflow: grades are visible at once.
        update_assign::execute($result['cmid'], '', false, '', false, [], [], -1, true, 15, '', 0);
        grade_submission::execute($result['cmid'], $student->id, true, 12);
        $gradebook = grade_get_grades($course->id, 'mod', 'assign', $result['assignid'], $student->id);
        $this->assertEquals(12, $gradebook->items[0]->grades[$student->id]->grade);
    }

    /**
     * Students cannot read or change grades.
     */
    public function test_student_cannot_grade(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));
        $result = create_assign::execute($course->id, 0, 'Sichtbar', '', [], 0, 1, 15);
        $student = $gen->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        grade_submission::execute($result['cmid'], $student->id, true, 15);
    }
}
