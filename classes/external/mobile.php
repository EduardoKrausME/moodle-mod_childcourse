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
 * Mobile external service for mod_childcourse.
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\external;

use completion_info;
use context_course;
use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_childcourse\enrol\enrol_manager;
use mod_childcourse\event\course_module_viewed;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->libdir}/externallib.php");
require_once("{$CFG->libdir}/completionlib.php");

/**
 * Mobile external service for opening the child course from the custom app.
 */
class mobile extends external_api {

    /**
     * Returns the service parameters.
     *
     * @return external_function_parameters
     */
    public static function datamobile_parameters(): external_function_parameters {
        return new external_function_parameters([
            "instanceid" => new external_value(PARAM_INT, "Childcourse instance id"),
        ]);
    }

    /**
     * Ensures access/enrolment and returns the child course id.
     *
     * @param int $instanceid Childcourse instance id.
     * @return array
     * @throws moodle_exception
     */
    public static function datamobile(int $instanceid): array {
        global $DB;

        $params = self::validate_parameters(self::mobile_parameters(), [
            "instanceid" => $instanceid,
        ]);

        $instance = $DB->get_record("childcourse", ["id" => $params["instanceid"]], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("childcourse", $instance->id, $instance->course, false, MUST_EXIST);
        $parentcourse = get_course($cm->course);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability("mod/childcourse:view", $context);

        if ((int) $instance->autoenrol === 1) {
            $manager = new enrol_manager();
            $manager->ensure_user_enrolled($instance, $cm, $parentcourse);
        }

        $childcourse = $DB->get_record("course", ["id" => $instance->childcourseid], "id,fullname", MUST_EXIST);
        $childcontext = context_course::instance($childcourse->id);

        if (!has_capability("moodle/course:view", $childcontext)) {
            throw new moodle_exception("nopermissions", "error", "", "view child course");
        }

        self::trigger_viewed_event($instance, $cm, $parentcourse, $context);

        $completion = new completion_info($parentcourse);
        $completion->set_module_viewed($cm);

        return [
            "refcourse" => (int) $childcourse->id,
            "childcourseid" => (int) $childcourse->id,
            "courseid" => (int) $childcourse->id,
        ];
    }

    /**
     * Triggers the childcourse viewed event without redirecting the user.
     *
     * @param object $instance Childcourse instance.
     * @param object $cm Course module.
     * @param object $parentcourse Parent course.
     * @param context_module $context Module context.
     * @return void
     */
    private static function trigger_viewed_event($instance, $cm, $parentcourse, context_module $context): void {
        $event = course_module_viewed::create([
            "objectid" => $instance->id,
            "context" => $context,
        ]);
        $event->add_record_snapshot("course", $parentcourse);
        $event->add_record_snapshot("childcourse", $instance);
        $event->trigger();
    }

    /**
     * Returns the service result structure.
     *
     * @return external_single_structure
     */
    public static function datamobile_returns(): external_single_structure {
        return new external_single_structure([
            "refcourse" => new external_value(PARAM_INT, "Child course id"),
            "childcourseid" => new external_value(PARAM_INT, "Child course id"),
            "courseid" => new external_value(PARAM_INT, "Child course id"),
        ]);
    }
}
