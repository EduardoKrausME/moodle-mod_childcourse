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
 * grade_sync.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\sync;

use dml_exception;
use grade_item;
use stdClass;

/**
 * Incremental grade sync from child course (course total) to parent activity grade.
 */
class grade_sync {

    /**
     * Checks whether child course has course total grade item.
     *
     * @param int $childcourseid Child course id.
     * @return bool True if exists.
     */
    public static function child_course_has_course_total($childcourseid) {
        global $CFG;

        require_once("{$CFG->libdir}/gradelib.php");
        $item = grade_item::fetch_course_item($childcourseid);
        return !empty($item) && !empty($item->id);
    }

    /**
     * Sync instance incrementally based on grade_grades.timemodified.
     *
     * @param stdClass $instance Instance record.
     * @return void
     * @throws dml_exception
     */
    public function sync_instance_incremental(stdClass $instance) {
        global $DB, $CFG;

        require_once("{$CFG->libdir}/gradelib.php");

        $courseitem = grade_item::fetch_course_item($instance->childcourseid);
        if (!$courseitem || empty($courseitem->id)) {
            return;
        }

        $since = $instance->lastsyncgrade;
        $now = time();

        $sql = "
            SELECT gg.userid, gg.finalgrade, gg.timemodified
              FROM {grade_grades} gg
             WHERE gg.itemid       = ?
               AND gg.timemodified > ?
          ORDER BY gg.timemodified ASC";
        $changed = $DB->get_records_sql($sql, [$courseitem->id, $since]);

        if (!$changed) {
            $instance->lastsyncgrade = $now;
            $DB->update_record("childcourse", $instance);
            return;
        }

        foreach ($changed as $row) {
            $percent = $this->normalize_to_percent($row->finalgrade, $courseitem->grademax);

            childcourse_update_grade_for_user($instance, $row->userid, $percent);

            $this->upsert_state_grade($instance->id, $row->userid, $percent, $row->timemodified);
        }

        $instance->lastsyncgrade = $now;
        $DB->update_record("childcourse", $instance);
    }

    /**
     * Converts a grade to 0..100 based on grademax.
     *
     * @param float|null $finalgrade Final grade.
     * @param float|null $grademax Max grade.
     * @return float|null Percent or null.
     */
    protected function normalize_to_percent($finalgrade, $grademax) {
        if ($finalgrade === null) {
            return null;
        }

        $max = (float) $grademax;
        if ($max <= 0.0) {
            return null;
        }

        $percent = ((float) $finalgrade / $max) * 100.0;

        if ($percent < 0.0) {
            $percent = 0.0;
        }
        if ($percent > 100.0) {
            $percent = 100.0;
        }

        return $percent;
    }

    /**
     * Upserts cached grade state.
     *
     * @param int $instanceid Instance id.
     * @param int $userid User id.
     * @param float|null $percent Percent grade.
     * @param int $timemodified Grade timemodified.
     * @return void
     * @throws dml_exception
     */
    protected function upsert_state_grade($instanceid, $userid, $percent, $timemodified) {
        global $DB;

        $state = $DB->get_record("childcourse_state", [
            "childcourseinstanceid" => $instanceid,
            "userid" => $userid,
        ]);

        if (!$state) {
            $params = (object) [
                "childcourseinstanceid" => $instanceid,
                "userid" => $userid,
                "finalgrade" => $percent,
                "gradeitemtimemodified" => $timemodified,
                "grade_source" => "course_total",
                "coursecompleted" => 0,
                "coursecompletiontimemodified" => 0,
                "timemodified" => time(),
            ];
            $DB->insert_record("childcourse_state", $params);
            return;
        }

        $state->finalgrade = $percent;
        $state->gradeitemtimemodified = $timemodified;
        $state->grade_source = "course_total";
        $state->timemodified = time();

        $DB->update_record("childcourse_state", $state);
    }
}
