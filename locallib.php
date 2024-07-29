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
define('TOOL_SELFSIGNUPHARDLIFECYCLLE_ENABLEOVERRIDES_DEFAULT', 0);


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

    // PHASE 1: Overridden users.

    // Do only if user override is enabled.
    if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true) {
        // Get all self-signup users who are overridden based on their profile fields and who are not yet deleted.
        // The users are fetched as recordset as the number could be really high.
        $usersparams = $authsqlparams;
        $usersparams['deleted'] = 0;
        $usersparams['deletionoverridefieldid'] = $config->userdeletionoverridefield;
        $usersparams['suspensionoverridefieldid'] = $config->usersuspensionoverridefield;
        $userssql = 'SELECT u.*,
                           (SELECT uid.data
                            FROM {user_info_data} uid
                            WHERE uid.userid = u.id
                            AND uid.fieldid = :deletionoverridefieldid
                           ) AS deletionoverride,
                           (SELECT uid.data
                            FROM {user_info_data} uid
                            WHERE uid.userid = u.id
                            AND uid.fieldid = :suspensionoverridefieldid
                           ) AS suspensionoverride
                       FROM {user} u
                       WHERE u.auth '.$authinsql.'
                       AND u.deleted = :deleted
                       ORDER BY u.id ASC';
        $usersrs = $DB->get_recordset_sql($userssql, $usersparams);

        // Iterate over these users.
        foreach ($usersrs as $user) {
            // Safety net for admins and guest user.
            if (is_siteadmin($user) || isguestuser($user)) {
                continue;
            }

            // If the user suspension is enabled but the user is not yet suspended.
            if (isset($config->enablesuspension) && $config->enablesuspension == true && $user->suspended == false) {
                // If the user should be suspended according to his override date.
                if ($user->suspensionoverride != false && $user->suspensionoverride < time()) {
                    // Trace.
                    mtrace('Suspending user '.fullname($user).' with ID '.$user->id . ' (Suspension period overridden)...');

                    // Suspend the user.
                    $user->suspended = 1;
                    \core\session\manager::kill_user_sessions($user->id);
                    user_update_user($user, false, true);

                    // Verify if the user is suspended.
                    $verifyuser = $DB->get_field('user', 'suspended', ['id' => $user->id], MUST_EXIST);

                    // If the suspension was successful.
                    if ($verifyuser == 1) {
                        // Trace.
                        mtrace('... Success');

                        // Log event.
                        $logevent = \tool_selfsignuphardlifecycle\event\user_suspended::create([
                                'objectid' => $user->id,
                                'relateduserid' => $user->id,
                                'context' => context_user::instance($user->id),
                                'other' => [
                                        'overridden' => true,
                                ],
                        ]);
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

                // Otherwise.
            } else {
                // If the user should be deleted according to his override date.
                if ($user->deletionoverride != false && $user->deletionoverride < time()) {
                    // Trace.
                    mtrace('Deleting user '.fullname($user).' with ID '.$user->id.' (Deletion period overridden)...');

                    // Delete the user.
                    $ret = delete_user($user);

                    // If the deletion was successful.
                    if ($ret == true) {
                        // Trace.
                        mtrace('... Success');

                        // Log event.
                        $logevent = \tool_selfsignuphardlifecycle\event\user_deleted::create([
                                'objectid' => $user->id,
                                'relateduserid' => $user->id,
                                'context' => context_user::instance($user->id),
                                'other' => [
                                        'overridden' => true,
                                ],
                        ]);
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
            }
        }
    }

    // PHASE 2: Standard period users.

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

        // If user overrides are enabled and
        // the user deletion is overridden for this user, skip him as he was handled in Phase 1 already.
        if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true) {
            $useroverrides = tool_selfsignuphardlifecycle_get_user_overrides($user->id);
            if ($useroverrides['deletion'] != false) {
                continue;
            }
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
            $logevent = \tool_selfsignuphardlifecycle\event\user_deleted::create([
                    'objectid' => $user->id,
                    'relateduserid' => $user->id,
                    'context' => context_user::instance($user->id),
                    'other' => [
                            'period' => $config->userdeletionperiod,
                    ],
            ]);
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

            // If user overrides are enabled and
            // the user suspension is overridden for this user, skip him as he was handled in Phase 1 already.
            if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true) {
                $useroverrides = tool_selfsignuphardlifecycle_get_user_overrides($user->id);
                if ($useroverrides['suspension'] != false) {
                    continue;
                }
            }

            // Trace.
            mtrace('Suspending user ' . fullname($user) . ' with ID ' . $user->id . '...');

            // Suspend the user.
            $user->suspended = 1;
            \core\session\manager::kill_user_sessions($user->id);
            user_update_user($user, false, true);

            // Verify if the user is suspended.
            $verifyuser = $DB->get_field('user', 'suspended', ['id' => $user->id], MUST_EXIST);

            // If the suspension was successful.
            if ($verifyuser == 1) {
                // Trace.
                mtrace('... Success');

                // Log event.
                $logevent = \tool_selfsignuphardlifecycle\event\user_suspended::create([
                        'objectid' => $user->id,
                        'relateduserid' => $user->id,
                        'context' => context_user::instance($user->id),
                        'other' => [
                                'period' => $config->usersuspensionperiod,
                        ],
                ]);
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
 * Helper function to compose the user account status for the user list table.
 *
 * @param int $userid The user's ID (from the user account record).
 *
 * @return string
 */
function tool_selfsignuphardlifecycle_userlist_get_accountoverridden_string($userid) {

    // Fallback: If user overrides are not enabled, return no.
    if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == false) {
        return get_string('no');
    }

    // Get the user's overrides.
    $overrides = tool_selfsignuphardlifecycle_get_user_overrides($userid);

    // If the user is overridden.
    if ($overrides['deletion'] != false || $overrides['suspension'] != false) {
        return get_string('yes');

        // If the user is not overridden.
    } else {
        return get_string('no');
    }
}

/**
 * Helper function to compose the next step for the user list table.
 *
 * @param int $userid The user's ID (from the user account record).
 * @param int $suspended The user's suspended value (from the user account record).
 * @param int $timecreated The user's timecreated value (from the user account record).
 *
 * @return string
 */
function tool_selfsignuphardlifecycle_userlist_get_nextstep_string($userid, $suspended, $timecreated) {

    // Get plugin config.
    $config = get_config('tool_selfsignuphardlifecycle');

    // If the user does not have a real timecreated date (This should not happen).
    if (($timecreated > 1) == false) {
        return get_string('nextstep_unknown', 'tool_selfsignuphardlifecycle');
    }

    // If user overrides are enabled.
    if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true) {
        // Get user overrides.
        $useroverrides = tool_selfsignuphardlifecycle_get_user_overrides($userid);
    }

    // If the user is suspended.
    if ($suspended == 1) {
        // If the user deletion is overridden.
        if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true &&
                $useroverrides['deletion'] != false) {
            $date = strftime(get_string('strftimedaydate'), $useroverrides['deletion']);

            // Otherwise.
        } else {
            $date = tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $config->userdeletionperiod);
        }

        // Return string.
        return get_string('nextstep_deletioncomingup', 'tool_selfsignuphardlifecycle', ['date' => $date]);

        // If the user is not suspended.
    } else if ($suspended == 0) {

        // If the user suspension is enabled.
        if (isset($config->enablesuspension) && $config->enablesuspension == true) {
            // If the user suspension is overridden.
            if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true &&
                    $useroverrides['suspension'] != false) {
                $date = strftime(get_string('strftimedaydate'), $useroverrides['suspension']);

                // Otherwise.
            } else {
                $date = tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $config->usersuspensionperiod);
            }

            // Return string.
            return get_string('nextstep_suspensioncomingup', 'tool_selfsignuphardlifecycle', ['date' => $date]);

            // Otherwise.
        } else {
            // If the user deletion is overridden.
            if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == true &&
                    $useroverrides['deletion'] != false) {
                $date = strftime(get_string('strftimedaydate'), $useroverrides['deletion']);

                // Otherwise.
            } else {
                $date = tool_selfsignuphardlifecycle_userlist_calculate_nextstep_date($timecreated, $config->userdeletionperiod);
            }

            // Return string.
            return get_string('nextstep_deletioncomingup', 'tool_selfsignuphardlifecycle', ['date' => $date]);
        }

        // Otherwise, if we got some other suspended status (This should not happen).
    } else {
        return get_string('nextstep_unknown', 'tool_selfsignuphardlifecycle');
    }
}

/**
 * Helper function to compose the user profile string for the user list table.
 *
 * @param int $userid The user's ID (from the user account record).
 *
 * @return string
 */
function tool_selfsignuphardlifecycle_userlist_get_profile_string($userid) {

    // First line: View profile.
    $viewurl = new moodle_url('/user/profile.php', ['id' => $userid]);
    $string = html_writer::link($viewurl, get_string('profileview', 'tool_selfsignuphardlifecycle'));

    // Separator.
    $string .= '&nbsp;&nbsp;|&nbsp;&nbsp;';

    // Second line: Edit profile.
    $editurl = new moodle_url('/user/editadvanced.php', ['id' => $userid]);
    $string .= html_writer::link($editurl, get_string('profileedit', 'tool_selfsignuphardlifecycle'));

    return $string;
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

/**
 * Helper function to get the available date profile fields to be used in the admin settings.
 *
 * @return array Array of profilefields.
 */
function tool_selfsignuphardlifecycle_get_date_profilefield_options() {
    // Get existing custom profile fields.
    $profilefields = profile_get_custom_fields();

    // Prepare options array.
    $options = [];

    // Iterate over the profilefields.
    foreach ($profilefields as $pf) {
        // If this isn't a date field, skip it.
        if ($pf->datatype != 'datetime') {
            continue;
        }

        // Add it to the options array.
        $options[$pf->id] = $pf->name;
    }

    // If we have at least one field, prepend the choose dots.
    if (count($options) > 0) {
        $options = ['' => get_string('choosedots')] + $options;
    }

    // Return the options array.
    return $options;
}

/**
 * Helper function to check if a particular user account is overridden based on his user profile fields.
 *
 * @param int $userid The user's ID (from the user account record).
 *
 * @return array Array of overrides. The array contains two values:
 *               'deletion' = The timestamp of the deletion date, false if not set for this user.
 *               'suspension' = The timestamp of the suspension date, false if not set for this user.
 *               If user overrides are not enabled at all, the values will always be false.
 */
function tool_selfsignuphardlifecycle_get_user_overrides($userid) {
    global $DB;

    // Get plugin config.
    $config = get_config('tool_selfsignuphardlifecycle');

    // Fallback: If user overrides are not enabled, return an all-false array.
    if (tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() == false) {
        return ['deletion' => false, 'suspension' => false];
    }

    // Use a static array to cache the results of this function as it might be called multiple times per user.
    static $staticcache = [];

    // If we did not compose the overrides for the given user yet.
    if (isset($staticcache[$userid]) == false) {
        // Get the user's profile field values (we do this directly as there isn't a proper API function for our needs
        // and as the API does not cache these calls anyway).
        $profilefielddata = $DB->get_records_menu('user_info_data', ['userid' => $userid], '', 'fieldid, data');

        // Prepare overrides array.
        $overrides = [];

        // Add the user's deletion override value.
        if (isset($profilefielddata[$config->userdeletionoverridefield]) &&
                $profilefielddata[$config->userdeletionoverridefield] != 0) {
            $overrides['deletion'] = $profilefielddata[$config->userdeletionoverridefield];
        } else {
            $overrides['deletion'] = false;
        }

        // Add the user's suspension override value.
        if (isset($profilefielddata[$config->usersuspensionoverridefield]) &&
                $profilefielddata[$config->usersuspensionoverridefield] != 0) {
            $overrides['suspension'] = $profilefielddata[$config->usersuspensionoverridefield];
        } else {
            $overrides['suspension'] = false;
        }

        // Remember the results in the cache.
        $staticcache[$userid] = $overrides;

        // Otherwise.
    } else {
        // Just pick the data from the cache.
        $overrides = $staticcache[$userid];
    }

    // Return the overrides array.
    return $overrides;
}

/**
 * Helper function to check if user overrides are enabled and configured (with at least one profile field).
 *
 * @return bool
 */
function tool_selfsignuphardlifecycle_user_overrides_enabled_and_configured() {
    // Get plugin config.
    $config = get_config('tool_selfsignuphardlifecycle');

    // Use a static variable to cache the result of this function as it might be called multiple times per page call.
    static $enabledandconfigured;

    // If we did not check the status yet.
    if (is_bool($enabledandconfigured) == false) {
        // If everything is configured properly.
        if (isset($config->enableuseroverrides) && $config->enableuseroverrides == true &&
                (isset($config->userdeletionoverridefield) &&
                        filter_var($config->userdeletionoverridefield, FILTER_VALIDATE_INT) &&
                        $config->userdeletionoverridefield > 0) ||
                (isset($config->usersuspensionoverridefield) &&
                        filter_var($config->usersuspensionoverridefield, FILTER_VALIDATE_INT) &&
                        $config->usersuspensionoverridefield > 0)) {
            $retvalue = true;

            // Otherwise.
        } else {
            $retvalue = false;
        }

        // Remember the result in the cache.
        $enabledandconfigured = $retvalue;

        // Otherwise.
    } else {
        $retvalue = $enabledandconfigured;
    }

    // Return the result.
    return $retvalue;
}
