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
 * index.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$courseid = required_param("id", PARAM_INT);

$course = get_course($courseid);
require_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url(new moodle_url("/mod/childcourse/index.php", ["id" => $course->id]));
$PAGE->set_title(get_string("modulenameplural", "childcourse"));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course("childcourse", $course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("modulenameplural", "childcourse"));

if (empty($instances)) {
    echo $OUTPUT->notification(
        get_string("thereareno", "moodle", get_string("modulenameplural", "childcourse")),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->continue_button(new moodle_url("/course/view.php", ["id" => $course->id]));
    echo $OUTPUT->footer();
    exit;
}

$childcourseids = [];
foreach ($instances as $instance) {
    if (!empty($instance->childcourseid)) {
        $childcourseids[] = (int) $instance->childcourseid;
    }
}
$childcourseids = array_values(array_unique($childcourseids));

$childcoursesbyid = [];
if (!empty($childcourseids)) {
    $childcourses = $DB->get_records_list("course", "id", $childcourseids, "", "id, fullname, visible");
    foreach ($childcourses as $c) {
        $childcoursesbyid[(int) $c->id] = $c;
    }
}

$table = new html_table();
$table->attributes["class"] = "generaltable mod_index";
$table->head = [
    get_string("name"),
    get_string("label_childcourse", "childcourse"),
    get_string("autoenrol", "childcourse"),
];
$table->data = [];

foreach ($instances as $instance) {
    $cmid = (int) $instance->coursemodule;

    $name = format_string($instance->name, true, ["context" => $coursecontext]);
    $namelink = html_writer::link(
        new moodle_url("/mod/childcourse/view.php", ["id" => $cmid]),
        $name
    );

    $child = "";
    $childid = (int) ($instance->childcourseid ?? 0);
    if ($childid && isset($childcoursesbyid[$childid])) {
        $childcourse = $childcoursesbyid[$childid];
        $childname = format_string($childcourse->fullname, true, ["context" => context_course::instance($childcourse->id)]);
        $child = html_writer::link(new moodle_url("/course/view.php", ["id" => $childcourse->id]), $childname);
    } else if ($childid) {
        $child = get_string("missingcourse", "childcourse", $childid);
    } else {
        $child = get_string("childcoursenotset", "childcourse");
    }

    $autoenrol = !empty($instance->autoenrol) ? get_string("yes") : get_string("no");

    $table->data[] = [$namelink, $child, $autoenrol];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
