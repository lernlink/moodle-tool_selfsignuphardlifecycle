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
 * Admin tool "Hard life cycle for self-signup users" - Settings
 *
 * @package    tool_selfsignuphardlifecycle
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

if ($hassiteconfig) {
    // Add new category to site admin navigation tree.
    $ADMIN->add('users', new admin_category('tool_selfsignuphardlifecycle',
            get_string('pluginname', 'tool_selfsignuphardlifecycle', null, true)));

    // Create settings page.
    $page = new admin_settingpage('tool_selfsignuphardlifecycle_settings',
            get_string('settings', 'core', null, true));

    if ($ADMIN->fulltree) {
        // Require the necessary libraries.
        require_once($CFG->dirroot . '/admin/tool/selfsignuphardlifecycle/locallib.php');

        // Create hard life cycle description static widget.
        $setting = new admin_setting_heading('tool_selfsignuphardlifecycle/userlifecyclestatic',
                '',
                get_string('setting_userlifecyclestatic_desc', 'tool_selfsignuphardlifecycle', null, true));
        $page->add($setting);

        // Create auth methods heading widget.
        $setting = new admin_setting_heading('tool_selfsignuphardlifecycle/authmethodsheading',
                get_string('setting_authmethodsheading', 'tool_selfsignuphardlifecycle', null, true),
                '');
        $page->add($setting);

        // Create auth method widget.
        $auths = core_component::get_plugin_list('auth');
        $authoptions = array();
        if (!empty($auths)) {
            foreach ($auths as $auth => $unused) {
                if (is_enabled_auth($auth)) {
                    $authoptions[$auth] = get_string('pluginname', "auth_{$auth}");
                }
            }
        }
        $setting = new admin_setting_configmultiselect('tool_selfsignuphardlifecycle/coveredauth',
                        get_string('setting_coveredauth', 'tool_selfsignuphardlifecycle', null, true),
                        get_string('setting_coveredauth_desc', 'tool_selfsignuphardlifecycle', null, true),
                        array(),
                        $authoptions);
        $page->add($setting);
        unset($auths, $authoptions);

        // Create user deletion heading widget.
        $setting = new admin_setting_heading('tool_selfsignuphardlifecycle/userdeletionheading',
                get_string('setting_userdeletionheading', 'tool_selfsignuphardlifecycle', null, true),
                '');
        $page->add($setting);

        // Create user deletion period widget.
        $setting = new admin_setting_configtext('tool_selfsignuphardlifecycle/userdeletionperiod',
                get_string('setting_userdeletionperiod', 'tool_selfsignuphardlifecycle', null, true),
                get_string('setting_userdeletionperiod_desc', 'tool_selfsignuphardlifecycle', null, true).'<br /><br />'.
                get_string('setting_userperiodscalc_desc', 'tool_selfsignuphardlifecycle', null, true),
                TOOL_SELFSIGNUPHARDLIFECYCLLE_DELETIONPERIOD_DEFAULT, PARAM_INT);
        $page->add($setting);

        // Create user suspension heading widget.
        $setting = new admin_setting_heading('tool_selfsignuphardlifecycle/usersuspensionheading',
                get_string('setting_usersuspensionheading', 'tool_selfsignuphardlifecycle', null, true),
                '');
        $page->add($setting);

        // Create enable user suspension widget.
        $setting = new admin_setting_configcheckbox('tool_selfsignuphardlifecycle/enableusersuspension',
                get_string('setting_enableusersuspension', 'tool_selfsignuphardlifecycle', null, true),
                get_string('setting_enableusersuspension_desc', 'tool_selfsignuphardlifecycle', null, true),
                TOOL_SELFSIGNUPHARDLIFECYCLLE_ENABLESUSPENSION_DEFAULT);
        $page->add($setting);

        // Create user suspension period widget.
        $setting = new admin_setting_configtext('tool_selfsignuphardlifecycle/usersuspensionperiod',
                get_string('setting_usersuspensionperiod', 'tool_selfsignuphardlifecycle', null, true),
                get_string('setting_usersuspensionperiod_desc', 'tool_selfsignuphardlifecycle', null, true).'<br /><br />'.
                        get_string('setting_userperiodscalc_desc', 'tool_selfsignuphardlifecycle', null, true).'<br /><br />'.
                        get_string('setting_userperiodsrelation_desc', 'tool_selfsignuphardlifecycle', null, true),
                TOOL_SELFSIGNUPHARDLIFECYCLLE_SUSPENSIONPERIOD_DEFAULT, PARAM_INT);
        $page->add($setting);
        $page->hide_if('tool_selfsignuphardlifecycle/usersuspensionperiod', 'tool_selfsignuphardlifecycle/enableusersuspension');

        // Create user overrides heading widget.
        $setting = new admin_setting_heading('tool_selfsignuphardlifecycle/useroverridesheading',
                get_string('setting_useroverridesheading', 'tool_selfsignuphardlifecycle', null, true),
                '');
        $page->add($setting);

        // Create enable user overrides widget.
        $setting = new admin_setting_configcheckbox('tool_selfsignuphardlifecycle/enableuseroverrides',
                get_string('setting_enableuseroverrides', 'tool_selfsignuphardlifecycle', null, true),
                get_string('setting_enableuseroverrides_desc', 'tool_selfsignuphardlifecycle', null, true),
                TOOL_SELFSIGNUPHARDLIFECYCLLE_ENABLEOVERRIDES_DEFAULT);
        $page->add($setting);

        // Get custom user profile fields options.
        $userprofilefieldoptions = tool_selfsignuphardlifecycle_get_date_profilefield_options();

        // If there aren't any custom user profile fields.
        if (count($userprofilefieldoptions) < 1) {
            // Build settings page link.
            $url = new moodle_url('/user/profile/index.php');
            $link = array('url' => $url->out(),
                    'linktitle' => get_string('profilefields', 'admin', null, true),
                    'fieldname' => get_string('pluginname', 'profilefield_datetime', null, true));

            // Create empty user deletion override field widget to trigger a settings entry in the database.
            $setting = new admin_setting_configempty('tool_selfsignuphardlifecycle/userdeletionoverridefield',
                    get_string('setting_userdeletionoverridefield', 'tool_selfsignuphardlifecycle', null, true),
                    get_string('setting_useroverridesnofieldyet_desc', 'tool_selfsignuphardlifecycle', $link, true));
            $page->add($setting);
            $page->hide_if('tool_selfsignuphardlifecycle/userdeletionoverridefield',
                    'tool_selfsignuphardlifecycle/enableuseroverrides');

            // Create empty user suspension override field widget to trigger a settings entry in the database.
            $setting = new admin_setting_configempty('tool_selfsignuphardlifecycle/usersuspensionoverridefield',
                    get_string('setting_usersuspensionoverridefield', 'tool_selfsignuphardlifecycle', null, true),
                    get_string('setting_useroverridesnofieldyet_desc', 'tool_selfsignuphardlifecycle', $link, true));
            $page->add($setting);
            $page->hide_if('tool_selfsignuphardlifecycle/usersuspensionoverridefield',
                    'tool_selfsignuphardlifecycle/enableuseroverrides');
            $page->hide_if('tool_selfsignuphardlifecycle/usersuspensionoverridefield',
                    'tool_selfsignuphardlifecycle/enableusersuspension');

            unset ($link, $url);

            // Otherwise, if there are fields.
        } else {
            // Create user deletion override field widget.
            $setting = new admin_setting_configselect('tool_selfsignuphardlifecycle/userdeletionoverridefield',
                    get_string('setting_userdeletionoverridefield', 'tool_selfsignuphardlifecycle', null, true),
                    get_string('setting_userdeletionoverridefield_desc', 'tool_selfsignuphardlifecycle', null, true),
                    '',
                    $userprofilefieldoptions);
            $page->add($setting);
            $page->hide_if('tool_selfsignuphardlifecycle/userdeletionoverridefield',
                    'tool_selfsignuphardlifecycle/enableuseroverrides');

            // Create user suspension override field widget.
            $setting = new admin_setting_configselect('tool_selfsignuphardlifecycle/usersuspensionoverridefield',
                    get_string('setting_usersuspensionoverridefield', 'tool_selfsignuphardlifecycle', null, true),
                    get_string('setting_usersuspensionoverridefield_desc', 'tool_selfsignuphardlifecycle', null, true).
                            '<br /><br />'.
                            get_string('setting_useroverridesrelation_desc', 'tool_selfsignuphardlifecycle', null, true),
                    '',
                    $userprofilefieldoptions);
            $page->add($setting);
            $page->hide_if('tool_selfsignuphardlifecycle/usersuspensionoverridefield',
                    'tool_selfsignuphardlifecycle/enableuseroverrides');
            $page->hide_if('tool_selfsignuphardlifecycle/usersuspensionoverridefield',
                    'tool_selfsignuphardlifecycle/enableusersuspension');
        }
        unset($userprofilefieldoptions);
    }

    // Add settings page to navigation category.
    $ADMIN->add('tool_selfsignuphardlifecycle', $page);

    // Create new external userlist page.
    $page = new admin_externalpage('tool_selfsignuphardlifecycle_userlist',
            get_string('settingsuserlist', 'tool_selfsignuphardlifecycle', null, true),
            new moodle_url('/admin/tool/selfsignuphardlifecycle/settings_userlist.php'));

    // Add pagelist page to navigation category.
    $ADMIN->add('tool_selfsignuphardlifecycle', $page);
}
