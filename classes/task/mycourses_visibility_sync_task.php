<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mycourses_visibility_sync_task.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\task;

use coding_exception;
use core\task\scheduled_task;
use dml_exception;
use mod_childcourse\sync\mycourses_visibility_sync;

/**
 * Scheduled task that re-applies hidden child course preferences in My courses.
 */
class mycourses_visibility_sync_task extends scheduled_task {

    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name() {
        return "Synchronize hidden child courses in My courses";
    }

    /**
     * Executes the task.
     *
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    public function execute() {
        $sync = new mycourses_visibility_sync();
        $processed = $sync->sync();

        mtrace("mod_childcourse: My courses visibility sync completed. Processed {$processed} rows.");
    }
}
