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
 * restore_childcourse_activity_task.class.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore task for childcourse.
 */
class restore_childcourse_activity_task extends restore_activity_task {

    /**
     * Defines restore settings.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No specific settings.
    }

    /**
     * Defines restore steps.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_childcourse_activity_structure_step("childcourse_structure", "childcourse.xml"));
    }

    /**
     * Defines decode rules for links in intro/contents.
     *
     * @return restore_decode_rule[] Rules.
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule("CHILDCOURSEVIEW", "/mod/childcourse/view.php?id=$1", "course_module"),
        ];
    }

    /**
     * Defines restore log rules (none for MVP).
     *
     * @return restore_log_rule[] Rules.
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
