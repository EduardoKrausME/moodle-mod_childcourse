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
 * thumb.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$cmid = required_param("cmid", PARAM_INT);
if (!$cmid) {
    require_login();
}

$cache = cache::make("theme_eadtraining", "course_cache");
$cachekey = "mod_childcourse_thumb_{$cmid}";
if ($cache->has($cachekey)) {
    $url = $cache->get($cachekey);
    redirect($url);
}

$cm = get_coursemodule_from_id("childcourse", $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

$course = new core_course_list_element($course);

/** @var stored_file $file */
foreach ($course->get_course_overviewfiles() as $file) {
    if ($file->is_valid_image()) {
        $contextid = $file->get_contextid();
        $component = $file->get_component();
        $filearea = $file->get_filearea();
        $filepath = $file->get_filepath();
        $filename = $file->get_filename();
        $url = moodle_url::make_pluginfile_url($contextid, $component, $filearea, null, $filepath, $filename);

        $cache->set($cachekey, $url);
        redirect($url);
    }
}

$url = $OUTPUT->get_generated_url_for_course(context_course::instance($course->id));

$cache->set($cachekey, $url);
redirect($url);
