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
require_once($CFG->dirroot.'/lib/tablelib.php');

/**
 * Class userlist_table
 *
 * @package     tool_selfsignuphardlifecycle
 * @copyright   2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userlist_table extends \table_sql {

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
        list($authinsql, $authsqlparams) = tool_selfsignuphardlifecycle_get_auth_sql();

        // Set the sql for the table.
        $sqlfields = 'id, firstname, lastname, username, email, auth, suspended, timecreated';
        $sqlwhere = 'deleted = :deleted AND auth '.$authinsql;
        $sqlparams = $authsqlparams;
        $sqlparams['deleted'] = 0;
        $this->set_sql($sqlfields, '{user}', $sqlwhere, $sqlparams);

        // Set the table columns.
        $tablecolumns = array('id', 'firstname', 'lastname', 'username', 'email', 'auth', 'timecreated', 'accountstatus',
                'nextstep');
        $this->define_columns($tablecolumns);

        // Allow table sorting.
        $this->sortable(true, 'id', SORT_ASC);
        $this->no_sorting('nextstep');

        // Set the table headers.
        $tableheaders = array(get_string('userid', 'grades'),
                get_string('firstname'),
                get_string('lastname'),
                get_string('username'),
                get_string('email'),
                get_string('col_auth', 'tool_selfsignuphardlifecycle'),
                get_string('col_timecreated', 'tool_selfsignuphardlifecycle'),
                get_string('col_accountstatus', 'tool_selfsignuphardlifecycle'),
                get_string('col_nextstep', 'tool_selfsignuphardlifecycle'));
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
            return strftime(get_string('strftimedate'), $row->timecreated);
        }

        // Inject account status column.
        if ($column === 'accountstatus') {
            return tool_selfsignuphardlifecycle_userlist_get_accountstatus_string($row->suspended);
        }

        // Inject next step column.
        if ($column === 'nextstep') {
            return tool_selfsignuphardlifecycle_userlist_get_nextstep_string($row->suspended, $row->timecreated);
        }

        // Call parent function.
        parent::other_cols($column, $row);
    }
}
