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
 * provider.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\privacy;

use coding_exception;
use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use dml_exception;
use stdClass;

/**
 * Privacy provider for childcourse.
 *
 * This provider covers:
 * - Mapping data for safe unenrol and audit (childcourse_map)
 * - Cached incremental sync state (childcourse_state)
 * - User preference used to hide the child course in My courses (block_myoverview_hidden_course_{courseid})
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata about stored user data.
     *
     * @param collection $collection Collection.
     * @return collection Updated collection.
     */
    public static function get_metadata(collection $collection) {
        $collection->add_database_table("childcourse_map", [
            "childcourseinstanceid" => "privacy:metadata:childcourse_map:childcourseinstanceid",
            "parentcourseid" => "privacy:metadata:childcourse_map:parentcourseid",
            "childcourseid" => "privacy:metadata:childcourse_map:childcourseid",
            "userid" => "privacy:metadata:childcourse_map:userid",
            "manualenrolid" => "privacy:metadata:childcourse_map:manualenrolid",
            "roleid" => "privacy:metadata:childcourse_map:roleid",
            "groupidsjson" => "privacy:metadata:childcourse_map:groupidsjson",
            "hiddenprefset" => "privacy:metadata:childcourse_map:hiddenprefset",
            "timeenrolled" => "privacy:metadata:childcourse_map:timeenrolled",
            "timemodified" => "privacy:metadata:childcourse_map:timemodified",
        ], "privacy:metadata:childcourse_map");

        $collection->add_database_table("childcourse_state", [
            "childcourseinstanceid" => "privacy:metadata:childcourse_state:childcourseinstanceid",
            "userid" => "privacy:metadata:childcourse_state:userid",
            "finalgrade" => "privacy:metadata:childcourse_state:finalgrade",
            "gradeitemtimemodified" => "privacy:metadata:childcourse_state:gradeitemtimemodified",
            "grade_source" => "privacy:metadata:childcourse_state:grade_source",
            "coursecompleted" => "privacy:metadata:childcourse_state:coursecompleted",
            "coursecompletiontimemodified" => "privacy:metadata:childcourse_state:coursecompletiontimemodified",
            "timemodified" => "privacy:metadata:childcourse_state:timemodified",
        ], "privacy:metadata:childcourse_state");

        // This preference name is dynamic, but it is always derived from the child course id.
        $collection->add_user_preference(
            "block_myoverview_hidden_course_{courseid}",
            "privacy:metadata:userpreference:block_myoverview_hidden_course"
        );

        return $collection;
    }

    /**
     * Returns contexts that contain user information.
     *
     * @param int $userid User id.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid($userid) {
        global $DB;

        $list = new contextlist();

        $sql = "
            SELECT ctx.id
              FROM {context} ctx
              JOIN {course_modules} cm ON cm.id = ctx.instanceid
              JOIN {modules} m ON m.id = cm.module AND m.name = 'childcourse'
              JOIN {childcourse} cc ON cc.id = cm.instance
              LEFT JOIN {childcourse_map} map ON map.childcourseinstanceid = cc.id AND map.userid = ?
              LEFT JOIN {childcourse_state} st ON st.childcourseinstanceid = cc.id AND st.userid = ?
             WHERE ctx.contextlevel = ?
               AND (map.id IS NOT NULL OR st.id IS NOT NULL)";

        $list->add_from_sql($sql, [(int)$userid, (int)$userid, CONTEXT_MODULE]);

        return $list;
    }

    /**
     * Exports user data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $instance = self::get_instance_from_context($context);
            if (!$instance) {
                continue;
            }

            $map = $DB->get_record("childcourse_map", [
                "childcourseinstanceid" => (int)$instance->id,
                "userid" => $userid,
            ], "*", IGNORE_MISSING);

            $state = $DB->get_record("childcourse_state", [
                "childcourseinstanceid" => (int)$instance->id,
                "userid" => $userid,
            ], "*", IGNORE_MISSING);

            if (!$map && !$state) {
                continue;
            }

            $childfullname = $DB->get_field("course", "fullname", ["id" => (int)$instance->childcourseid]);
            $groups = [];
            if ($map && !empty($map->groupidsjson)) {
                $groupids = json_decode((string)$map->groupidsjson, true);
                if (is_array($groupids) && $groupids) {
                    [$insql, $params] = $DB->get_in_or_equal(array_map("intval", $groupids), SQL_PARAMS_QM);
                    $grouprecords = $DB->get_records_sql("SELECT id, name FROM {groups} WHERE id $insql", $params);
                    foreach ($grouprecords as $g) {
                        $groups[] = [
                            "id" => (int)$g->id,
                            "name" => format_string($g->name, true),
                        ];
                    }
                }
            }

            $pref = null;
            if ($map && (int)$map->hiddenprefset === 1) {
                $prefname = "block_myoverview_hidden_course_" . (int)$instance->childcourseid;
                $pref = (string)get_user_preferences($prefname, "", $userid);
            }

            $data = [
                "parentcourseid" => (int)$instance->course,
                "childcourseid" => (int)$instance->childcourseid,
                "childcoursefullname" => format_string((string)$childfullname, true),
                "mapping" => $map ? [
                    "manualenrolid" => (int)$map->manualenrolid,
                    "roleid" => (int)$map->roleid,
                    "groups" => $groups,
                    "hiddenprefname" =>
                        $map->hiddenprefset ?
                        ("block_myoverview_hidden_course_" . (int)$instance->childcourseid) :
                        null,
                    "hiddenprefvalue" => $pref,
                    "timeenrolled" => (int)$map->timeenrolled,
                    "timemodified" => (int)$map->timemodified,
                ] : null,
                "state" => $state ? [
                    "finalgrade" => $state->finalgrade,
                    "gradeitemtimemodified" => (int)$state->gradeitemtimemodified,
                    "grade_source" => (string)$state->grade_source,
                    "coursecompleted" => (int)$state->coursecompleted,
                    "coursecompletiontimemodified" => (int)$state->coursecompletiontimemodified,
                    "timemodified" => (int)$state->timemodified,
                ] : null,
            ];

            $subcontext = [get_string("pluginname", "childcourse"), format_string($instance->name, true)];
            writer::with_context($context)->export_data($subcontext, (object)$data);
        }
    }

    /**
     * Deletes all user data for a given context.
     *
     * @param context $context Context.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        $instance = self::get_instance_from_context($context);
        if (!$instance) {
            return;
        }

        // Remove any "My courses hidden" preferences set by this link.
        $maps = $DB->get_records("childcourse_map", [
            "childcourseinstanceid" => (int)$instance->id,
            "hiddenprefset" => 1,
        ], "id ASC", "id,userid");

        foreach ($maps as $map) {
            $prefname = "block_myoverview_hidden_course_" . (int)$instance->childcourseid;
            unset_user_preference($prefname, (int)$map->userid);
        }

        $DB->delete_records("childcourse_state", ["childcourseinstanceid" => (int)$instance->id]);
        $DB->delete_records("childcourse_map", ["childcourseinstanceid" => (int)$instance->id]);
    }

    /**
     * Deletes user data for an approved context list.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $instance = self::get_instance_from_context($context);
            if (!$instance) {
                continue;
            }

            $map = $DB->get_record("childcourse_map", [
                "childcourseinstanceid" => (int)$instance->id,
                "userid" => $userid,
            ], "id,hiddenprefset", IGNORE_MISSING);

            if ($map && (int)$map->hiddenprefset === 1) {
                $prefname = "block_myoverview_hidden_course_" . (int)$instance->childcourseid;
                unset_user_preference($prefname, $userid);
            }

            $DB->delete_records("childcourse_state", [
                "childcourseinstanceid" => (int)$instance->id,
                "userid" => $userid,
            ]);

            $DB->delete_records("childcourse_map", [
                "childcourseinstanceid" => (int)$instance->id,
                "userid" => $userid,
            ]);
        }
    }

    /**
     * Resolves the childcourse instance from a module context.
     *
     * @param context $context Context.
     * @return stdClass|null Instance record or null.
     * @throws dml_exception
     */
    protected static function get_instance_from_context(context $context) {
        global $DB;

        if ((int)$context->contextlevel !== CONTEXT_MODULE) {
            return null;
        }

        $cmid = (int)$context->instanceid;

        $record = $DB->get_record_sql("
            SELECT cc.*
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'childcourse'
              JOIN {childcourse} cc ON cc.id = cm.instance
             WHERE cm.id = ?
        ", [$cmid]);

        return $record ?: null;
    }
}
