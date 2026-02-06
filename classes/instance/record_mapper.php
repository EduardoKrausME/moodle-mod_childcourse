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
 * record_mapper.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_childcourse\instance;

use stdClass;

/**
 * Maps form data into a record compatible with the DB schema.
 */
class record_mapper {
    /**
     * Converts form data into a persistable record.
     *
     * @param stdClass $data Form data.
     * @return stdClass Persistable record.
     */
    public static function prepare_record(stdClass $data) {
        $record = clone $data;

        // Ensure ints.
        $record->completioncmid = ($record->completioncmid ?? 0);

        return $record;
    }

    /**
     * Converts DB record into form-friendly data.
     *
     * @param stdClass $record DB record.
     * @return stdClass Form data.
     */
    public static function prepare_form_data(stdClass $record) {
        $data = clone $record;

        $data->completionset = [];
        if (!empty($data->completionsetjson)) {
            $decoded = json_decode($data->completionsetjson, true);
            if (is_array($decoded)) {
                $data->completionset = array_values(array_unique(array_map("intval", $decoded)));
            }
        }

        return $data;
    }
}
