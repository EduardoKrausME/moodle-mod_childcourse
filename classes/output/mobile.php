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
 * mobile.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\output;

use coding_exception;
use context_module;
use core\exception\moodle_exception as core_moodle_exception;
use dml_exception;
use mod_childcourse\enrol\enrol_manager;
use mod_childcourse\event\course_module_viewed;
use moodle_exception;
use moodle_url;
use require_login_exception;
use required_capability_exception;

/**
 * Mobile app output for the activity.
 */
class mobile {

    /**
     * Renders the activity content inside the Moodle App.
     *
     * @param array $args Arguments from the app (courseid, cmid, userid).
     * @return array Content response.
     * @throws coding_exception
     * @throws core_moodle_exception
     * @throws dml_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public static function mobile_course_view($args) {
        global $DB, $OUTPUT, $USER;

        $args = (object) $args;
        $cm = get_coursemodule_from_id("childcourse", $args->cmid, 0, false, MUST_EXIST);

        require_login($args->courseid, false, $cm, true, true);

        $context = context_module::instance($cm->id);
        require_capability("mod/childcourse:view", $context);

        $instance = $DB->get_record("childcourse", ["id" => $cm->instance], "*", MUST_EXIST);
        $course = get_course($args->courseid);

        // If configured, ensure the current user is enrolled in the child course before showing the open button.
        if ( $instance->autoenrol == 1 &&  $USER->id ==  $args->userid) {
            $enrolmanager = new enrol_manager();
            $enrolmanager->ensure_user_enrolled($instance, $cm,  $course);
        }

        // Log the view.
        $event = course_module_viewed::create([
            "objectid" => $cm->instance,
            "context" => $context,
        ]);
        $event->add_record_snapshot("course", $course);
        $event->add_record_snapshot("childcourse", $instance);
        $event->trigger();

        $courseurl = new moodle_url("/course/view.php", ["id" => $instance->childcourseid]);

        $data = ["open_course_url" => $courseurl];

        return [
            "templates" => [
                [
                    "id" => "main",
                    "html" => $OUTPUT->render_from_template("childcourse/view-mobile", $data),
                ],
            ],
        ];
    }
}
