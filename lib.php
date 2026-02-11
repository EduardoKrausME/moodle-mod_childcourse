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
 * lib.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_childcourse\enrol\enrol_manager;
use mod_childcourse\instance\record_mapper;
use mod_childcourse\sync\outcome_sync;

/**
 * Defines plugin support features.
 *
 * @param string $feature Feature constant.
 * @return int|string|true|null True if supported, null if unknown.
 */
function childcourse_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        default:
            return null;
    }
}

/**
 * Adds a new instance of the module.
 *
 * @param stdClass $data Form data.
 * @param mod_childcourse_mod_form $mform The form object.
 * @return int The instance id.
 * @throws dml_exception
 */
function childcourse_add_instance($data, $mform) {
    global $DB;

    $data = record_mapper::prepare_record($data);

    $data->timecreated = time();
    $data->timemodified = time();

    $id = $DB->insert_record("childcourse", $data);

    $instance = $DB->get_record("childcourse", ["id" => $id], "*", MUST_EXIST);
    childcourse_grade_item_update($instance);

    return $id;
}

/**
 * Updates an existing instance.
 *
 * @param stdClass $data Form data.
 * @param mod_childcourse_mod_form $mform The form object.
 * @return bool True on success.
 * @throws dml_exception
 * @throws moodle_exception
 */
function childcourse_update_instance($data, $mform) {
    global $DB;

    $data = record_mapper::prepare_record($data);

    $data->timemodified = time();
    $data->id = $data->instance;

    $current = $DB->get_record("childcourse", ["id" => $data->id], "*", MUST_EXIST);

    // Child course is immutable after first save, except when restored with childcourseid=0.
    if ((int)$current->childcourseid > 0 && (int)$current->childcourseid !== (int)$data->childcourseid) {
        throw new moodle_exception("lockedcoursewarning", "childcourse");
    }

    $DB->update_record("childcourse", $data);

    $instance = $DB->get_record("childcourse", ["id" => $data->id], "*", MUST_EXIST);
    childcourse_grade_item_update($instance);

    return true;
}

/**
 * Deletes an instance and handles enrolments depending on config.
 *
 * @param int $id Instance id.
 * @return bool True on success.
 * @throws coding_exception
 * @throws dml_exception
 */
function childcourse_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record("childcourse", ["id" => $id], "*", MUST_EXIST);

    $manager = new enrol_manager();
    $manager->handle_instance_deleted($instance);

    $DB->delete_records("childcourse_state", ["childcourseinstanceid" => $instance->id]);
    $DB->delete_records("childcourse_map", ["childcourseinstanceid" => $instance->id]);
    $DB->delete_records("childcourse", ["id" => $instance->id]);

    childcourse_grade_item_delete($instance);

    return true;
}

/**
 * Creates or updates the grade item for this module instance.
 *
 * @param stdClass $instance Instance record.
 * @param array|null $grades Optional grades.
 * @return int 0 on success.
 */
function childcourse_grade_item_update($instance, $grades = null) {
    require_once(__DIR__ . "/../../lib/gradelib.php");

    $item = [
        "itemname" => $instance->name,
        "gradetype" => GRADE_TYPE_VALUE,
        "grademax" => 100,
        "grademin" => 0,
    ];

    return grade_update(
        "mod/childcourse",
        $instance->course,
        "mod",
        "childcourse",
        $instance->id,
        0,
        $grades,
        $item
    );
}

/**
 * Deletes the grade item for this module instance.
 *
 * @param stdClass $instance Instance record.
 * @return int 0 on success.
 */
function childcourse_grade_item_delete($instance) {
    require_once(__DIR__ . "/../../lib/gradelib.php");

    return grade_update(
        "mod/childcourse",
        $instance->course,
        "mod",
        "childcourse",
        $instance->id,
        0,
        null,
        ["deleted" => 1]
    );
}

/**
 * Updates grades for a specific user.
 *
 * @param stdClass $instance Instance record.
 * @param int $userid User id.
 * @param float|null $grade Grade (0..100) or null.
 * @return void
 * @throws dml_exception
 */
function childcourse_update_grade_for_user($instance, $userid, $grade) {
    $grades = [
        $userid => [
            "rawgrade" => $grade,
        ],
    ];

    childcourse_grade_item_update($instance, $grades);

    // Outcome grades are optional and will only be pushed when outcomes are enabled and mappable.
    $outcomesync = new outcome_sync();
    $outcomesync->sync_user_outcomes($instance, (int)$userid, $grade);
}

/**
 * Gradebook callback that updates grades for one user or all users.
 *
 * @param stdClass $instance Instance record.
 * @param int $userid User id or 0 for all.
 * @param bool $nullifnone Insert NULL grade if no grade exists.
 * @return void
 * @throws dml_exception
 */
function childcourse_update_grades($instance, $userid = 0, $nullifnone = true) {
    // This module is sync-driven; this callback ensures the grade item exists.
    childcourse_grade_item_update($instance);

    if ($userid && $nullifnone) {
        // If requested, insert a NULL grade to avoid "missing grade" in gradebook.
        childcourse_update_grade_for_user($instance, (int)$userid, null);
    }
}

/**
 * Provides extra CM info for course listings and (critical here) for custom completion rules.
 *
 * IMPORTANT:
 * - For custom completion rules, Moodle expects custom rules to be stored in:
 *   $info->customdata["customcompletionrules"]
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|null
 * @throws dml_exception
 */
function childcourse_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        "childcourse",
        ["id" => $coursemodule->instance],
        "id,childcourseid,completionrule"
    );

    if (!$instance) {
        return null;
    }

    $info = new cached_cm_info();

    // Keep childcourseid available to the custom completion class without extra queries.
    $info->customdata = [
        "childcourseid" => (int) $instance->childcourseid,
    ];

    // Only expose the custom completion rule when completion is automatic and rule is enabled.
    $completion = $coursemodule->completion ?? 0;
    if ((int) $completion === COMPLETION_TRACKING_AUTOMATIC && !empty($instance->completionrule) &&
        $instance->completionrule !== "none") {
        $info->customdata["customcompletionrules"] = [
            // The *rule name* is "completionrule" (must match custom_completion::get_defined_custom_rules()).
            // The value is the selected mode ("coursecompleted" | "allactivities").
            "completionrule" => (string) $instance->completionrule,
        ];
    }

    return $info;
}

/**
 * Returns the active completion rule descriptions shown in the activity completion UI.
 *
 * @param cm_info $cm The course module info object.
 * @return string[]
 * @throws coding_exception
 */
function childcourse_get_completion_active_rule_descriptions($cm): array {
    if (empty($cm->customdata) || empty($cm->customdata["customcompletionrules"]["completionrule"])) {
        return [];
    }

    $selected = (string) $cm->customdata["customcompletionrules"]["completionrule"];

    if ($selected === "allactivities") {
        return [get_string("completionrule_allactivities", "childcourse")];
    }

    return [get_string("completionrule_coursecompleted", "childcourse")];
}
