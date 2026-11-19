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
 * Tests for planning when an activity becomes available.
 *
 * @package    local_coursefiles
 * @category   test
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(set_availability::class)]
final class availability_test extends \advanced_testcase {
    /**
     * A hidden page gets a date restriction, becomes visible, and loses the date again.
     */
    public function test_set_and_remove(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $time = time() + DAYSECS;

        $result = external_api::clean_returnvalue(
            set_availability::execute_returns(),
            set_availability::execute($page->cmid, $time)
        );
        $this->assertSame(1, $result['visible']);
        $tree = json_decode($DB->get_field('course_modules', 'availability', ['id' => $page->cmid]), true);
        $this->assertSame([['type' => 'date', 'd' => '>=', 't' => $time]], $tree['c']);
        $this->assertSame([false], $tree['showc']);

        set_availability::execute($page->cmid, 0);
        $this->assertNull($DB->get_field('course_modules', 'availability', ['id' => $page->cmid]));
    }

    /**
     * Other restrictions stay; an earlier date "from" is replaced.
     */
    public function test_with_date_keeps_other_conditions(): void {
        $current = json_encode(['op' => '&', 'showc' => [true, true], 'c' => [
            ['type' => 'group', 'id' => 7],
            ['type' => 'date', 'd' => '>=', 't' => 100],
        ]]);
        $tree = set_availability::with_date($current, 200, true);
        $this->assertSame([['type' => 'group', 'id' => 7], ['type' => 'date', 'd' => '>=', 't' => 200]], $tree['c']);
        $this->assertSame([true, false], $tree['showc']);

        $or = json_encode(['op' => '|', 'show' => true, 'c' => [['type' => 'group', 'id' => 7]]]);
        $tree = set_availability::with_date($or, 300, false);
        $this->assertSame('&', $tree['op']);
        $this->assertSame('|', $tree['c'][0]['op']);
        $this->assertSame([true, true], $tree['showc']);
    }

    /**
     * Without restricted access on the site, nothing is changed.
     */
    public function test_availability_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 0);
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        set_availability::execute($page->cmid, time() + DAYSECS);
    }
}
