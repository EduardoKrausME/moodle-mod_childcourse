# Changelog

This changelog documents only the two most recent published GitHub releases. Each entry records verified changes between its release tag and the immediately preceding published release. Changes made later on `master` are excluded.

## 2.1.7 - 2026-10-15

### Changed

- Updated the GitHub Actions CI PostgreSQL service from version 16 to 17.
- Updated the MariaDB CI service from version 10 to 11.4, revising its startup environment variables and health-check command to use `healthcheck.sh --connect --innodb_initialized`.
- Updated the plugin release identifier from `2.1.6` to `2.1.7` and its build number from `2026101200` to `2026101500`.

No functional changes to the Child Course activity were identified in this tag comparison.

[Compare 2.1.6...2.1.7](https://github.com/EduardoKrausME/moodle-mod_childcourse/compare/2.1.6...2.1.7)

## 2.1.6 - 2026-10-12

### Fixed

- Hardened the GitHub Actions release workflow against shell injection by passing the head commit message through a `RELEASE_MESSAGE` environment variable instead of interpolating it directly into the shell script.

### Changed

- Updated the PHP 8.3 CI test entry from Moodle 5.2 (`MOODLE_502_STABLE`) to Moodle 5.3 (`MOODLE_503_STABLE`), retaining the Moodle 4.5 test entry.
- Added a plugin icon at `pix/icon.png`.
- Updated the plugin release identifier from `2.1.5` to `2.1.6` and its build number from `2026100500` to `2026101200`.

No functional changes to the Child Course activity were identified in this tag comparison.

[Compare 2.1.5...2.1.6](https://github.com/EduardoKrausME/moodle-mod_childcourse/compare/2.1.5...2.1.6)
