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
 * view.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_childcourse\enrol\enrol_manager;
use mod_childcourse\output\view_page;
use mod_childcourse\sync\completion_sync;
use mod_childcourse\sync\grade_sync;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$action = optional_param("action", "", PARAM_ALPHA);

$cm = get_coursemodule_from_id("childcourse", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability("mod/childcourse:view", $context);

$instance = $DB->get_record("childcourse", ["id" => $cm->instance], "*", MUST_EXIST);

$enrolmanager = new enrol_manager();

if ($action === "open") {
    $enrolmanager->redirect_enrolled($instance, $cm, $course);
}

if ($action === "sync") {
    require_sesskey();
    require_capability("mod/childcourse:sync", $context);

    $gradesync = new grade_sync();
    $compsync = new completion_sync();

    $gradesync->sync_instance_incremental($instance);
    $compsync->sync_instance_incremental($instance, $cm);

    redirect(new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id]), get_string("syncdone", "childcourse"), 1);
}

$PAGE->set_url("/mod/childcourse/view.php", ["id" => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$canmanagesync = has_capability("mod/childcourse:sync", $context);
$canaddinstance = has_capability("mod/childcourse:addinstance", $context);

if (!$canaddinstance) {
    $enrolmanager->redirect_enrolled($instance, $cm, $course);
}

echo $OUTPUT->header();

$childcourse = $DB->get_record("course", ["id" => $instance->childcourseid], "id,fullname,enablecompletion");

$gradeok = grade_sync::child_course_has_course_total($instance->childcourseid);
$completionok = !empty($childcourse) && $childcourse->enablecompletion === 1;

$openurl = new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id, "action" => "open"]);
$syncurl = new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id, "action" => "sync", "sesskey" => sesskey()]);

if ($canaddinstance) {
    $mustachecontext = [
        "name" => format_string($instance->name),
        "intro" => format_module_intro("childcourse", $instance, $cm->id),

        "childcourse_name" => $childcourse ? format_string($childcourse->fullname) : "",
        "childcourse_id" => $instance->childcourseid,

        "open_url" => $openurl,
        "open_new_tab" => ($instance->opennewtab === 1),

        "autoenrol" => ($instance->autoenrol === 1),

        "can_sync" => $canmanagesync,
        "sync_url" => $syncurl,

        "lastsync_grade" => empty($instance->lastsyncgrade)
            ? get_string("never", "childcourse")
            : userdate($instance->lastsyncgrade),

        "lastsync_completion" => empty($instance->lastsynccompletion)
            ? get_string("never", "childcourse")
            : userdate($instance->lastsynccompletion),

        "grade_ok" => $gradeok,
        "completion_ok" => $completionok,
        "grade_warning" => get_string("gradebookmissing", "childcourse"),
        "completion_warning" => get_string("completionmissing", "childcourse"),
    ];
    echo $OUTPUT->render_from_template("childcourse/view", $mustachecontext);
}

echo $OUTPUT->footer();
