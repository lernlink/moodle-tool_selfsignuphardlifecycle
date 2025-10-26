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
 * Admin tool "Hard life cycle for self-signup users" - User list class
 *
 * @package    tool_selfsignuphardlifecycle
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_selfsignuphardlifecycle;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/tablelib.php');

/**
 * Class userlist_table
 *
 * @package     tool_selfsignuphardlifecycle
 * @copyright   2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist_table extends \core_table\sql_table {
    /**
     * Override the constructor to construct a userlist table instead of a simple table.
     *
     * @param string $uniqueid a string identifying this table. Used as a key in session vars.
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        // Do not allow downloading.
        $this->is_downloadable(false);

        // Get SQL snippets for covered auth methods.
        [$authinsql, $authsqlparams] = tool_selfsignuphardlifecycle_get_auth_sql();

        // Get SQL snippets for excludings admins and guests.
        [$admininsql, $adminsqlparams] = tool_selfsignuphardlifecycle_get_adminandguest_sql();

        // Get SQL subquery for ignoring cohorts.
        [$cohortexceptionswhere, $cohortexceptionsparams] =
                tool_selfsignuphardlifecycle_get_cohort_exceptions_sql();

        // Get plugin config.
        $config = get_config('tool_selfsignuphardlifecycle');

        // Set the sql for the table.
        $sqlfields = 'u.id, u.firstname, u.lastname, u.username, u.email, u.auth, u.suspended, u.timecreated';
        $sqlfrom = '{user} u';
        $sqlwhere = 'u.deleted = :deleted AND u.auth ' . $authinsql . ' AND u.id ' . $admininsql . ' ' . $cohortexceptionswhere;
        $sqlparams = array_merge($authsqlparams, $adminsqlparams, $cohortexceptionsparams);
        $sqlparams['deleted'] = 0;
        $this->set_sql($sqlfields, $sqlfrom, $sqlwhere, $sqlparams);

        // Set the table columns (depending if user overrides are enabled or not).
        if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true) {
            $tablecolumns = ['id', 'firstname', 'lastname', 'username', 'email', 'auth', 'timecreated',
                    'accountstatus', 'accountoverridden', 'nextstep', 'profile'];
        } else {
            $tablecolumns = ['id', 'firstname', 'lastname', 'username', 'email', 'auth', 'timecreated',
                    'accountstatus', 'nextstep', 'profile'];
        }
        $this->define_columns($tablecolumns);

        // Allow table sorting.
        $this->sortable(true, 'id', SORT_ASC);
        $this->no_sorting('nextstep');
        $this->no_sorting('profile');

        // Set the table headers (depending if user overrides are enabled or not).
        if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true) {
            $tableheaders = [get_string('userid', 'grades'),
                    get_string('firstname'),
                    get_string('lastname'),
                    get_string('username'),
                    get_string('email'),
                    get_string('col_auth', 'tool_selfsignuphardlifecycle'),
                    get_string('col_timecreated', 'tool_selfsignuphardlifecycle'),
                    get_string('col_accountstatus', 'tool_selfsignuphardlifecycle'),
                    get_string('col_accountoverridden', 'tool_selfsignuphardlifecycle'),
                    get_string('col_nextstep', 'tool_selfsignuphardlifecycle'),
                    get_string('col_profile', 'tool_selfsignuphardlifecycle')];
        } else {
            $tableheaders = [get_string('userid', 'grades'),
                    get_string('firstname'),
                    get_string('lastname'),
                    get_string('username'),
                    get_string('email'),
                    get_string('col_auth', 'tool_selfsignuphardlifecycle'),
                    get_string('col_timecreated', 'tool_selfsignuphardlifecycle'),
                    get_string('col_accountstatus', 'tool_selfsignuphardlifecycle'),
                    get_string('col_nextstep', 'tool_selfsignuphardlifecycle'),
                    get_string('col_profile', 'tool_selfsignuphardlifecycle')];
        }
        $this->define_headers($tableheaders);
    }

    /**
     * Override the other_cols function to inject content into columns which does not come directly from the database.
     *
     * @param string $column The column name.
     * @param stdClass $row The submission row.
     *
     * @return mixed string or null.
     */
    public function other_cols($column, $row) {

        // Inject auth column.
        if ($column === 'auth') {
            return get_string('pluginname', "auth_{$row->auth}");
        }

        // Inject timecreated column.
        if ($column === 'timecreated') {
            return \core_date::strftime(get_string('strftimedate'), (int) $row->timecreated);
        }

        // Inject account status column.
        if ($column === 'accountstatus') {
            return tool_selfsignuphardlifecycle_userlist_get_accountstatus_string($row->suspended);
        }

        // Inject account overridden column.
        if ($column === 'accountoverridden') {
            return tool_selfsignuphardlifecycle_userlist_get_accountoverridden_string($row->id);
        }

        // Inject next step column.
        if ($column === 'nextstep') {
            return tool_selfsignuphardlifecycle_userlist_get_nextstep_string(
                $row->id,
                $row->suspended,
                $row->timecreated
            );
        }

        // Inject profile column.
        if ($column === 'profile') {
            return tool_selfsignuphardlifecycle_userlist_get_profile_string($row->id);
        }

        // Call parent function.
        parent::other_cols($column, $row);
    }

    /**
     * This function is not part of the public api.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        // Render the dynamic table header.
        echo $this->get_dynamic_table_html_start();

        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();

        $this->print_initials_bar();

        echo $OUTPUT->notification(get_string('emptytable', 'tool_selfsignuphardlifecycle'), 'info');

        // Render the dynamic table footer.
        echo $this->get_dynamic_table_html_end();
    }
}
