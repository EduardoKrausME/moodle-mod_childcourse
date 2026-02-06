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
 * restore_childcourse_stepslib.class.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore a childcourse activity.
 */
class restore_childcourse_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines the restore structure paths.
     *
     * @return restore_path_element[] Paths.
     */
    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element("childcourse", "/activity/childcourse_activity/childcourse");

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes childcourse data.
     *
     * @param array $data Raw data.
     * @return void
     * @throws dml_exception
     */
    protected function process_childcourse($data) {
        global $DB;

        $data = (object)$data;

        $oldid = (int)$data->id;

        $data->course = (int)$this->get_courseid();

        // Validate cross-course references.
        $data->childcourseid = $this->validate_child_course_id((int)($data->childcourseid ?? 0));
        $data->completioncmid = $this->validate_child_course_module_id($data->childcourseid, (int)($data->completioncmid ?? 0));

        // Reset sync timestamps/caches after restore.
        $data->lastsyncgrade = 0;
        $data->lastsynccompletion = 0;

        $data->timecreated = time();
        $data->timemodified = time();

        $newitemid = $DB->insert_record("childcourse", $data);

        $this->apply_activity_instance($newitemid);

        // Map old activity id -> new.
        $this->set_mapping("childcourse", $oldid, $newitemid);
    }

    /**
     * After execution, rebuild grade item for the restored instance.
     *
     * @return void
     * @throws dml_exception
     */
    protected function after_execute() {
        global $DB;

        $instanceid = (int)$this->get_new_parentid("childcourse");
        if ($instanceid <= 0) {
            return;
        }

        $instance = $DB->get_record("childcourse", ["id" => $instanceid]);
        if (!$instance) {
            return;
        }

        // Ensure the grade item exists in the restored course gradebook.
        childcourse_grade_item_update($instance);

        // Restore intro files.
        $this->add_related_files("childcourse", "intro", null);
    }

    /**
     * Validates child course id in the target site.
     * If not found, returns 0 to allow admin to choose it after restore.
     *
     * @param int $childcourseid Child course id.
     * @return int Valid id or 0.
     * @throws dml_exception
     */
    protected function validate_child_course_id($childcourseid) {
        global $DB;

        if ($childcourseid <= 0) {
            return 0;
        }

        if (!$DB->record_exists("course", ["id" => $childcourseid])) {
            return 0;
        }

        return $childcourseid;
    }

    /**
     * Validates the completion cmid reference (cross-course) for the target site.
     * If invalid, returns 0.
     *
     * @param int $childcourseid Child course id.
     * @param int $cmid Course module id.
     * @return int Valid cmid or 0.
     * @throws dml_exception
     */
    protected function validate_child_course_module_id($childcourseid, $cmid) {
        global $DB;

        if ($childcourseid <= 0 || $cmid <= 0) {
            return 0;
        }

        $cm = $DB->get_record("course_modules", ["id" => $cmid], "id,course");
        if (!$cm) {
            return 0;
        }

        if ((int)$cm->course !== (int)$childcourseid) {
            return 0;
        }

        return (int)$cm->id;
    }
}
