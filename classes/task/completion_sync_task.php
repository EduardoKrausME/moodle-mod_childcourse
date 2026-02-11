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
 * completion_sync_task.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\task;

use coding_exception;
use core\task\scheduled_task;
use dml_exception;
use mod_childcourse\sync\completion_sync;
use mod_childcourse\sync\grade_sync;
use moodle_exception;

/**
 * Scheduled task to incrementally sync grades and completion for all instances.
 */
class completion_sync_task extends scheduled_task {

    /**
     * Returns task name.
     *
     * @return string Name.
     */
    public function get_name() {
        return "childcourse completion sync";
    }

    /**
     * Executes the task.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute() {
        global $DB;

        $instances = $DB->get_records("childcourse", ["grade_approval" => 1]);
        if (!$instances) {
            return;
        }

        $compsync = new completion_sync();
        foreach ($instances as $instance) {
            $cm = get_coursemodule_from_instance("childcourse", $instance->id, $instance->course);
            if (!$cm) {
                continue;
            }

            $compsync->sync_instance_incremental($instance, $cm);
        }
    }
}
