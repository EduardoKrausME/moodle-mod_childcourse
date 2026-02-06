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
 * completion_sync.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\sync;

use coding_exception;
use completion_info;
use dml_exception;
use moodle_exception;
use stdClass;

/**
 * Incremental completion sync from child course state to parent activity completion.
 */
class completion_sync {

    /**
     * Sync instance completion incrementally based on rule-specific source timestamps.
     *
     * @param stdClass $instance Instance record.
     * @param stdClass $cm Course module.
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function sync_instance_incremental(stdClass $instance, stdClass $cm) {
        global $DB;

        if ($instance->completionrule === "none") {
            return;
        }

        $childcourse = $DB->get_record("course", ["id" => $instance->childcourseid], "id,enablecompletion");
        if (!$childcourse || $childcourse->enablecompletion !== 1) {
            return;
        }

        $parentcourse = $DB->get_record("course", ["id" => $instance->course], "*", MUST_EXIST);

        $since = $instance->lastsynccompletion;
        $now = time();

        $userchanges = [];

        if ($instance->completionrule === "coursecompleted") {
            $userchanges =
                $this->get_changed_users_from_course_completion($instance->id, $instance->childcourseid, $since);
        } else if ($instance->completionrule === "allactivities") {
            $userchanges =
                $this->get_changed_users_from_module_completion($instance->id, $instance->childcourseid, $since, []);
        }

        if (!$userchanges) {
            $instance->lastsynccompletion = $now;
            $DB->update_record("childcourse", $instance);
            return;
        }

        $completion = new completion_info($parentcourse);
        $iscompletionenabled = $completion->is_enabled($cm);
        if (!$iscompletionenabled) {
            $instance->lastsynccompletion = $now;
            $DB->update_record("childcourse", $instance);
            return;
        }

        foreach ($userchanges as $userid => $maxtime) {
            $satisfied = $this->evaluate_rule_for_user($instance, $userid);

            if ($satisfied) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
                $this->upsert_state_completion($instance->id, $userid, 1, $maxtime);
            }
        }

        $instance->lastsynccompletion = $now;
        $DB->update_record("childcourse", $instance);
    }

    /**
     * Evaluates the completion rule for a user.
     *
     * @param stdClass $instance Instance record.
     * @param int $userid User id.
     * @return bool True if rule satisfied.
     * @throws dml_exception
     */
    protected function evaluate_rule_for_user(stdClass $instance, $userid) {
        if ($instance->completionrule === "coursecompleted") {
            return $this->is_course_completed($instance->childcourseid, $userid);
        }

        if ($instance->completionrule === "allactivities") {
            return $this->is_all_tracked_activities_completed($instance->childcourseid, $userid);
        }

        return false;
    }

    /**
     * Checks child course completion.
     *
     * @param int $childcourseid Child course id.
     * @param int $userid User id.
     * @return bool True if completed.
     * @throws dml_exception
     */
    protected function is_course_completed($childcourseid, $userid) {
        global $DB;

        $sql = "
            SELECT 1
              FROM {course_completions} cc
             WHERE cc.course = ?
               AND cc.userid = ?
               AND cc.timecompleted IS NOT NULL";
        return $DB->record_exists_sql($sql, [$childcourseid, $userid]);
    }

    /**
     * Checks if all activities with completion tracking are completed for the user.
     *
     * @param int $childcourseid Child course id.
     * @param int $userid User id.
     * @return bool True if satisfied.
     * @throws dml_exception
     */
    protected function is_all_tracked_activities_completed($childcourseid, $userid) {
        global $DB;

        $sql = "
            SELECT COUNT(1)
              FROM {course_modules} cm
             WHERE cm.course = ?
               AND cm.completion > 0";
        $total = $DB->count_records_sql($sql, [$childcourseid]);

        if ($total <= 0) {
            return false;
        }

        $sql = "
            SELECT COUNT(1)
              FROM {course_modules_completion} cmc
              JOIN {course_modules}             cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course = ?
               AND cm.completion > 0
               AND cmc.userid = ?
               AND (cmc.completionstate > 0 OR cmc.viewed = 1)";
        $completed = $DB->count_records_sql($sql, [$childcourseid, $userid]
        );

        return $completed >= $total;
    }

    /**
     * Checks if the user accessed a specific activity.
     * Primary source: course_modules_completion (viewed/completionstate).
     * Fallback source: logstore_standard_log.
     *
     * @param int $childcourseid Child course id.
     * @param int $userid User id.
     * @param int $cmid Course module id.
     * @return bool True if accessed.
     * @throws dml_exception
     */
    protected function has_accessed_activity($childcourseid, $userid, $cmid) {
        global $DB;

        $sql = "
            SELECT 1
              FROM {course_modules_completion} cmc
             WHERE cmc.coursemoduleid = ?
               AND cmc.userid = ?
               AND (cmc.completionstate > 0 OR cmc.viewed = 1)";
        $found = $DB->record_exists_sql($sql, [$cmid, $userid]);

        if ($found) {
            return true;
        }

        // Log fallback (works even if completion for that activity is not configured).
        $sql = "
            SELECT 1
              FROM {logstore_standard_log} sl
             WHERE sl.courseid = ?
               AND sl.contextinstanceid = ?
               AND sl.userid = ?
               AND sl.target = 'course_module'
               AND sl.action = 'viewed'";
        return $DB->record_exists_sql($sql, [$childcourseid, $cmid, $userid]);
    }

    /**
     * Checks if the user completed all activities from a set.
     *
     * @param int $userid User id.
     * @param int[] $cmids Course module ids.
     * @return bool True if completed all.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function has_completed_activity_set($userid, array $cmids) {
        global $DB;

        $cmids = array_values(array_unique(array_map("intval", $cmids)));
        if (!$cmids) {
            return false;
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids);
        array_unshift($params, $userid);

        $sql = "
            SELECT COUNT(DISTINCT cmc.coursemoduleid)
              FROM {course_modules_completion} cmc
             WHERE cmc.userid = ?
               AND cmc.coursemoduleid $insql
               AND (cmc.completionstate > 0 OR cmc.viewed = 1)";
        $completedcount = $DB->count_records_sql($sql, $params);

        return $completedcount >= count($cmids);
    }

    /**
     * Gets changed users from course completion table (only users enrolled via link instance).
     *
     * @param int $instanceid Instance id.
     * @param int $childcourseid Child course id.
     * @param int $since Timestamp.
     * @return array<int,int> userid => maxtime.
     * @throws dml_exception
     */
    protected function get_changed_users_from_course_completion($instanceid, $childcourseid, $since) {
        global $DB;

        $sql = "
            SELECT cc.userid, MAX(cc.timemodified) AS maxtime
              FROM {course_completions}  cc
              JOIN {childcourse_map}    map ON map.userid = cc.userid AND map.childcourseinstanceid = ?
             WHERE cc.course = ?
               AND cc.timemodified > ?
               AND cc.timecompleted IS NOT NULL
          GROUP BY cc.userid";
        $records = $DB->get_records_sql($sql, [$instanceid, $childcourseid, $since]);

        $out = [];
        foreach ($records as $r) {
            $out[$r->userid] = $r->maxtime;
        }
        return $out;
    }

    /**
     * Gets changed users from module completion table, filtered by optional cmids list.
     * Only users enrolled via link instance are returned.
     *
     * @param int $instanceid Instance id.
     * @param int $childcourseid Child course id.
     * @param int $since Timestamp.
     * @param int[] $cmids Optional list of cmids to filter.
     * @return array<int,int> userid => maxtime.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_changed_users_from_module_completion($instanceid, $childcourseid, $since, array $cmids) {
        global $DB;

        $params = [$instanceid, $childcourseid, $since];
        $filter = "";

        if ($cmids) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_values(array_unique(array_map("intval", $cmids))));
            $filter = " AND cmc.coursemoduleid $insql ";
            $params = array_merge([$instanceid, $childcourseid, $since], $inparams);
        }

        $sql = "
            SELECT cmc.userid, MAX(cmc.timemodified) AS maxtime
              FROM {course_modules_completion} cmc
              JOIN {course_modules}             cm ON cm.id = cmc.coursemoduleid
              JOIN {childcourse_map}           map ON map.userid = cmc.userid AND map.childcourseinstanceid = ?
             WHERE cm.course = ?
               AND cmc.timemodified > ?
               $filter
          GROUP BY cmc.userid";
        $records = $DB->get_records_sql($sql, $params);

        $out = [];
        foreach ($records as $r) {
            $out[$r->userid] = $r->maxtime;
        }
        return $out;
    }

    /**
     * Gets changed users from logs for "accessed activity" rule.
     * Only users enrolled via link instance are returned.
     *
     * @param int $instanceid Instance id.
     * @param int $childcourseid Child course id.
     * @param int $since Timestamp.
     * @param int $cmid Target course module id.
     * @return array<int,int> userid => maxtime.
     * @throws dml_exception
     */
    protected function get_changed_users_from_logs($instanceid, $childcourseid, $since, $cmid) {
        global $DB;

        $sql  ="
            SELECT sl.userid, MAX(sl.timecreated) AS maxtime
              FROM {logstore_standard_log}  sl
              JOIN {childcourse_map}       map ON map.userid = sl.userid AND map.childcourseinstanceid = ?
             WHERE sl.courseid = ?
               AND sl.contextinstanceid = ?
               AND sl.timecreated > ?
               AND sl.target = 'course_module'
               AND sl.action = 'viewed'
          GROUP BY sl.userid";
        $records = $DB->get_records_sql($sql, [$instanceid, $childcourseid, $cmid, $since]);

        $out = [];
        foreach ($records as $r) {
            $out[$r->userid] = $r->maxtime;
        }
        return $out;
    }

    /**
     * Merges two change maps, keeping the max timestamp.
     *
     * @param array<int,int> $a Map A.
     * @param array<int,int> $b Map B.
     * @return array<int,int> Merged.
     */
    protected function merge_user_changes(array $a, array $b) {
        foreach ($b as $userid => $time) {
            if (!isset($a[$userid]) || $time > $a[$userid]) {
                $a[$userid] = $time;
            }
        }
        return $a;
    }

    /**
     * Decodes a JSON set.
     *
     * @param string $json JSON.
     * @return int[] List.
     */
    protected function decode_set($json) {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map("intval", $decoded)));
    }

    /**
     * Upserts cached completion state.
     *
     * @param int $instanceid Instance id.
     * @param int $userid User id.
     * @param int $completed Completed flag.
     * @param int $timemodified Source timemodified.
     * @return void
     * @throws dml_exception
     */
    protected function upsert_state_completion($instanceid, $userid, $completed, $timemodified) {
        global $DB;

        $state = $DB->get_record("childcourse_state", [
            "childcourseinstanceid" => $instanceid,
            "userid" => $userid,
        ]);

        if (!$state) {
            $DB->insert_record(
                "childcourse_state", (object) [
                "childcourseinstanceid" => $instanceid,
                "userid" => $userid,
                "finalgrade" => null,
                "gradeitemtimemodified" => 0,
                "grade_source" => "course_total",
                "coursecompleted" => $completed,
                "coursecompletiontimemodified" => $timemodified,
                "timemodified" => time(),
            ]
            );
            return;
        }

        $state->coursecompleted = $completed;
        $state->coursecompletiontimemodified = $timemodified;
        $state->timemodified = time();

        $DB->update_record("childcourse_state", $state);
    }
}
