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
 * custom_completion.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\completion;

use coding_exception;
use core_completion\activity_custom_completion;
use dml_exception;
use moodle_exception;

/**
 * Custom completion implementation for mod_childcourse.
 *
 * This module uses a single custom completion rule ("completionrule") whose value
 * is stored as a string in the activity instance record (childcourse.completionrule).
 *
 * The possible values are:
 * - "coursecompleted": complete when the child course is completed.
 * - "allactivities": complete when all tracked activities in the child course are completed.
 */
class custom_completion extends activity_custom_completion {

    /**
     * Returns the list of custom completion rules defined by this module.
     *
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return ["completionrule"];
    }

    /**
     * Returns an associative array of descriptions for custom completion rules.
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_custom_rule_descriptions(): array {
        $selected = $this->get_selected_completionrule();

        // If something is off, keep a safe, generic description.
        if ($selected === "allactivities") {
            return ["completionrule" => get_string("completionrule_allactivities", "childcourse")];
        }

        return ["completionrule" => get_string("completionrule_coursecompleted", "childcourse")];
    }

    /**
     * Defines the display order for the custom completion rules.
     *
     * @return string[]
     */
    public function get_sort_order(): array {
        return ["completionrule"];
    }

    /**
     * Returns the completion state for a given custom completion rule.
     *
     * @param string $rule The completion rule name (must be "completionrule").
     * @return int One of COMPLETION_INCOMPLETE or COMPLETION_COMPLETE (or other core constants).
     * @throws coding_exception
     * @throws moodle_exception
     * @throws dml_exception
     */
    public function get_state(string $rule): int {
        // Ensures the rule exists and is enabled for this instance.
        $this->validate_rule($rule);

        // Fast path: if core completion already says it's complete, keep it consistent.
        if (!empty($this->completionstate) && !empty($this->completionstate["completionstate"])) {
            return (int) $this->completionstate["completionstate"];
        }

        $childcourseid = $this->get_childcourseid();
        if ($childcourseid <= 0) {
            return COMPLETION_INCOMPLETE;
        }

        $selected = $this->get_selected_completionrule();

        if ($selected === "allactivities") {
            return $this->is_all_tracked_activities_completed($childcourseid, $this->userid)
                ? COMPLETION_COMPLETE
                : COMPLETION_INCOMPLETE;
        }

        // Default coursecompleted.
        return $this->is_course_completed($childcourseid, $this->userid)
            ? COMPLETION_COMPLETE
            : COMPLETION_INCOMPLETE;
    }

    /**
     * Gets the selected completionrule value for this instance.
     * Prefers cm_info->customdata (cheap), falls back to DB if needed.
     *
     * @return string
     * @throws dml_exception
     */
    protected function get_selected_completionrule(): string {
        if (!empty($this->cm->customdata)
            && !empty($this->cm->customdata["customcompletionrules"])
            && !empty($this->cm->customdata["customcompletionrules"]["completionrule"])) {
            return (string) $this->cm->customdata["customcompletionrules"]["completionrule"];
        }

        // Fallback: read from DB.
        global $DB;
        $rec = $DB->get_record("childcourse", ["id" => $this->cm->instance], "id,completionrule");
        if (!$rec || empty($rec->completionrule)) {
            return "coursecompleted";
        }

        return (string) $rec->completionrule;
    }

    /**
     * Gets the child course id for this instance (cheap via customdata, fallback via DB).
     *
     * @return int
     * @throws dml_exception
     */
    protected function get_childcourseid(): int {
        if (!empty($this->cm->customdata) && !empty($this->cm->customdata["childcourseid"])) {
            return (int) $this->cm->customdata["childcourseid"];
        }

        global $DB;
        $rec = $DB->get_record("childcourse", ["id" => $this->cm->instance], "id,childcourseid");
        return $rec ? (int) $rec->childcourseid : 0;
    }

    /**
     * Checks if the child course completion is completed for the given user.
     *
     * @param int $childcourseid Child course id.
     * @param int $userid User id.
     * @return bool
     * @throws dml_exception
     */
    protected function is_course_completed(int $childcourseid, int $userid): bool {
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
     * Checks if the user completed all tracked activities (cm.completion > 0) in the child course.
     *
     * @param int $childcourseid Child course id.
     * @param int $userid User id.
     * @return bool
     * @throws dml_exception
     */
    protected function is_all_tracked_activities_completed(int $childcourseid, int $userid): bool {
        global $DB;

        $sql = "
            SELECT COUNT(1)
              FROM {course_modules} cm
             WHERE cm.course     = ?
               AND cm.completion > 0";
        $total = $DB->count_records_sql($sql, [$childcourseid]);

        if ($total <= 0) {
            return false;
        }

        $sql = "
            SELECT COUNT(1)
              FROM {course_modules_completion} cmc
              JOIN {course_modules}             cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course     = ?
               AND cm.completion > 0
               AND cmc.userid    = ?
               AND (cmc.completionstate > 0 OR cmc.viewed = 1)";
        $completed = $DB->count_records_sql($sql, [$childcourseid, $userid]);

        return $completed >= $total;
    }
}
