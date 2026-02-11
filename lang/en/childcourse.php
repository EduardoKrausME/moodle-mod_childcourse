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
 * childcourse.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['autoenrol'] = 'Auto-enrol on access';
$string['autoenrol_help'] = 'If enabled, the plugin will automatically enrol the user in the child course when they open it through this activity. Enrolments are created using a dedicated Manual enrolment instance so they can be tracked and safely reverted later (depending on the removal policy). If disabled, the plugin will not attempt to enrol users automatically.';
$string['childcourse'] = 'Child course';
$string['childcourse:addinstance'] = 'Add a new child course activity';
$string['childcourse:manage'] = 'Manage child course settings';
$string['childcourse:sync'] = 'Sync child course grade and completion';
$string['childcourse:view'] = 'View child course activity';
$string['childcourse_help'] = 'Select the course that will be linked to this activity. This choice controls all rule-specific settings (groups, completion rules, activity selectors, grade sync). After the activity is saved, the child course becomes immutable to keep mappings and sync history consistent.';
$string['childcoursenotset'] = 'The child course has not been set.';
$string['completionmissing'] = 'Child course completion is not enabled.';
$string['completionrule'] = 'Completion rule based on the child course';
$string['completionrule_allactivities'] = 'Complete when 100% of the tracked activities are completed';
$string['completionrule_coursecompleted'] = 'Complete when the child course is completed';
$string['completionrule_help'] = 'Defines how this activity is automatically marked as complete based on the user\'s progress in the child course.

- **Do nothing:** completion of this activity has no relation to child course completion.
- **When the child course is completed:** as soon as the child course is completed, this activity is also completed.
- **When 100% of tracked activities are completed:** all activities in the child course with completion tracking enabled must be completed for this activity to be completed.';
$string['completionrule_none'] = 'Do nothing';
$string['enrolinstancename'] = 'Child course link #{$a}';
$string['error_manualenrolnotavailable'] = 'The Manual enrolment plugin is not available.';
$string['grade_approval'] = 'Send grade from';
$string['grade_approval_no'] = 'Do not send grade';
$string['grade_approval_yes'] = 'Use grade from the child course';
$string['gradebookmissing'] = 'The child course gradebook is not configured (the course total is missing).';
$string['hideinmycourses'] = 'Hide child course in My courses';
$string['hideinmycourses_help'] = 'If enabled, users enrolled by this activity will have the child course hidden in the "My courses" menu. This helps enforce navigation through this course. This setting only affects users enrolled by this plugin (tracked by the plugin).';
$string['inheritgroups'] = 'Inherit groups from the parent course';
$string['inheritgroups_help'] = 'If enabled, the plugin will try to replicate the user\'s group memberships from the parent course to the child course, matching by group names. If a group name does not exist in the child course, it may be created. This is applied during auto-enrolment. It is not a continuous sync unless you later implement a dedicated re-sync routine.';
$string['keeprole'] = 'Keep role (student/teacher)';
$string['keeprole_help'] = 'If enabled, the plugin will try to keep a simplified role parity: users with teacher-level capabilities in the parent course will be enrolled as teacher (editingteacher/teacher when available); otherwise, as student. This does not copy custom roles or complex role assignments.';
$string['label_childcourse'] = 'Child course';
$string['label_lastsynccompletion'] = 'Last completion sync';
$string['label_lastsyncgrade'] = 'Last grade sync';
$string['lastsync'] = 'Last sync';
$string['lockedcoursewarning'] = 'The child course cannot be changed after saving.';
$string['manage_header_actions'] = 'Actions';
$string['manage_header_name'] = 'Name';
$string['missingcourse'] = 'Missing course';
$string['modulename'] = 'Child course';
$string['modulenameplural'] = 'Child courses';
$string['never'] = 'Never';
$string['nogroup'] = 'No group';
$string['openchildcourse'] = 'Open child course';
$string['opennewtab'] = 'Open in a new tab';
$string['opennewtab_help'] = 'If enabled, the button will open the child course in a new tab. This does not change enrolment or sync behaviour, only how the course is opened for the user.';
$string['pluginadministration'] = 'Child course administration';
$string['pluginname'] = 'Child course';
$string['privacy:metadata:childcourse_map'] = 'Stores mapping data created by the linked course activity to allow safe unenrolment and auditing.';
$string['privacy:metadata:childcourse_map:childcourseid'] = 'The ID of the child course that was linked.';
$string['privacy:metadata:childcourse_map:childcourseinstanceid'] = 'The ID of the linked course activity instance.';
$string['privacy:metadata:childcourse_map:groupidsjson'] = 'The list of child course group IDs assigned by the plugin (JSON).';
$string['privacy:metadata:childcourse_map:hiddenprefset'] = 'Indicates whether the plugin set the preference to hide the child course in My courses.';
$string['privacy:metadata:childcourse_map:manualenrolid'] = 'The ID of the enrolment instance used by the plugin to enrol the user.';
$string['privacy:metadata:childcourse_map:parentcourseid'] = 'The ID of the parent course where the activity exists.';
$string['privacy:metadata:childcourse_map:roleid'] = 'The ID of the role assigned by the plugin in the child course.';
$string['privacy:metadata:childcourse_map:timeenrolled'] = 'The time when the user was enrolled through the link.';
$string['privacy:metadata:childcourse_map:timemodified'] = 'The time of the last modification to the mapping record.';
$string['privacy:metadata:childcourse_map:userid'] = 'The ID of the user enrolled through the link.';
$string['privacy:metadata:childcourse_state'] = 'Stores per-user cached state to support incremental grade and completion sync.';
$string['privacy:metadata:childcourse_state:childcourseinstanceid'] = 'The ID of the linked course activity instance.';
$string['privacy:metadata:childcourse_state:coursecompleted'] = 'Cached indicator of whether the completion rule has been satisfied for the user.';
$string['privacy:metadata:childcourse_state:coursecompletiontimemodified'] = 'Timestamp of the last modification of the source completion data for incremental sync.';
$string['privacy:metadata:childcourse_state:finalgrade'] = 'Cached grade (percentage) synced from the child course total.';
$string['privacy:metadata:childcourse_state:grade_source'] = 'Identifier of the grade source (e.g. course_total).';
$string['privacy:metadata:childcourse_state:gradeitemtimemodified'] = 'Timestamp of the last modification of the source grade item for incremental sync.';
$string['privacy:metadata:childcourse_state:timemodified'] = 'The time of the last modification to the cached state row.';
$string['privacy:metadata:childcourse_state:userid'] = 'The user ID.';
$string['privacy:metadata:userpreference:block_myoverview_hidden_course'] = 'A user preference used to hide a child course in My courses (default preference name: block_myoverview_hidden_course_{courseid}).';
$string['settings_heading'] = 'Child course settings';
$string['syncdone'] = 'Sync completed.';
$string['syncnow'] = 'Sync now';
$string['targetgroup'] = 'Enrol into group';
$string['targetgroup_help'] = 'If selected, the user will be added to this specific group in the child course at the time of auto-enrolment. The group must exist in the child course. If "Inherit groups from the parent course" is also enabled, both behaviours apply (the selected group and the inherited groups).';
$string['unenrolaction'] = 'When the link is removed';
$string['unenrolaction_help'] = 'Controls what happens to enrolments created by this activity when the linked activity is deleted. "Unenrol" will remove only the enrolments that were created by this activity (tracked in the mapping table). "Keep enrolments" will leave users enrolled in the child course.';
$string['unenrolaction_keep'] = 'Keep enrolments';
$string['unenrolaction_unenrol'] = 'Unenrol users enrolled by this link';
