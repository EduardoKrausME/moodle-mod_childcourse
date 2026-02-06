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
 * manage_table.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\table;

use cm_info;
use coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use html_writer;
use moodle_url;
use stdClass;
use table_sql;

/**
 * SQL table for the childcourse manage page.
 */
class manage_table extends table_sql {
    /** @var int */
    protected $courseid = 0;

    /** @var array<int, cm_info> */
    protected $cmsbyinstanceid = [];

    /** @var array<int, stdClass|null> */
    protected static $coursecache = [];

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique table id.
     * @param moodle_url $baseurl Base URL for paging/sorting.
     * @param int $courseid Parent course id.
     * @param array<int, cm_info> $cmsbyinstanceid Map: instanceid => cm_info.
     * @throws coding_exception
     */
    public function __construct(string $uniqueid, moodle_url $baseurl, int $courseid, array $cmsbyinstanceid) {
        parent::__construct($uniqueid);

        $this->courseid = $courseid;
        $this->cmsbyinstanceid = $cmsbyinstanceid;

        $this->define_baseurl($baseurl);

        $this->define_columns(["name", "childcourse", "sync", "actions"]);
        $this->define_headers([
            get_string("manage_header_name", "childcourse"),
            get_string("childcourse", "childcourse"),
            get_string("lastsync", "childcourse"),
            get_string("manage_header_actions", "childcourse"),
        ]);

        $this->set_attribute("class", "generaltable table-striped");
    }

    /**
     * Renders the instance name column.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws moodle_exception
     */
    public function col_name($row): string {
        $cm = $this->get_cm_for_instance((int) $row->id);
        $name = format_string($row->name);

        if (!$cm) {
            return $name;
        }

        $url = new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id]);
        return html_writer::link($url, $name);
    }

    /**
     * Renders the linked child course column.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws moodle_exception
     * @throws dml_exception
     */
    public function col_childcourse($row): string {
        $childcourseid = (int) ($row->childcourseid ?? 0);
        if (empty($childcourseid)) {
            return "-";
        }

        $course = $this->get_course_cached($childcourseid);
        $label = $course ? format_string($course->fullname) : ("Course #" . $childcourseid);

        $url = new moodle_url("/course/view.php", ["id" => $childcourseid]);

        return html_writer::link($url, $label)
            . html_writer::div("ID: " . $childcourseid, "small text-muted");
    }

    /**
     * Renders last sync column.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws coding_exception
     */
    public function col_sync($row): string {
        $never = get_string("never", "childcourse");

        $grade = empty($row->lastsyncgrade) ? $never : userdate((int) $row->lastsyncgrade);
        $completion = empty($row->lastsynccompletion) ? $never : userdate((int) $row->lastsynccompletion);

        $out = [];
        $out[] = html_writer::div("<strong>".get_string("gradenoun").":</strong> " . $grade);
        $out[] = html_writer::div("<strong>".get_string("coursecompletion").":</strong> " . $completion);

        return html_writer::div(implode("", $out), "small text-muted");
    }

    /**
     * Renders actions column.
     *
     * @param stdClass $row Table row.
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function col_actions($row): string {
        $cm = $this->get_cm_for_instance((int) $row->id);
        if (!$cm) {
            return "";
        }

        $buttons = [];

        $viewurl = new moodle_url("/mod/childcourse/view.php", ["id" => $cm->id]);
        $buttons[] = html_writer::link(
            $viewurl,
            get_string("view"),
            ["class" => "btn btn-sm btn-outline-primary me-1"]
        );

        $syncurl = new moodle_url("/mod/childcourse/view.php", [
            "id" => $cm->id,
            "action" => "sync",
            "sesskey" => sesskey(),
        ]);
        $buttons[] = html_writer::link(
            $syncurl,
            get_string("syncnow", "childcourse"),
            ["class" => "btn btn-sm btn-outline-secondary me-1"]
        );

        $editurl = new moodle_url("/course/modedit.php", ["update" => $cm->id, "return" => 1]);
        $buttons[] = html_writer::link(
            $editurl,
            get_string("editsettings"),
            ["class" => "btn btn-sm btn-outline-dark"]
        );

        return implode("", $buttons);
    }

    /**
     * Returns cm_info for an instance id.
     *
     * @param int $instanceid Instance id.
     * @return cm_info|null
     */
    protected function get_cm_for_instance(int $instanceid): ?cm_info {
        return $this->cmsbyinstanceid[$instanceid] ?? null;
    }

    /**
     * Loads a course record using a small static cache.
     *
     * @param int $courseid Course id.
     * @return stdClass|null
     * @throws dml_exception
     */
    protected function get_course_cached(int $courseid): ?stdClass {
        global $DB;

        if (array_key_exists($courseid, self::$coursecache)) {
            return self::$coursecache[$courseid];
        }

        $record = $DB->get_record("course", ["id" => $courseid], "id,fullname");
        self::$coursecache[$courseid] = $record ?: null;

        return self::$coursecache[$courseid];
    }
}
