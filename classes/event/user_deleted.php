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
 * Admin tool "Hard life cycle for self-signup users" - Event definition
 *
 * @package    tool_selfsignuphardlifecycle
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_selfsignuphardlifecycle\event;

/**
 * The tool_selfsignuphardlifecycle user deleted event class.
 *
 * @package    tool_selfsignuphardlifecycle
 * @copyright  2022 Alexander Bias, lern.link GmbH <alexander.bias@lernlink.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_deleted extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'user';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventuserdeleted', 'tool_selfsignuphardlifecycle');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if (isset($this->other['overridden']) && $this->other['overridden'] == true) {
            return get_string(
                'eventuserdeletedoverridden_desc',
                'tool_selfsignuphardlifecycle',
                ['userid' => $this->relateduserid]
            );
        } else {
            return get_string('eventuserdeleted_desc', 'tool_selfsignuphardlifecycle', ['userid' => $this->relateduserid,
                    'period' => $this->other['period']]);
        }
    }

    /**
     * Returns relevant URL.
     *
     * @return \core\url
     */
    public function get_url() {
        return new \core\url('/user/view.php', ['id' => $this->relateduserid]);
    }
}
