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
 * Admin tool "Hard life cycle for self-signup users" - Local library
 *
 * @package    tool_selfsignuphardlifecycle
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define setting defaults centrally as they might be needed in several places.
define('TOOL_SELFSIGNUPHARDLIFECYCLLE_DELETIONPERIOD_DEFAULT', 200);
define('TOOL_SELFSIGNUPHARDLIFECYCLLE_SUSPENSIONPERIOD_DEFAULT', 100);
define('TOOL_SELFSIGNUPHARDLIFECYCLLE_ENABLESUSPENSION_DEFAULT', 1);


/**
 * Helper function which processes the user life cycle.
 *
 * @return boolean The fact if the whole process was successful.
 *                 If at least one user suspension / deletion failed in a way that another try might fix the problem,
 *                 the value will be false.
 *                 Otherwise, it will be true.
 */
function tool_selfsignuphardlifecycle_process_lifecycle() {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/user/lib.php');

    // Initialize return value.
    $retvalue = true;

    // Get plugin config.
    $config = get_config('tool_selfsignuphardlifecycle');

    // Get SQL snippets for covered auth methods.
    list($authinsql, $authsqlparams) = tool_selfsignuphardlifecycle_get_auth_sql();

    // Calculate the reference date timestamp for the user deletion.
    $userdeletiondatets = tool_selfsignuphardlifecycle_calculate_reference_day($config->userdeletionperiod);

    // If the user suspension is enabled, calculate the reference date for the user suspension.
    if (isset($config->enablesuspension) && $config->enablesuspension == true) {
        $usersuspensiondatets = tool_selfsignuphardlifecycle_calculate_reference_day($config->usersuspensionperiod);
    }

    // If the user suspension is enabled.
    if (isset($config->enablesuspension) && $config->enablesuspension == true) {
        // Prepare the suspended SQL snippet as WHERE clause argument.
        $suspendedsqlsnippet = 'AND suspended = :suspended';

        // Otherwise.
    } else {
        // Prepare the suspended SQL snippet as empty string.
        $suspendedsqlsnippet = '';
    }

    // Get all self-signup users who are older than the reference date and who are (suspended and) not already deleted.
    // The users are fetched as recordset as the number could be really high.
    $deleteusersparams = $authsqlparams;
    $deleteusersparams['timecreated'] = $userdeletiondatets;
    $deleteusersparams['suspended'] = 1;
    $deleteusersparams['deleted'] = 0;
    $deleteuserssql = 'SELECT *
                       FROM {user}
                       WHERE auth '.$authinsql.'
                       AND timecreated < :timecreated '.
                       $suspendedsqlsnippet.'
                       AND deleted = :deleted
                       ORDER BY id ASC';
    $deleteusersrs = $DB->get_recordset_sql($deleteuserssql, $deleteusersparams);

    // Iterate over these users.
    foreach ($deleteusersrs as $user) {
        // Safety net for admins and guest user.
        if (is_siteadmin($user) || isguestuser($user)) {
            continue;
        }

        // Trace.
        mtrace('Deleting user '.fullname($user).' with ID '.$user->id.'...');

        // Delete the user.
        $ret = delete_user($user);

        // If the deletion was successful.
        if ($ret == true) {
            // Trace.
            mtrace('... Success');

            // Log event.
            $logevent = \tool_selfsignuphardlifecycle\event\user_deleted::create(array(
                    'objectid' => $user->id,
                    'relateduserid' => $user->id,
                    'context' => context_user::instance($user->id),
                    'other' => array(
                            'period' => $config->userdeletionperiod
                    )
            ));
            $logevent->trigger();

            // Otherwise.
        } else {
            // Trace.
            mtrace('... Failed');

            // There is no real need to log the failed deletion, but we should flip the return value so that the task
            // is marked as failed and the deletion is tried again within the scheduled task run.
            $retvalue = false;
        }
    }

    // Close the record set.
    $deleteusersrs->close();

    // If the user suspension is enabled.
    if (isset($config->enablesuspension) && $config->enablesuspension == true) {
        // Get all self-signup users who are older than the reference date and who are not suspended and not already deleted.
        // The users are fetched as recordset as the number could be really high.
        $suspendusersparams = $authsqlparams;
        $suspendusersparams['timecreated'] = $usersuspensiondatets;
        $suspendusersparams['suspended'] = 0;
        $suspendusersparams['deleted'] = 0;
        $suspenduserssql = 'SELECT *
                       FROM {user}
                       WHERE auth ' . $authinsql . '
                       AND timecreated < :timecreated
                       AND suspended = :suspended
                       AND deleted = :deleted
                       ORDER BY id ASC';
        $suspendusersrs = $DB->get_recordset_sql($suspenduserssql, $suspendusersparams);

        // Iterate over these users.
        foreach ($suspendusersrs as $user) {
            // Safety net for admins and guest user.
            if (is_siteadmin($user) || isguestuser($user)) {
                continue;
            }

            // Trace.
            mtrace('Suspending user ' . fullname($user) . ' with ID ' . $user->id . '...');

            // Suspend the user.
            $user->suspended = 1;
            \core\session\manager::kill_user_sessions($user->id);
            user_update_user($user, false, true);

            // Verify if the user is suspended.
            $verifyuser = $DB->get_field('user', 'suspended', array('id' => $user->id), MUST_EXIST);

            // If the suspension was successful.
            if ($verifyuser == 1) {
                // Trace.
                mtrace('... Success');

                // Log event.
                $logevent = \tool_selfsignuphardlifecycle\event\user_suspended::create(array(
                        'objectid' => $user->id,
                        'relateduserid' => $user->id,
                        'context' => context_user::instance($user->id),
                        'other' => array(
                                'period' => $config->usersuspensionperiod
                        )
                ));
                $logevent->trigger();

                // Otherwise.
            } else {
                // Trace.
                mtrace('... Failed');

                // There is no real need to log the failed suspension, but we should flip the return value so that the task
                // is marked as failed and the suspension is tried again within the scheduled task run.
                $retvalue = false;
            }
        }

        // Close the record set.
        $suspendusersrs->close();
    }

    // Return.
    return $retvalue;
}

/**
 * Helper function to compose the user account status for the user list table.
 *
 * @param int $suspended The user's suspended value (from the user account record).
 *
 * @return string
 */
function tool_selfsignuphardlifecycle_userlist_get_accountstatus_string($suspended) {

    // If the user is suspended.
    if ($suspended == 1) {
        return get_string('status_suspended', 'tool_selfsignuphardlifecycle');

        // If the user is not suspended.
    } else if ($suspended == 0) {
        return get_string('status_active', 'tool_selfsignuphardlifecycle');

        // Otherwise, if we got some other suspended status (This should not happen).
    } else {
        return get_string('status_unknown', 'tool_selfsignuphardlifecycle');
    }
}

/**
 * Helper function to compose the next step for the user list table.
 *
 * @param int $suspended The user's suspended value (from the user account record).
 * @param int $timecreated The user's timecreated value (from the user account record).
 *
 * @return string
 */
function tool_selfsignuphardlifecycle_userlist_get_nextstep_string($suspended, $timecreated) {

    // Get plugin config.
    $config = get_config('tool_selfsignuphardlifecycle');

    // If the user does not have a real timecreated date (This should not happen).
    if (($timecreated > 1) == false) {
        return get_string('nextstep_unknown', 'tool_selfsignuphardlifecycle');
    }

    // If the user is suspended.
    if ($suspended == 1) {
        $date = tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $config->userdeletionperiod);
        return get_string('nextstep_deletioncomingup', 'tool_selfsignuphardlifecycle', array('date' => $date));

        // If the user is not suspended.
    } else if ($suspended == 0) {

        // If the user suspension is enabled.
        if (isset($config->enablesuspension) && $config->enablesuspension == true) {

            $date = tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $config->usersuspensionperiod);
            return get_string('nextstep_suspensioncomingup', 'tool_selfsignuphardlifecycle', array('date' => $date));

            // Otherwise.
        } else {
            $date = tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $config->userdeletionperiod);
            return get_string('nextstep_deletioncomingup', 'tool_selfsignuphardlifecycle', array('date' => $date));
        }

        // Otherwise, if we got some other suspended status (This should not happen).
    } else {
        return get_string('nextstep_unknown', 'tool_selfsignuphardlifecycle');
    }
}


/**
 * Helper function to calculate the day for the next step.
 *
 * @param int $timecreated The user's timecreated value (from the user account record).
 * @param int $period The configured delay period.
 *
 * @return string The day (as string representation).
 */
function tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $period) {

    // Get the date when the account was created.
    $date = new \DateTime('@'.$timecreated, \core_date::get_server_timezone_object());

    // Advance the date by the configured delay and add one more day (as the registration day is not counted).
    $date->modify('+ '.($period + 1).' days');

    // Compose and return string representation.
    return strftime(get_string('strftimedaydate'), $date->getTimestamp());
}

/**
 * Helper function to calculate the reference day for the next step.
 *
 * @param int $period The configured period.
 *
 * @return int The reference day timestamp.
 */
function tool_selfsignuphardlifecycle_calculate_reference_day($period) {

    // Get the reference day.
    $referencedate = new \DateTime($period.' days ago 00:00', \core_date::get_server_timezone_object());

    // Get the timestamp of the reference day.
    $referencedatets = $referencedate->getTimestamp();

    // Return.
    return $referencedatets;
}

/**
 * Helper function to compose the SQL snippets for the covered auth methods.
 *
 * @return array Array of insql and sqlparams.
 */
function tool_selfsignuphardlifecycle_get_auth_sql() {
    global $DB;

    // Get plugin config.
    $config = get_config('tool_selfsignuphardlifecycle');

    // Explode auth config.
    $coveredauth = explode(',', $config->coveredauth);

    // Return sql snippets for covered auth methods.
    return $DB->get_in_or_equal($coveredauth, SQL_PARAMS_NAMED);
}
