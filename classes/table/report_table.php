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
 * report_table.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\table;

use coding_exception;
use dml_exception;
use grade_item;
use html_writer;
use moodle_url;
use stdClass;
use table_sql;

/**
 * Grade and completion consistency report.
 */
class report_table extends table_sql {
    /** @var stdClass */
    protected $instance;

    /** @var stdClass */
    protected $cm;

    /** @var stdClass */
    protected $parentcourse;

    /** @var stdClass */
    protected $childcourse;

    /** @var grade_item|null */
    protected $activitygradeitem = null;

    /** @var grade_item|null */
    protected $childcoursegradeitem = null;

    /** @var array<int,float|null> */
    protected $activitygradecache = [];

    /** @var array<int,float|null> */
    protected $remotegradecache = [];

    /** @var array<int,bool> */
    protected $activitycompletioncache = [];

    /** @var array<int,array> */
    protected $remotecompletioncache = [];

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique table id.
     * @param moodle_url $baseurl Base URL for paging and sorting.
     * @param stdClass $instance Activity instance.
     * @param stdClass $cm Course module.
     * @param stdClass $parentcourse Parent course.
     * @param stdClass $childcourse Child course.
     * @throws coding_exception
     */
    public function __construct(
        string $uniqueid,
        moodle_url $baseurl,
        stdClass $instance,
        stdClass $cm,
        stdClass $parentcourse,
        stdClass $childcourse
    ) {
        global $CFG;

        require_once("{$CFG->libdir}/gradelib.php");

        parent::__construct($uniqueid);

        $this->instance = $instance;
        $this->cm = $cm;
        $this->parentcourse = $parentcourse;
        $this->childcourse = $childcourse;

        $this->activitygradeitem = grade_item::fetch([
            "courseid" => $instance->course,
            "itemtype" => "mod",
            "itemmodule" => "childcourse",
            "iteminstance" => $instance->id,
        ]);
        $this->childcoursegradeitem = grade_item::fetch_course_item($instance->childcourseid);

        $this->define_baseurl($baseurl);

        $this->define_columns([
            "userfullname",
            "activitygrade",
            "remotegrade",
            "gradestatus",
            "activitycompletion",
            "remotecompletion",
            "completionstatus",
        ]);
        $this->define_headers([
            get_string("user"),
            get_string("report_activitygrade", "childcourse"),
            get_string("report_remotegrade", "childcourse"),
            get_string("report_gradestatus", "childcourse"),
            get_string("report_activitycompletion", "childcourse"),
            get_string("report_remotecompletion", "childcourse"),
            get_string("report_completionstatus", "childcourse"),
        ]);

        $this->no_sorting("activitygrade");
        $this->no_sorting("remotegrade");
        $this->no_sorting("gradestatus");
        $this->no_sorting("activitycompletion");
        $this->no_sorting("remotecompletion");
        $this->no_sorting("completionstatus");
        $this->set_attribute("class", "generaltable table-striped table-sm");
    }

    /**
     * Renders user column.
     *
     * @param stdClass $row Table row.
     * @return string
     */
    public function col_userfullname($row): string {
        $name = fullname($row);
        $parts = [];
        $parts[] = html_writer::div($name, "fw-semibold");

        if (!empty($row->email)) {
            $parts[] = html_writer::div(s($row->email), "small text-muted");
        }

        return implode("", $parts);
    }

    /**
     * Renders the activity grade synced to the parent module.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws dml_exception
     */
    public function col_activitygrade($row): string {
        $grade = $this->get_activity_grade((int) $row->userid);
        return $this->format_activity_grade($grade);
    }

    /**
     * Renders the remote child course grade.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws dml_exception
     */
    public function col_remotegrade($row): string {
        $grade = $this->get_remote_grade((int) $row->userid);
        return $this->format_remote_grade($grade);
    }

    /**
     * Renders grade consistency status.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws dml_exception
     */
    public function col_gradestatus($row): string {
        $activitygrade = $this->get_activity_grade((int) $row->userid);
        $remotegrade = $this->get_remote_grade((int) $row->userid);
        $remotepercent = $this->normalize_remote_grade_to_percent($remotegrade);

        $matches = $this->grades_match($activitygrade, $remotepercent);

        return $this->render_status_badge(
            $matches,
            $matches ? get_string("report_status_ok", "childcourse") : get_string("report_status_discrepant", "childcourse")
        );
    }

    /**
     * Renders the synced parent activity completion status.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws dml_exception
     */
    public function col_activitycompletion($row): string {
        $completed = $this->get_activity_completion((int) $row->userid);

        return $this->render_status_badge(
            $completed,
            $completed ? get_string("report_completed", "childcourse") : get_string("report_incomplete", "childcourse")
        );
    }

    /**
     * Renders the remote child course completion status.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws dml_exception
     */
    public function col_remotecompletion($row): string {
        $data = $this->get_remote_completion((int) $row->userid);
        $badge = $this->render_status_badge(
            !empty($data["completed"]),
            !empty($data["completed"])
                ? get_string("report_completed", "childcourse")
                : get_string("report_incomplete", "childcourse")
        );

        $details = [];
        $details[] = html_writer::div($badge, "mb-1");

        if (!empty($data["details"])) {
            $details[] = html_writer::div(s($data["details"]), "small text-muted");
        }

        return implode("", $details);
    }

    /**
     * Renders completion consistency status.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws dml_exception
     */
    public function col_completionstatus($row): string {
        $activitycompleted = $this->get_activity_completion((int) $row->userid);
        $remote = $this->get_remote_completion((int) $row->userid);
        $matches = $activitycompleted === !empty($remote["completed"]);

        return $this->render_status_badge(
            $matches,
            $matches ? get_string("report_status_ok", "childcourse") : get_string("report_status_discrepant", "childcourse")
        );
    }

    /**
     * Loads parent activity grade.
     *
     * @param int $userid User id.
     * @return float|null
     * @throws dml_exception
     */
    protected function get_activity_grade(int $userid): ?float {
        global $DB;

        if (array_key_exists($userid, $this->activitygradecache)) {
            return $this->activitygradecache[$userid];
        }

        if (!$this->activitygradeitem || empty($this->activitygradeitem->id)) {
            $this->activitygradecache[$userid] = null;
            return null;
        }

        $grade = $DB->get_field(
            "grade_grades",
            "finalgrade",
            [
                "itemid" => $this->activitygradeitem->id,
                "userid" => $userid,
            ]
        );

        $this->activitygradecache[$userid] = ($grade === false || $grade === null) ? null : (float) $grade;
        return $this->activitygradecache[$userid];
    }

    /**
     * Loads child course total grade.
     *
     * @param int $userid User id.
     * @return float|null
     * @throws dml_exception
     */
    protected function get_remote_grade(int $userid): ?float {
        global $DB;

        if (array_key_exists($userid, $this->remotegradecache)) {
            return $this->remotegradecache[$userid];
        }

        if (!$this->childcoursegradeitem || empty($this->childcoursegradeitem->id)) {
            $this->remotegradecache[$userid] = null;
            return null;
        }

        $grade = $DB->get_field(
            "grade_grades",
            "finalgrade",
            [
                "itemid" => $this->childcoursegradeitem->id,
                "userid" => $userid,
            ]
        );

        $this->remotegradecache[$userid] = ($grade === false || $grade === null) ? null : (float) $grade;
        return $this->remotegradecache[$userid];
    }

    /**
     * Loads parent activity completion.
     *
     * @param int $userid User id.
     * @return bool
     * @throws dml_exception
     */
    protected function get_activity_completion(int $userid): bool {
        global $DB;

        if (array_key_exists($userid, $this->activitycompletioncache)) {
            return $this->activitycompletioncache[$userid];
        }

        $sql = "
            SELECT 1
              FROM {course_modules_completion} cmc
             WHERE cmc.coursemoduleid = ?
               AND cmc.userid = ?
               AND cmc.completionstate > 0";
        $completed = $DB->record_exists_sql($sql, [$this->cm->id, $userid]);

        $this->activitycompletioncache[$userid] = $completed;
        return $completed;
    }

    /**
     * Loads remote completion according to the configured rule.
     *
     * @param int $userid User id.
     * @return array
     * @throws dml_exception
     */
    protected function get_remote_completion(int $userid): array {
        if (array_key_exists($userid, $this->remotecompletioncache)) {
            return $this->remotecompletioncache[$userid];
        }

        $data = [
            "completed" => false,
            "details" => "",
        ];

        if ($this->instance->completionrule == "coursecompleted") {
            $data["completed"] = $this->is_child_course_completed($userid);
            $data["details"] = get_string("completionrule_coursecompleted", "childcourse");
        } else if ($this->instance->completionrule == "allactivities") {
            [$completedcount, $totalcount] = $this->get_child_activity_completion_counts($userid);
            $data["completed"] = ($totalcount > 0 && $completedcount >= $totalcount);
            $data["details"] = get_string(
                "report_completionratio",
                "childcourse",
                (object) [
                    "completed" => $completedcount,
                    "total" => $totalcount,
                ]
            );
        } else {
            $data["details"] = get_string("completionrule_none", "childcourse");
        }

        $this->remotecompletioncache[$userid] = $data;
        return $data;
    }

    /**
     * Checks if the child course is completed for the user.
     *
     * @param int $userid User id.
     * @return bool
     * @throws dml_exception
     */
    protected function is_child_course_completed(int $userid): bool {
        global $DB;

        $sql = "
            SELECT 1
              FROM {course_completions} cc
             WHERE cc.course = ?
               AND cc.userid = ?
               AND cc.timecompleted IS NOT NULL";

        return $DB->record_exists_sql($sql, [$this->childcourse->id, $userid]);
    }

    /**
     * Returns completed and total tracked child activities.
     *
     * @param int $userid User id.
     * @return array{0:int,1:int}
     * @throws dml_exception
     */
    protected function get_child_activity_completion_counts(int $userid): array {
        global $DB;

        $totalsql = "
            SELECT COUNT(1)
              FROM {course_modules} cm
             WHERE cm.course = ?
               AND cm.deletioninprogress = 0
               AND cm.completion > 0";
        $totalcount = (int) $DB->count_records_sql($totalsql, [$this->childcourse->id]);

        if ($totalcount <= 0) {
            return [0, 0];
        }

        $completedsql = "
            SELECT COUNT(DISTINCT cmc.coursemoduleid)
              FROM {course_modules_completion} cmc
              JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course = ?
               AND cm.deletioninprogress = 0
               AND cm.completion > 0
               AND cmc.userid = ?
               AND (cmc.completionstate > 0 OR cmc.viewed = 1)";
        $completedcount = (int) $DB->count_records_sql($completedsql, [$this->childcourse->id, $userid]);

        return [$completedcount, $totalcount];
    }

    /**
     * Formats the parent activity grade.
     *
     * @param float|null $grade Grade percentage.
     * @return string
     */
    protected function format_activity_grade(?float $grade): string {
        if ($grade === null) {
            return html_writer::span(get_string("report_nograde", "childcourse"), "text-muted");
        }

        return format_float($grade, 2) . "%";
    }

    /**
     * Formats the remote child course grade with raw and normalized values.
     *
     * @param float|null $grade Raw child course total.
     * @return string
     */
    protected function format_remote_grade(?float $grade): string {
        if ($grade === null) {
            return html_writer::span(get_string("report_nograde", "childcourse"), "text-muted");
        }

        $parts = [];
        $parts[] = html_writer::div(format_float($grade, 2), "fw-semibold");

        if ($this->childcoursegradeitem && (float) $this->childcoursegradeitem->grademax > 0) {
            $percent = $this->normalize_remote_grade_to_percent($grade);
            $details = format_float($grade, 2) . " / " . format_float((float) $this->childcoursegradeitem->grademax, 2);
            $details .= " (" . format_float($percent, 2) . "%)";
            $parts[] = html_writer::div($details, "small text-muted");
        }

        return implode("", $parts);
    }

    /**
     * Normalizes remote grade to the parent activity 0..100 scale.
     *
     * @param float|null $grade Raw child course total.
     * @return float|null
     */
    protected function normalize_remote_grade_to_percent(?float $grade): ?float {
        if ($grade === null || !$this->childcoursegradeitem) {
            return null;
        }

        $max = (float) $this->childcoursegradeitem->grademax;
        if ($max <= 0.0) {
            return null;
        }

        $percent = ($grade / $max) * 100.0;
        if ($percent < 0.0) {
            return 0.0;
        }
        if ($percent > 100.0) {
            return 100.0;
        }

        return $percent;
    }

    /**
     * Compares grades with tolerance.
     *
     * @param float|null $activitygrade Synced activity grade.
     * @param float|null $remotepercent Normalized remote percent.
     * @return bool
     */
    protected function grades_match(?float $activitygrade, ?float $remotepercent): bool {
        if ($activitygrade === null && $remotepercent === null) {
            return true;
        }

        if ($activitygrade === null || $remotepercent === null) {
            return false;
        }

        return abs($activitygrade - $remotepercent) < 0.01;
    }

    /**
     * Renders a small status badge.
     *
     * @param bool $ok Whether the status is positive.
     * @param string $label Badge label.
     * @return string
     */
    protected function render_status_badge(bool $ok, string $label): string {
        $class = $ok ? "badge bg-success" : "badge bg-danger";
        return html_writer::span(s($label), $class);
    }
}
