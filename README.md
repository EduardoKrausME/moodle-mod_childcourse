# mod_childcourse (Child course)

 **mod_childcourse** adds a Moodle activity of type **"Child course"**: when clicked, the student is redirected to **another course** (the "child course") and, optionally, the plugin performs **automatic enrolment** in the child course and keeps **incremental synchronization** of **grades** and **completion**.

## Main features

* **Link activity** to a child course (a shortcut inside the parent course).
* **Automatic enrolment** in the child course when accessing the activity (optional).
* **Open in a new tab** (optional).
* **Groups**

  * Enrol the user into **a specific group** in the child course (optional).
  * **Inherit groups** from the parent course to the child course (optional).
* **Preserve role** when enrolling (optional).
* When removing the activity, you can choose to:

  * **Unenrol** users who were enrolled via the link, or
  * **Keep** the enrolments (configurable).
* **Hide from "My courses / Overview"** (optional) to reduce visual clutter.
* **Manual sync action** (when the user has permission).
* **Backup/Restore** supported.
* **Moodle App (mobile) support**: renders a simple screen with a button to open the child course.

### Student access

* If **Automatic enrolment** is enabled, when accessing the activity the student will be enrolled in the child course and redirected.
* If it is disabled, the student must already have access to the child course (or they will get a permission error in the child course).

## Activity completion rules

The activity has additional automatic rules (when Moodle completion is set to **Automatic**):

* **None**: the plugin does not automatically control completion.
* **Course completed**: marks the activity as complete when the user completes the **child course**.
* **All activities**: marks the activity as complete when the user completes all trackable activities in the child course (according to the plugin’s logic).

> Note: the child course must have `enablecompletion = 1` for completion-based rules.

## Synchronization (grades and completion)

### Scheduled task

The task `\mod_childcourse\task\sync_task` runs by default every **15 minutes** (see `db/tasks.php`) and performs incremental synchronization for all instances.

### Manual sync

On the activity page (for users with permission), there is a **Sync** button/action that forces an immediate incremental run.

## Mobile support (Moodle App)

In the Moodle App, the activity shows **only a button** to open the child course; clicking it performs the full enrolment and access flow.

## Plugin pages

* `view.php` — main activity page (student is redirected; teacher sees a dashboard)
* `manage.php` — lists/manages instances in the course (requires `moodle/course:update`)
* `index.php` — lists module instances in the course

## Data structure (tables)

* `{childcourse}` — instance settings (includes completion rules and sync timestamps)
* `{childcourse_map}` — tracks enrolments/groups created per instance and user
* `{childcourse_state}` — state cache for incremental synchronization (grades/completion)

## Support

For questions, bugs, improvements, or suggestions:

* GitHub Issues:
  [https://github.com/EduardoKrausME/moodle-mod_childcourse/issues](https://github.com/EduardoKrausME/moodle-mod_childcourse/issues)

* Direct contact:
  [https://eduardokraus.com/contato](https://eduardokraus.com/contato)

When opening a ticket, it really helps to include:

* Moodle version
* steps to reproduce
* affected provider (component + name)
* a template example (without sensitive data)
* cron / task logs (if it’s about digest)