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

defined('MOODLE_INTERNAL') || die;
global $ADMIN, $CFG, $hassiteconfig, $USER;

if ($hassiteconfig) {
    try {
        $linktext = get_string('pluginname', 'local_user_completion');
    } catch (coding_exception $e) {
        $linktext = 'User Completion';
    }
    // You are a site admin and the report link is going to be in the site admin reports.
    $ADMIN->add('reports', new admin_externalpage('local_user_completion',
        $linktext,
        "$CFG->wwwroot/local/user_completion/index.php", 'moodle/site:config'));
}