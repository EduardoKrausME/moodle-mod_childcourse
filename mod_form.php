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
 * mod_form.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_childcourse\instance\record_mapper;

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
require_once("{$CFG->dirroot}/course/moodleform_mod.php");

/**
 * Module instance form.
 */
class mod_childcourse_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     *
     * @return void
     * @throws coding_exception
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement("header", "general", get_string("general", "form"));
        $mform->addElement("text", "name", get_string("name"), ["size" => "64"]);
        $mform->setType("name", PARAM_TEXT);
        $mform->addRule("name", null, "required", null, "client");

        $this->standard_intro_elements();

        // Child course settings (ONLY link/enrol/navigation/grade sync).
        $mform->addElement("header", "settings", get_string("settings_heading", "childcourse"));

        $mform->addElement("select", "childcourseid", get_string("childcourse", "childcourse"), $this->get_course_options());
        $mform->addHelpButton("childcourseid", "childcourse", "childcourse");
        $mform->addRule("childcourseid", null, "required", null, "client");

        $options = [
            "1" => get_string("yes"),
            "0" => get_string("no"),
        ];

        $mform->addElement("select", "opennewtab", get_string("opennewtab", "childcourse"), $options);
        $mform->addHelpButton("opennewtab", "opennewtab", "childcourse");
        $mform->setDefault("opennewtab", 0);

        $mform->addElement("select", "autoenrol", get_string("autoenrol", "childcourse"), $options);
        $mform->addHelpButton("autoenrol", "autoenrol", "childcourse");
        $mform->setDefault("autoenrol", 1);

        $mform->addElement(
            "select", "targetgroupid", get_string("targetgroup", "childcourse"), [0 => get_string("nogroup", "childcourse")]
        );
        $mform->addHelpButton("targetgroupid", "targetgroup", "childcourse");

        $mform->addElement("select", "inheritgroups", get_string("inheritgroups", "childcourse"), $options);
        $mform->addHelpButton("inheritgroups", "inheritgroups", "childcourse");
        $mform->setDefault("inheritgroups", 0);

        $mform->addElement("select", "keeprole", get_string("keeprole", "childcourse"), $options);
        $mform->addHelpButton("keeprole", "keeprole", "childcourse");
        $mform->setDefault("keeprole", 1);

        $mform->addElement("select", "unenrolaction", get_string("unenrolaction", "childcourse"), [
            "unenrol" => get_string("unenrolaction_unenrol", "childcourse"),
            "keep" => get_string("unenrolaction_keep", "childcourse"),
        ]);
        $mform->addHelpButton("unenrolaction", "unenrolaction", "childcourse");
        $mform->setDefault("unenrolaction", "unenrol");

        $mform->addElement("select", "hideinmycourses", get_string("hideinmycourses", "childcourse"), $options);
        $mform->addHelpButton("hideinmycourses", "hideinmycourses", "childcourse");
        $mform->setDefault("hideinmycourses", 0);

        $dependentes = [
            "opennewtab",
            "autoenrol",
            "targetgroupid",
            "inheritgroups",
            "keeprole",
            "unenrolaction",
            "hideinmycourses",
        ];
        foreach ($dependentes as $campo) {
            $mform->hideIf($campo, "childcourseid", "eq", 0);
        }

        // JS for AJAX + rule field toggle.
        $PAGE->requires->js_call_amd("mod_childcourse/form", "init", []);

        // Standard module elements (includes "Completion conditions" / Activity completion section).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Adds custom completion rules.
     * Moodle will display these inside the Activity completion section ("Completion conditions").
     *
     * @return array Element names that are part of completion rules.
     * @throws coding_exception
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement("select", "completionrule", get_string("completionrule", "childcourse"), [
            "none" => get_string("completionrule_none", "childcourse"),
            "coursecompleted" => get_string("completionrule_coursecompleted", "childcourse"),
            "allactivities" => get_string("completionrule_allactivities", "childcourse"),
        ]);
        $mform->addHelpButton("completionrule", "completionrule", "childcourse");
        $mform->setDefault("completionrule", "coursecompleted");

        return ["completionrule"];
    }

    /**
     * Indicates whether the completion rules are enabled based on form data.
     * This makes Moodle treat completion as "Automatic" only when rule != none.
     *
     * @param array $data Form data.
     * @return bool True if enabled.
     */
    public function completion_rule_enabled($data) {
        // Custom rules only make sense with automatic completion tracking.
        if (empty($data["completion"]) || (int) $data["completion"] !== COMPLETION_TRACKING_AUTOMATIC) {
            return false;
        }

        if (empty($data["completionrule"]) || $data["completionrule"] === "none") {
            return false;
        }

        return true;
    }

    /**
     * Freezes child course selector for existing instances.
     *
     * @return void
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform = $this->_form;

        if (!empty($this->current->instance)) {
            $childcourseid = (int) ($this->current->childcourseid ?? 0);

            // If instance was restored and childcourseid could not be mapped, allow selecting again.
            if ($childcourseid > 0 && $mform->elementExists("childcourseid")) {
                $mform->freeze("childcourseid");
            }
        }
    }

    /**
     * Preprocesses form data.
     *
     * @param array $defaultvalues Default values.
     * @return array Prepared values.
     */
    public function data_preprocessing(&$defaultvalues) {
        $defaultvalues = (array) record_mapper::prepare_form_data((object) $defaultvalues);
        return $defaultvalues;
    }

    /**
     * Validates rules that depend on child course activity selection.
     *
     * @param array $data Data.
     * @param array $files Files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

    /**
     * Returns course options for a simple select.
     *
     * @return array<int,string> Options.
     * @throws dml_exception
     */
    protected function get_course_options() {
        global $DB;

        $sql = "
            SELECT c.id, c.fullname
              FROM {course} c
             WHERE c.id      <> ?
               AND c.visible  = 1
          ORDER BY c.fullname ASC";
        $records = $DB->get_records_sql($sql, [$this->get_course()->id]);

        $options = [0 => ""];
        foreach ($records as $c) {
            $options[(int) $c->id] = format_string($c->fullname, true, ["context" => context_course::instance($c->id)]);
        }

        return $options;
    }
}
