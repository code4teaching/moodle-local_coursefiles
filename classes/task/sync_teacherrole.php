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

namespace local_coursefiles\task;

/**
 * Daily check: everybody who teaches in a course has the site role (catches assignments made without events).
 *
 * @package    local_coursefiles
 * @copyright  2026 Holger Sebastião
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_teacherrole extends \core\task\scheduled_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_syncteacherrole', 'local_coursefiles');
    }

    /**
     * Runs the task.
     */
    public function execute(): void {
        if (!\local_coursefiles\local\teacherrole::roles()) {
            mtrace('Site role for teachers is not set up – nothing to do.');
            return;
        }
        $count = \local_coursefiles\local\teacherrole::sync();
        mtrace("Site role given to {$count} teacher(s).");
    }
}
