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
 * Tests for the Course Files web service functions.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(create_resource::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(create_folder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(update_files::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_visibility::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursefiles\local\files::class)]
final class coursefiles_test extends \advanced_testcase {
    /**
     * A file as the web service expects it.
     *
     * @param string $filename
     * @param string $content
     * @return array
     */
    private function file(string $filename, string $content): array {
        return ['filename' => $filename, 'content' => base64_encode($content)];
    }

    /**
     * Names and contents of the files in a module's content area.
     *
     * @param int $cmid
     * @param string $component
     * @return array filename => content
     */
    private function stored(int $cmid, string $component): array {
        $context = \context_module::instance($cmid);
        $result = [];
        foreach (get_file_storage()->get_area_files($context->id, $component, 'content', 0, 'filename', false) as $f) {
            $result[$f->get_filename()] = $f->get_content();
        }
        return $result;
    }

    /**
     * A PDF lands as a hidden file resource in the requested section.
     */
    public function test_create_resource(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);

        $result = create_resource::execute($course->id, 2, 'Worksheet 02-06', $this->file('02-06.pdf', '%PDF-1.4 test'));
        $result = external_api::clean_returnvalue(create_resource::execute_returns(), $result);

        $cm = get_coursemodule_from_id('resource', $result['cmid'], 0, true, MUST_EXIST);
        $this->assertEquals(2, $cm->sectionnum);
        $this->assertEquals(0, $cm->visible);
        $this->assertSame('Worksheet 02-06', $DB->get_field('resource', 'name', ['id' => $result['resourceid']]));
        $this->assertSame(['02-06.pdf' => '%PDF-1.4 test'], $this->stored($cm->id, 'mod_resource'));
    }

    /**
     * A folder gets all files.
     */
    public function test_create_folder(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);

        $result = create_folder::execute($course->id, 1, 'Material', [
            $this->file('a.pdf', 'A'), $this->file('b.java', 'class B {}'),
        ], '', 1);
        $result = external_api::clean_returnvalue(create_folder::execute_returns(), $result);

        $cm = get_coursemodule_from_id('folder', $result['cmid'], 0, true, MUST_EXIST);
        $this->assertEquals(1, $cm->visible);
        $this->assertSame(['a.pdf' => 'A', 'b.java' => 'class B {}'], $this->stored($cm->id, 'mod_folder'));
    }

    /**
     * Replacing the file keeps the activity and raises the revision.
     */
    public function test_update_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $created = create_resource::execute($course->id, 0, 'Old', $this->file('old.pdf', 'old'));

        $result = update_files::execute($created['cmid'], [$this->file('new.pdf', 'new')], 'New');
        $result = external_api::clean_returnvalue(update_files::execute_returns(), $result);

        $this->assertSame($created['cmid'], $result['cmid']);
        $this->assertSame(2, $result['revision']);
        $this->assertSame('New', $DB->get_field('resource', 'name', ['id' => $created['resourceid']]));
        $this->assertSame(['new.pdf' => 'new'], $this->stored($created['cmid'], 'mod_resource'));
    }

    /**
     * A file resource takes exactly one file.
     */
    public function test_update_files_resource_one_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $created = create_resource::execute($course->id, 0, 'Sheet', $this->file('a.pdf', 'a'));

        $this->expectException(\moodle_exception::class);
        update_files::execute($created['cmid'], [$this->file('a.pdf', 'a'), $this->file('b.pdf', 'b')]);
    }

    /**
     * Only file resources and folders can get new files.
     */
    public function test_update_files_other_module(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        update_files::execute($page->cmid, [$this->file('a.pdf', 'a')]);
    }

    /**
     * Content that is not base64 is refused before anything is created.
     */
    public function test_invalid_base64(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        try {
            create_resource::execute($course->id, 0, 'Broken', ['filename' => 'x.pdf', 'content' => '***']);
            $this->fail('Expected invalidcontent');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidcontent', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('resource', ['course' => $course->id]));
    }

    /**
     * Activities can be hidden and shown again.
     */
    public function test_set_visibility(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = set_visibility::execute($page->cmid, 0);
        $result = external_api::clean_returnvalue(set_visibility::execute_returns(), $result);
        $this->assertSame(0, $result['visible']);
        $this->assertEquals(0, $DB->get_field('course_modules', 'visible', ['id' => $page->cmid]));

        set_visibility::execute($page->cmid, 1);
        $this->assertEquals(1, $DB->get_field('course_modules', 'visible', ['id' => $page->cmid]));
    }

    /**
     * Students cannot upload files.
     */
    public function test_create_resource_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        create_resource::execute($course->id, 0, 'Sheet', $this->file('a.pdf', 'a'));
    }

    /**
     * Students cannot hide activities.
     */
    public function test_set_visibility_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        set_visibility::execute($page->cmid, 0);
    }
}
