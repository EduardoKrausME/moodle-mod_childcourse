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
 * enrol_manager.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\enrol;

use coding_exception;
use context_course;
use core\exception\moodle_exception as moodle_exceptionAlias;
use dml_exception;
use mod_childcourse\event\course_module_viewed;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Handles enrolments created by childcourse.
 */
class enrol_manager {

    /**
     * Function redirect_enrolled
     *
     * @param stdClass $instance
     * @param stdClass $cm
     * @param stdClass $parentcourse
     * @return void
     * @throws moodle_exceptionAlias
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function redirect_enrolled(stdClass $instance, stdClass $cm, stdClass $parentcourse) {
        global $PAGE;

        if ($instance->autoenrol === 1) {
            $this->ensure_user_enrolled($instance, $cm, $parentcourse);
        }

        $event = course_module_viewed::create([
            "objectid" => $PAGE->cm->instance,
            "context" => $PAGE->context,
        ]);
        $event->add_record_snapshot("course", $PAGE->course);
        $event->add_record_snapshot($PAGE->cm->modname, $instance);
        $event->trigger();

        $url = new moodle_url("/course/view.php", ["id" => $instance->childcourseid]);
        redirect($url);
    }

    /**
     * Ensures a user is enrolled in the child course for a given instance.
     *
     * @param stdClass $instance Instance record.
     * @param stdClass $cm Course module.
     * @param stdClass $parentcourse Parent course.
     * @return void
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function ensure_user_enrolled(stdClass $instance, stdClass $cm, stdClass $parentcourse) {
        global $DB, $USER;

        $map = $DB->get_record("childcourse_map", [
            "childcourseinstanceid" => $instance->id,
            "userid" => $USER->id,
        ]);

        if ($map) {
            return;
        }

        $childcourse = $DB->get_record("course", ["id" => $instance->childcourseid], "*", MUST_EXIST);

        $manual = enrol_get_plugin("manual");
        if (!$manual) {
            throw new moodle_exception("error_manualenrolnotavailable");
        }

        $enrolinstance = $this->get_or_create_manual_instance($childcourse, $instance);

        $roleid = $this->resolve_roleid($parentcourse, $cm, $USER->id, $instance->keeprole);

        $manual->enrol_user($enrolinstance, $USER->id, $roleid, time(), 0, ENROL_USER_ACTIVE);

        $groupids = [];

        if ($instance->targetgroupid > 0) {
            $this->add_user_to_group($instance->targetgroupid, $USER->id);
            $groupids[] = $instance->targetgroupid;
        }

        if ($instance->inheritgroups == 1) {
            $inherited = $this->inherit_groups_by_name($parentcourse->id, $childcourse->id, $USER->id);
            $groupids = array_values(array_unique(array_merge($groupids, $inherited)));
        }

        $hiddenprefset = 0;
        if ($instance->hideinmycourses === 1) {
            $prefname = "block_myoverview_hidden_course_" . $childcourse->id;
            set_user_preference($prefname, 1, $USER->id);
            $hiddenprefset = 1;
        }

        $record = (object) [
            "childcourseinstanceid" => $instance->id,
            "parentcourseid" => $parentcourse->id,
            "childcourseid" => $childcourse->id,
            "userid" => $USER->id,
            "manualenrolid" => $enrolinstance->id,
            "roleid" => $roleid,
            "groupidsjson" => json_encode($groupids),
            "hiddenprefset" => $hiddenprefset,
            "timeenrolled" => time(),
            "timemodified" => time(),
        ];

        $DB->insert_record("childcourse_map", $record);

        $this->ensure_state_row($instance->id, $USER->id);
    }

    /**
     * Handles instance deletion by unenrolling users depending on unenrolaction.
     *
     * @param stdClass $instance Instance record.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function handle_instance_deleted(stdClass $instance) {
        global $DB;

        if ($instance->unenrolaction !== "unenrol") {
            return;
        }

        $maps = $DB->get_records("childcourse_map", ["childcourseinstanceid" => $instance->id]);
        if (!$maps) {
            return;
        }

        $manual = enrol_get_plugin("manual");
        if (!$manual) {
            return;
        }

        foreach ($maps as $map) {
            $enrolinstance = $DB->get_record("enrol", ["id" => $map->manualenrolid]);
            if (!$enrolinstance) {
                continue;
            }

            // Only unenrol if the user_enrolments exists for this enrol instance.
            $ue = $DB->get_record("user_enrolments", [
                "enrolid" => $enrolinstance->id,
                "userid" => $map->userid,
            ]);

            if ($ue) {
                $manual->unenrol_user($enrolinstance, $map->userid);
            }
        }
    }

    /**
     * Creates a dedicated manual enrol instance for a link, or returns an existing one.
     *
     * @param stdClass $childcourse Child course record.
     * @param stdClass $instance Module instance.
     * @return stdClass Enrol instance.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_or_create_manual_instance(stdClass $childcourse, stdClass $instance) {
        global $DB;

        $sql = "
            SELECT e.*
              FROM {enrol} e
             WHERE e.enrol    = 'manual'
               AND e.courseid = ?";
        $existing = $DB->get_record_sql($sql, [$childcourse->id]);

        if ($existing) {
            return $existing;
        }

        $manual = enrol_get_plugin("manual");

        $fields = [
            "name" => get_string("enrolinstancename", "childcourse", $instance->id),
            "status" => ENROL_INSTANCE_ENABLED,
            "roleid" => 0,
            "enrolperiod" => 0,
            "customint1" => 0,
            "customint2" => 0,
            "customint3" => 0,
            "customint4" => 0,
            "customint5" => 0,
            "customint6" => 0,
            "customint7" => 0,
            "customint8" => 0,
        ];

        $id = $manual->add_instance($childcourse, $fields);
        return $DB->get_record("enrol", ["id" => $id], "*", MUST_EXIST);
    }

    /**
     * Resolves role id according to config and user capability in parent course.
     *
     * @param stdClass $parentcourse Parent course.
     * @param stdClass $cm Course module.
     * @param int $userid User id.
     * @param int $keeprole Keep role flag.
     * @return int Role id.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function resolve_roleid(stdClass $parentcourse, stdClass $cm, $userid, $keeprole) {
        global $DB;

        $student = $DB->get_record("role", ["shortname" => "student"], "id", MUST_EXIST);
        $teacher = $DB->get_record("role", ["shortname" => "editingteacher"], "id");
        if (!$teacher) {
            $teacher = $DB->get_record("role", ["shortname" => "teacher"], "id");
        }

        if ($keeprole !== 1 || !$teacher) {
            return $student->id;
        }

        $coursecontext = context_course::instance($parentcourse->id);
        if (has_capability("moodle/course:update", $coursecontext, $userid)) {
            return $teacher->id;
        }

        return $student->id;
    }

    /**
     * Adds user to group.
     *
     * @param int $groupid Group id.
     * @param int $userid User id.
     * @return void
     */
    protected function add_user_to_group($groupid, $userid) {
        global $CFG;
        require_once("{$CFG->dirroot}/group/lib.php");
        groups_add_member($groupid, $userid);
    }

    /**
     * Inherits groups by group name from parent course to child course.
     *
     * @param int $parentcourseid Parent course id.
     * @param int $childcourseid Child course id.
     * @param int $userid User id.
     * @return int[] Child course group ids.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function inherit_groups_by_name($parentcourseid, $childcourseid, $userid) {
        global $DB, $CFG;

        require_once("{$CFG->dirroot}/group/lib.php");

        $parentgroups = groups_get_user_groups($parentcourseid, $userid);
        $parentgroupids = [];
        foreach ($parentgroups as $ids) {
            $parentgroupids = array_merge($parentgroupids, $ids);
        }
        $parentgroupids = array_values(array_unique(array_map("intval", $parentgroupids)));

        if (!$parentgroupids) {
            return [];
        }

        $createdchildgroupids = [];

        [$insql, $params] = $DB->get_in_or_equal($parentgroupids);
        $sql = "
            SELECT id, name
              FROM {groups}
             WHERE id {$insql}";
        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $g) {
            $existing = $DB->get_record("groups", [
                "courseid" => $childcourseid,
                "name" => $g->name,
            ], "id");

            if ($existing) {
                $childgroupid = $existing->id;
            } else {
                $childgroupid = $DB->insert_record(
                    "groups", (object) [
                    "courseid" => $childcourseid,
                    "name" => $g->name,
                    "timecreated" => time(),
                ]
                );
            }

            groups_add_member($childgroupid, $userid);
            $createdchildgroupids[] = $childgroupid;
        }

        return array_values(array_unique($createdchildgroupids));
    }

    /**
     * Ensures a state row exists for (instance, user).
     *
     * @param int $instanceid Instance id.
     * @param int $userid User id.
     * @return void
     * @throws dml_exception
     */
    protected function ensure_state_row($instanceid, $userid) {
        global $DB;

        $existing = $DB->get_record("childcourse_state", [
            "childcourseinstanceid" => $instanceid,
            "userid" => $userid,
        ], "id");

        if ($existing) {
            return;
        }

        $DB->insert_record(
            "childcourse_state", (object) [
            "childcourseinstanceid" => $instanceid,
            "userid" => $userid,
            "finalgrade" => null,
            "gradeitemtimemodified" => 0,
            "grade_source" => "course_total",
            "coursecompleted" => 0,
            "coursecompletiontimemodified" => 0,
            "timemodified" => time(),
        ]
        );
    }
}
