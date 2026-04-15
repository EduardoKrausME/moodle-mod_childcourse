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
 * report.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_childcourse\table\report_table;

require_once(__DIR__ . "/../../config.php");
require_once("{$CFG->libdir}/tablelib.php");

$id = required_param("id", PARAM_INT);

$cm = get_coursemodule_from_id("childcourse", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$instance = $DB->get_record("childcourse", ["id" => $cm->instance], "*", MUST_EXIST);
$childcourse = $DB->get_record("course", ["id" => $instance->childcourseid], "id,fullname,enablecompletion", MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability("mod/childcourse:manage", $context);

$PAGE->set_url("/mod/childcourse/report.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("reporttitle", "childcourse"));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(format_string($instance->name), new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id]));
$PAGE->navbar->add(get_string("reporttitle", "childcourse"));

$table = new report_table(
    "mod_childcourse_report_{$instance->id}",
    $PAGE->url,
    $instance,
    $cm,
    $course,
    $childcourse
);

$table->set_sql(
    "m.id,
     u.id AS userid,
     u.firstname,
     u.lastname,
     u.email,
     cs.finalgrade AS syncedremotecache,
     cs.coursecompleted AS syncedcompletioncache,
     cs.timemodified AS cachetimemodified",
    "{childcourse_map} m
LEFT JOIN {user} u ON u.id = m.userid
LEFT JOIN {childcourse_state} cs
       ON cs.childcourseinstanceid = m.childcourseinstanceid
      AND cs.userid = m.userid",
    "m.childcourseinstanceid = ?",
    [$instance->id]
);

$table->sortable(true, "firstname", SORT_ASC);
$table->pageable(true);

$syncurl = new moodle_url("/mod/childcourse/view.php", [
    "id" => $cm->id,
    "action" => "sync",
    "sesskey" => sesskey(),
]);

$backurl = new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id]);

echo $OUTPUT->header();

echo html_writer::start_div("container-fluid");
echo html_writer::tag("h3", get_string("reporttitle", "childcourse"), ["class" => "mb-2"]);
echo html_writer::div(format_string($instance->name), "text-muted mb-3");

$summary = [];
$summary[] = html_writer::div(
    "<strong>" . get_string("label_childcourse", "childcourse") . ":</strong> " . format_string($childcourse->fullname),
    "mb-1"
);
$summary[] = html_writer::div(
    "<strong>" . get_string("label_lastsyncgrade", "childcourse") . ":</strong> "
    . (empty($instance->lastsyncgrade) ? get_string("never", "childcourse") : userdate($instance->lastsyncgrade)),
    "mb-1"
);
$summary[] = html_writer::div(
    "<strong>" . get_string("label_lastsynccompletion", "childcourse") . ":</strong> "
    . (empty($instance->lastsynccompletion) ? get_string("never", "childcourse") : userdate($instance->lastsynccompletion)),
    "mb-3"
);

echo html_writer::div(implode("", $summary), "alert alert-light border");

echo html_writer::start_div("mb-3 d-flex gap-2 flex-wrap");
echo html_writer::link($backurl, get_string("view"), ["class" => "btn btn-outline-secondary"]);
echo html_writer::link($syncurl, get_string("syncnow", "childcourse"), ["class" => "btn btn-outline-primary"]);
echo html_writer::end_div();

$table->out(30, true);

echo html_writer::end_div();
echo $OUTPUT->footer();
