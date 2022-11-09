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
 * @package     local_user_completion
 * @author      Vasileios Sotiras <ptolemy.sotir@googlemail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $ADMIN, $CFG, $USER;

// If you are a site admin you can also have a link to the report in the course menu.
function local_user_completion_extend_navigation_course($navigation, $course)
{
    global $USER;
    if (is_siteadmin($USER)) {
        try {
            $url = new moodle_url('/local/user_completion/index.php', array('id' => $course->id));
            $linktext = get_string('pluginname', 'local_user_completion');
        } catch (moodle_exception $e) {
            $url = "/local/user_completion/index.php?id=$course->id";
            $linktext = 'local_user_completion';
        }
        $navigation->add($linktext, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}
