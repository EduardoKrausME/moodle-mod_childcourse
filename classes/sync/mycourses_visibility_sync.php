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
 * mycourses_visibility_sync.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\sync;

use coding_exception;
use dml_exception;

/**
 * Synchronizes the "hidden in My courses" user preference for tracked child course enrolments.
 */
class mycourses_visibility_sync {

    /**
     * Re-applies the hidden preference for all tracked enrolments whose instance
     * has hideinmycourses enabled.
     *
     * @return int Number of processed mapping rows.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function sync() {
        global $DB;

        $processed = 0;
        $updatedprefs = 0;
        $updatedmaps = 0;

        $sql = "SELECT map.id,
                       map.userid,
                       map.hiddenprefset,
                       cc.childcourseid
                  FROM {childcourse_map} map
                  JOIN {childcourse}      cc
                    ON cc.id              = map.childcourseinstanceid
                 WHERE cc.hideinmycourses = :hideinmycourses";

        $recordset = $DB->get_recordset_sql($sql, [
            "hideinmycourses" => 1,
        ]);

        foreach ($recordset as $record) {
            $processed++;

            $prefname = "block_myoverview_hidden_course_" . $record->childcourseid;
            $currentvalue = get_user_preferences($prefname, null, $record->userid);

            if ((string)$currentvalue !== "1") {
                set_user_preference($prefname, 1, $record->userid);
                $updatedprefs++;
            }

            if ((int)$record->hiddenprefset !== 1) {
                $DB->set_field("childcourse_map", "hiddenprefset", 1, [
                    "id" => $record->id,
                ]);
                $updatedmaps++;
            }
        }

        $recordset->close();

        mtrace("mod_childcourse: processed {$processed} childcourse visibility mappings.");
        mtrace("mod_childcourse: updated {$updatedprefs} My courses hidden preferences.");
        mtrace("mod_childcourse: normalized {$updatedmaps} childcourse_map.hiddenprefset flags.");

        return $processed;
    }
}
