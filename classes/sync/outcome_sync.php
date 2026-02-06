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
 * outcome_sync.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\sync;

use dml_exception;
use stdClass;

/**
 * Handles optional outcome grade sync for a module instance.
 *
 * This module grade is a percentage (0..100) from the child course total.
 * Outcomes in Moodle are scale-based. We only auto-map outcomes when the scale values are numeric.
 */
class outcome_sync {

    /**
     * Sync outcomes for a user.
     *
     * @param stdClass $instance Module instance (childcourse record).
     * @param int $userid User id.
     * @param float|null $activitygrade Activity grade (0..100) or null.
     * @return void
     * @throws dml_exception
     */
    public function sync_user_outcomes(stdClass $instance, $userid, $activitygrade) {
        global $CFG;

        // Outcomes can be disabled at site level.
        if (empty($CFG->enableoutcomes)) {
            return;
        }

        // Without a grade, we skip outcome updates (avoid accidental clearing).
        if ($activitygrade === null) {
            return;
        }

        $items = $this->get_outcome_grade_items((int)$instance->id);
        if (!$items) {
            return;
        }

        $data = [];
        foreach ($items as $item) {
            $mapped = $this->map_percent_to_scale_value((float)$activitygrade, (int)$item->scaleid);
            if ($mapped === null) {
                continue;
            }

            // The grade_update_outcomes expects: [itemnumber => outcomegrade].
            $data[(int)$item->itemnumber] = $mapped;
        }

        if (!$data) {
            return;
        }

        require_once("{$CFG->libdir}/gradelib.php");
        grade_update_outcomes(
            "mod/childcourse",
            (int)$instance->course,
            "mod",
            "childcourse",
            (int)$instance->id,
            (int)$userid,
            $data
        );
    }

    /**
     * Loads outcome grade items for this module instance.
     *
     * We filter by itemnumber <> 0 and gradetype = SCALE to avoid touching the main activity item.
     *
     * @param int $instanceid Instance id.
     * @return stdClass[] Grade item records.
     * @throws dml_exception
     */
    protected function get_outcome_grade_items($instanceid) {
        global $DB;

        $sql = "
            SELECT gi.id, gi.itemnumber, gi.scaleid
              FROM {grade_items} gi
             WHERE gi.itemtype      = 'mod'
               AND gi.itemmodule    = 'childcourse'
               AND gi.iteminstance  = ?
               AND gi.itemnumber   <> 0
               AND gi.gradetype     = ?
               AND gi.scaleid       > 0
          ORDER BY gi.itemnumber ASC";
        return $DB->get_records_sql($sql, [$instanceid, GRADE_TYPE_SCALE]);
    }

    /**
     * Maps a percentage grade (0..100) into a numeric scale value index (1-based).
     *
     * If the scale items are not purely numeric, returns null.
     *
     * @param float $percent Grade percent.
     * @param int $scaleid Scale id.
     * @return int|null 1-based index into the scale, or null if not mappable.
     * @throws dml_exception
     */
    protected function map_percent_to_scale_value($percent, $scaleid) {
        global $DB;

        $scale = $DB->get_record("scale", ["id" => (int)$scaleid], "id,scale");
        if (!$scale || empty($scale->scale)) {
            return null;
        }

        $rawitems = array_map("trim", explode(",", (string)$scale->scale));
        $rawitems = array_values(array_filter($rawitems, function($v) {
            return $v !== "";
        }));

        if (!$rawitems) {
            return null;
        }

        $numbers = [];
        foreach ($rawitems as $item) {
            if (!preg_match("/^-?\\d+(\\.\\d+)?$/", $item)) {
                return null;
            }
            $numbers[] = (float)$item;
        }

        $min = min($numbers);
        $max = max($numbers);

        if ($max == $min) {
            return 1;
        }

        $percent = max(0.0, min(100.0, (float)$percent));
        $target = $min + (($percent / 100.0) * ($max - $min));

        $bestindex = 1;
        $bestdist = null;

        foreach ($numbers as $i => $value) {
            $dist = abs($value - $target);
            if ($bestdist === null || $dist < $bestdist) {
                $bestdist = $dist;
                $bestindex = $i + 1; // 1-based for scale grades.
            }
        }

        return $bestindex;
    }
}
