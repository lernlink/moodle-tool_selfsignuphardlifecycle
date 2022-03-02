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
 * Admin tool "Hard life cycle for self-signup users" - User list
 *
 * @package    tool_selfsignuphardlifecycle
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_selfsignuphardlifecycle\userlist_table;

// Include config.php.
require(__DIR__ . '/../../../config.php');

// Globals.
global $CFG, $PAGE, $OUTPUT;

// Include adminlib.php.
require_once($CFG->libdir.'/adminlib.php');

// Include tablelib.php.
require_once($CFG->libdir.'/tablelib.php');

// Include locallib.php.
require_once($CFG->dirroot.'/admin/tool/selfsignuphardlifecycle/locallib.php');

// Set up external admin page.
admin_externalpage_setup('tool_selfsignuphardlifecycle_userlist');


// Prepare page.
$title = get_string('settingsuserlist', 'tool_selfsignuphardlifecycle');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/admin/tool/selfsignuphardlifecycle/settings_userlist.php');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Prepare table.
$table = new userlist_table('selfsignuphardlifecycle_userlist');
$table->define_baseurl($CFG->wwwroot.'/admin/tool/selfsignuphardlifecycle/settings_userlist.php');

// Output table.
$table->out(50, true);

// Finish page.
echo $OUTPUT->footer();
