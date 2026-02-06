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
 * course_details.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\external;

use context_course;
use core_external\restricted_context_exception;
use dml_exception;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_childcourse\sync\grade_sync;
use moodle_exception;
use required_capability_exception;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
require_once("{$CFG->libdir}/externallib.php");

/**
 * External function for loading child course details via Ajax.
 */
class course_details extends external_api {

    /**
     * Describes parameters.
     *
     * @return external_function_parameters Parameters.
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            "parentcourseid" => new external_value(PARAM_INT, "Parent course id"),
            "childcourseid" => new external_value(PARAM_INT, "Child course id"),
        ]);
    }

    /**
     * Loads details for a child course.
     *
     * @param int $parentcourseid Parent course id.
     * @param int $childcourseid Child course id.
     * @return array Details.
     * @throws restricted_context_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws moodle_exception
     */
    public static function execute($parentcourseid, $childcourseid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            "parentcourseid" => $parentcourseid,
            "childcourseid" => $childcourseid,
        ]);

        $parentcontext = context_course::instance($params["parentcourseid"]);
        self::validate_context($parentcontext);
        require_capability("moodle/course:update", $parentcontext);

        $childcourse =
            $DB->get_record("course", ["id" => $params["childcourseid"]], "id,fullname,enablecompletion", MUST_EXIST);

        $groups = $DB->get_records("groups", ["courseid" => $childcourse->id], "name ASC", "id,name");
        $groupoptions = [];
        foreach ($groups as $g) {
            $groupoptions[] = [
                "id" => $g->id,
                "name" => format_string($g->name),
            ];
        }

        $gradeok = grade_sync::child_course_has_course_total($childcourse->id);

        $modules = self::get_course_modules($childcourse->id);

        return [
            "childcourse" => [
                "id" => $childcourse->id,
                "fullname" => format_string($childcourse->fullname),
                "enablecompletion" => ($childcourse->enablecompletion === 1),
            ],
            "groups" => $groupoptions,
            "gradebookconfigured" => $gradeok,
            "modules" => $modules,
        ];
    }

    /**
     * Returns course modules for selectors.
     *
     * @param int $courseid Course id.
     * @return array Modules.
     * @throws dml_exception
     * @throws moodle_exception
     */
    protected static function get_course_modules($courseid) {
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);

        $sections = $modinfo->get_section_info_all();
        $result = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }

            $sectionname = "";
            $sectionnum = $cm->sectionnum;
            if (isset($sections[$sectionnum])) {
                $sectionname = get_section_name($course, $sections[$sectionnum]);
            }

            $label = trim($sectionname . " · " . $cm->name . " (" . $cm->modname . ")");
            $result[] = [
                "id" => $cm->id,
                "label" => format_string($label),
            ];
        }

        return $result;
    }

    /**
     * Describes return structure.
     *
     * @return external_single_structure Structure.
     */
    public static function execute_returns() {
        return new external_single_structure([
            "childcourse" => new external_single_structure([
                "id" => new external_value(PARAM_INT, "Course id"),
                "fullname" => new external_value(PARAM_TEXT, "Course fullname"),
                "enablecompletion" => new external_value(PARAM_BOOL, "Completion enabled"),
            ]),
            "groups" => new external_multiple_structure(
                new external_single_structure([
                    "id" => new external_value(PARAM_INT, "Group id"),
                    "name" => new external_value(PARAM_TEXT, "Group name"),
                ])
            ),
            "gradebookconfigured" => new external_value(PARAM_BOOL, "Course total exists"),
            "modules" => new external_multiple_structure(
                new external_single_structure([
                    "id" => new external_value(PARAM_INT, "Course module id"),
                    "label" => new external_value(PARAM_TEXT, "Label"),
                ])
            ),
        ]);
    }
}
