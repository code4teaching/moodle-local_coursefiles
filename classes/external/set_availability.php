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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Plans when an activity or resource becomes available: adds (or removes) a date restriction "from …" and makes the
 * activity visible, so Moodle releases it by itself at that time, for example at the start of the next lesson.
 * Other restrictions stay. Before the date students don't see the activity at all (hidebefore=1) or see it greyed
 * out with the date (hidebefore=0).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_availability extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'timefrom' => new external_value(PARAM_INT, 'Available from this time (Unix timestamp), 0 = remove the date'),
            'hidebefore' => new external_value(PARAM_INT, '1 = completely hidden before, 0 = shown greyed out', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Sets the date restriction.
     *
     * @param int $cmid
     * @param int $timefrom
     * @param int $hidebefore
     * @return array
     */
    public static function execute(int $cmid, int $timefrom, int $hidebefore = 1): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(),
            ['cmid' => $cmid, 'timefrom' => $timefrom, 'hidebefore' => $hidebefore]);
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('moodle/course:activityvisibility', $context);
        if (empty($CFG->enableavailability)) {
            throw new \moodle_exception('availabilitydisabled', 'local_coursefiles');
        }

        $tree = self::with_date($cm->availability, $params['timefrom'], (bool)$params['hidebefore']);
        $json = $tree ? json_encode($tree) : null;
        if ($json) {
            new \core_availability\tree(json_decode($json)); // Throws if Moodle would not accept the structure.
        }
        $DB->set_field('course_modules', 'availability', $json, ['id' => $cm->id]);
        if ($params['timefrom'] && !$cm->visible) {
            set_coursemodule_visible($cm->id, 1);
        }
        rebuild_course_cache($cm->course, true);
        $updated = get_coursemodule_from_id('', $cm->id, 0, false, MUST_EXIST);
        \core\event\course_module_updated::create_from_cm($updated, $context)->trigger();

        return ['cmid' => (int)$cm->id, 'visible' => (int)$updated->visible, 'availability' => (string)$json];
    }

    /**
     * The availability structure with our date condition: date conditions "from" at the top level are replaced, all
     * other conditions stay. Returns null if nothing is left.
     *
     * @param string|null $current JSON from course_modules.availability
     * @param int $timefrom 0 = only remove the date
     * @param bool $hidebefore
     * @return array|null
     */
    public static function with_date(?string $current, int $timefrom, bool $hidebefore): ?array {
        $tree = $current ? json_decode($current, true) : null;
        if (!is_array($tree) || !isset($tree['op'])) {
            $tree = ['op' => '&', 'c' => [], 'showc' => []];
        }
        $date = ['type' => 'date', 'd' => '>=', 't' => $timefrom];
        if ($tree['op'] === '&') {
            $conditions = [];
            $show = [];
            foreach ($tree['c'] ?? [] as $i => $condition) {
                if (($condition['type'] ?? '') === 'date' && ($condition['d'] ?? '') === '>=') {
                    continue;
                }
                $conditions[] = $condition;
                $show[] = $tree['showc'][$i] ?? true;
            }
            if ($timefrom) {
                $conditions[] = $date;
                $show[] = !$hidebefore;
            }
            if (!$conditions) {
                return null;
            }
            return ['op' => '&', 'c' => $conditions, 'showc' => $show];
        }
        if (!$timefrom) {
            return $tree;
        }
        // Another operator (or, not): keep that part as it is and add the date next to it.
        unset($tree['show'], $tree['showc']);
        return ['op' => '&', 'c' => [$tree, $date], 'showc' => [true, !$hidebefore]];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'visible' => new external_value(PARAM_INT, 'Visible (1) or hidden (0)'),
            'availability' => new external_value(PARAM_RAW, 'The new availability JSON, empty if none'),
        ]);
    }
}
