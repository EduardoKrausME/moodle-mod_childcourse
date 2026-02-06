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
 * backup_childcourse_stepslib.class.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to backup a childcourse activity.
 */
class backup_childcourse_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure.
     *
     * @return backup_nested_element Root element.
     * @throws base_element_struct_exception
     */
    protected function define_structure() {
        $childcourse = new backup_nested_element("childcourse", ["id"], [
            "name",
            "intro",
            "introformat",
            "childcourseid",
            "opennewtab",
            "autoenrol",
            "targetgroupid",
            "inheritgroups",
            "keeprole",
            "unenrolaction",
            "hideinmycourses",
            "completionrule",
            "completioncmid",
            "lastsyncgrade",
            "lastsynccompletion",
            "timecreated",
            "timemodified",
        ]);

        $root = new backup_nested_element("childcourse_activity");
        $root->add_child($childcourse);

        $childcourse->set_source_table("childcourse", ["id" => backup::VAR_ACTIVITYID]);

        // Notes:
        // - We intentionally do NOT backup childcourse_map/childcourse_state.
        //   Those are volatile caches/mappings that can be rebuilt after restore.
        // - childcourseid is a cross-course reference; restore will keep it only if it exists in target site.
        // - completioncmid also references a module in a different course; restore will validate it and drop if invalid.

        return $this->prepare_activity_structure($root);
    }
}
