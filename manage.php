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
 * manage.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_childcourse\table\manage_table;

require_once(__DIR__ . "/../../config.php");
require_once("{$CFG->libdir}/tablelib.php");

$courseid = required_param("courseid", PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($courseid);
require_capability("moodle/course:update", $context);

$PAGE->set_url("/mod/childcourse/manage.php", ["courseid" => $courseid]);
$PAGE->set_title(get_string("modulenameplural", "childcourse"));
$PAGE->set_heading(format_string($course->fullname));

$modinfo = get_fast_modinfo($course);
$cmsbyinstanceid = [];

if (!empty($modinfo->instances["childcourse"])) {
    foreach ($modinfo->instances["childcourse"] as $instanceid => $cm) {
        $cmsbyinstanceid[(int) $instanceid] = $cm;
    }
}

$table = new manage_table("childcourse_manage", $PAGE->url, $courseid, $cmsbyinstanceid);

$table->set_sql(
    "cc.id, cc.name, cc.childcourseid, cc.lastsyncgrade, cc.lastsynccompletion", "{childcourse} cc", "cc.course = ?", [$courseid]
);

$table->sortable(true, "name", SORT_ASC);
$table->pageable(true);

echo $OUTPUT->header();

echo html_writer::start_div("container-fluid");
echo html_writer::tag("h3", format_string(get_string("modulenameplural", "childcourse")), ["class" => "mb-3"]);

$table->out(30, true);

echo html_writer::end_div();
echo $OUTPUT->footer();
