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

namespace local_coursefiles\local;

/**
 * Grading helpers: grade type of an assignment (points, a scale by name, or none) and the scale items.
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading {
    /**
     * The value for assign.grade: maximum points (> 0), 0 for no grade, or minus the id of the scale named $scalename.
     *
     * @param int $grade Maximum points, 0 = no grade (ignored when $scalename is given)
     * @param string $scalename Name of a site scale or a scale of this course ("" = use $grade)
     * @param \stdClass $course
     * @return int
     */
    public static function grade_value(int $grade, string $scalename, \stdClass $course): int {
        global $DB;
        $scalename = trim($scalename);
        if ($scalename !== '') {
            $scales = $DB->get_records_select(
                'scale',
                'name = ? AND (courseid = 0 OR courseid = ?)',
                [$scalename, $course->id],
                'courseid DESC, id ASC',
                'id',
                0,
                1
            );
            if (!$scales) {
                throw new \moodle_exception('scalenotfound', 'local_coursefiles', '', s($scalename));
            }
            return -(int)reset($scales)->id;
        }
        if ($grade < 0 || $grade > 1000) {
            throw new \invalid_parameter_exception('grade must be between 0 and 1000');
        }
        return $grade;
    }

    /**
     * Describes the grade type of an assignment record.
     *
     * @param \stdClass $instance Record from the assign table
     * @return array type (point, scale, none), grademax, scalename, scaleitems
     */
    public static function describe(\stdClass $instance): array {
        global $DB;
        $grade = (int)$instance->grade;
        if ($grade > 0) {
            return ['type' => 'point', 'grademax' => (float)$grade, 'scalename' => '', 'scaleitems' => []];
        }
        if ($grade < 0) {
            $scale = $DB->get_record('scale', ['id' => -$grade]);
            $items = $scale ? array_values(array_filter(array_map('trim', explode(',', $scale->scale)), 'strlen')) : [];
            return ['type' => 'scale', 'grademax' => (float)count($items), 'scalename' => $scale ? $scale->name : '',
                'scaleitems' => $items];
        }
        return ['type' => 'none', 'grademax' => 0.0, 'scalename' => '', 'scaleitems' => []];
    }

    /**
     * Whether any participant already has a grade in this assignment.
     *
     * @param int $assignid
     * @return bool
     */
    public static function graded(int $assignid): bool {
        global $DB;
        return $DB->record_exists_select('assign_grades', 'assignment = ? AND grade >= 0', [$assignid]);
    }
}
