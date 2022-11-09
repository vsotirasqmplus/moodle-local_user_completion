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

require_once(dirname(__FILE__, 3) . '/config.php');
global $OUTPUT, $PAGE, $CFG, $USER, $DB;
require_once($CFG->dirroot . '/completion/classes/privacy/provider.php');
require_once($CFG->libdir . '/completionlib.php');
require_once('./locallib.php');

use core_completion\privacy\provider;

try {
    // Prepare page variables.
    $userid = required_param('id', PARAM_INT);
    require_sesskey();
    require_login();
    $context = context_system::instance();
    $url = str_replace($CFG->dirroot, $CFG->wwwroot, __FILE__);
    $title = get_string('pluginname', LOCAL_USER_COMPLETION_STRING);
    $permission = is_siteadmin($USER);

    // Prepare the page metadata.
    $PAGE->set_context($context);
    $PAGE->set_url($url);
    $PAGE->set_title($title);
    if ($permission) {
        $PAGE->navbar->add(get_string('reports'),
            new moodle_url("$CFG->wwwroot/$CFG->admin/search.php#linkreports")
        );
        $PAGE->navbar->add(get_string('selectuser', LOCAL_USER_COMPLETION_STRING),
            new moodle_url(LOCAL_USER_COMPLETION_FOLDER . 'index.php')
        );
    }
    $PAGE->navbar->add($title); // No link to self as session key should not be in a GET request.

    // Check if the user exists and we have permission.
    if ($permission) {
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            throw new moodle_exception(get_string('cannotfinduser', 'error', (string)$userid));
        }
        $fullname = fullname($user);
        $coursefields = ['id', 'fullname', 'enablecompletion'];
        $courses = enrol_get_all_users_courses($userid, false, $coursefields, 'fullname');

        // Filter out in tracked courses.
        $trackedcourses = [];
        foreach ($courses as $key => $course) {
            if ($course->enablecompletion) {
                // Add the user completion here.
                $completion = provider::get_course_completion_info($user, $course);
                // We need the time stamp of the course completion.
                $completiondatetime = $DB->get_field('course_completions',
                    'timecompleted',
                    [
                        'userid' => $userid,
                        'course' => $course->id
                    ]
                );
                $tracked = [
                    'course_name' => html_writer::link(
                        (new moodle_url($CFG->wwwroot . '/course/view.php', ['id' => $course->id]))->__toString(),
                        local_user_completion_ellipse_string($course->fullname, 40),
                        [
                            'class' => 'btn btn-primary',
                            'style' => 'border-radius: 0.25em;'
                        ]
                    ),
                    'completion_status' => $completion['status'],
                    'completion_datetime' => $completiondatetime ? userdate($completiondatetime,
                        get_string('strftimedatetime', 'langconfig')
                    ) : '-',
                    'course_criteria' => $completion['criteria'],
                ];
                $trackedcourses[] = $tracked;
            }
        }

        // Prepare template data.
        $data['permission'] = $permission;
        $data['courses'] = $trackedcourses;
        $data['title'] = $title;
        $data['fullname'] = $fullname;
        $first = reset($trackedcourses);
        if ($first) {
            $headers = array_keys($first);
            foreach ($headers as $header) {
                $data['headers'][] = ['name' => str_replace('_', ' ', $header)];
            }
        }

        // Show the template.
        $content = $OUTPUT->render_from_template('local_user_completion/report', (object)$data);

    } else {
        // No permission.
        $content = get_string('nopermission', LOCAL_USER_COMPLETION_STRING);
    }
} catch (dml_exception|coding_exception|moodle_exception $e) {
    // Any error should be presented.
    $content = $e->getMessage() . "\n" . $e->getTraceAsString();
    mtrace($content);
}

// Show the results of this request.
echo $OUTPUT->header();
echo $content;
echo $OUTPUT->footer();