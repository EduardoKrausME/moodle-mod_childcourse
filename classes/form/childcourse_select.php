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
 * mod_childcourse form_childcourse_select
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("{$CFG->libdir}/form/selectgroups.php");

/**
 * Child course selector with optgroups (categories).
 */
class mod_childcourse_form_childcourse_select extends MoodleQuickForm_selectgroups {

    /**
     * Constructor.
     *
     * @param string|null $elementname Name.
     * @param mixed $elementlabel Label.
     * @param array|null $optgrps Options (may contain optgroups).
     * @param mixed|null $attributes Attributes.
     */
    public function __construct($elementname = null, $elementlabel = null, $optgrps = null, $attributes = null) {
        // Always show Moodle's standard "Choose..." option as the first item (value 0).
        parent::__construct($elementname, $elementlabel, null, $attributes, true);

        if ($optgrps == null) {
            return;
        }

        $groups = array_filter($optgrps, function($value) {
            return is_array($value);
        });

        if ($groups) {
            $this->loadArrayOptGroups($groups);
        }
    }
}
