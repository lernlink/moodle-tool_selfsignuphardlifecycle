moodle-tool_selfsignuphardlifecycle
===================================

Changes
-------

### Unreleased

* 2025-10-26 - Tests: Switch Github actions workflows to reusable workflows by Moodle an Hochschulen e.V.

### v4.5-r1

* 2025-03-20 - Development: Rename master branch to main, please update your clones.
* 2025-03-17 - Upgrade: Adopt changes from MDL-82183 and use new \core_table\sql_table.
* 2025-03-17 - Upgrade: Adopt changes from MDL-82183 and use new \core\output\html_writer.
* 2025-03-17 - Upgrade: Adopt changes from MDL-81960 and use new \core\url class.
* 2025-03-17 - Upgrade: Adopt changes from MDL-66903 and use new \core\component class.
* 2025-03-17 - Prepare compatibility for Moodle 4.5.

### v4.4-r2

* 2025-03-18 - Bugfix: "Enable user suspension before deletion" feature was partly broken, resolves #2.

### v4.4-r1

* 2025-03-17 - Bugfix: Behat Scenario 'If user overrides is enabled, user suspension and deletion days can be overridden' always failed on first try, resolves #1.
* 2025-03-17 - Upgrade: Fix Behat tests which broke on 4.4.
* 2025-03-17 - Upgrade: Make the scheduled task non-blocking as this has been deprecated in Moodle core.
* 2025-03-17 - Prepare compatibility for Moodle 4.4.

### v4.3-r3

* 2024-09-09 - Bugfix: The cohort exceptions feature was not working correctly on MariaDB.

### v4.3-r2

* 2024-07-30 - Feature: Allow the admin to configure cohorts which should be ignored by the tool.

### v4.3-r1

* 2024-07-28 - Prepare compatibility for Moodle 4.3.

### v4.2-r1

* 2024-07-28 - Prepare compatibility for Moodle 4.2.

### v4.1-r1

* 2024-07-28 - Upgrade: Fix a Behat test which broke von Moodle 4.1.
* 2024-07-28 - Cleanup: Replace deprecated strftime() function with \core_date::strftime() function.
* 2024-07-28 - Prepare compatibility for Moodle 4.1.

### v3.9-r3

* 2024-07-29 - Add automated release to moodle.org/plugins
* 2024-07-29 - Improvement: Exclude admins and guests from the user table.
* 2024-07-29 - Make codechecker happy again
* 2024-07-29 - Updated Moodle Plugin CI to latest upstream recommendations

### v3.9-r2

* 2022-04-28 - Feature: Add possibility to override individual users.

### v3.9-r1

* 2022-03-01 - Initial version.
