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


use core_user\fields;

global $OUTPUT, $PAGE, $USER, $CFG, $DB;
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('./locallib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/filters/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (!is_siteadmin($USER)) {
    throw new moodle_exception(
        get_string('nopermission', LOCAL_USER_COMPLETION_STRING)
    );
}


try {
// Prepare script data.
    $context = context_system::instance();
    $title = get_string('pluginname', LOCAL_USER_COMPLETION_STRING);
    $file = str_replace($CFG->dirroot, $CFG->wwwroot, __FILE__);
    $url = new moodle_url($file);
    $content = '';

// Prepare data for the page template.
    $data = new stdClass();
    $data->permission = is_siteadmin($USER);
    $data->title = $title;

// Prepare Page meta data.
    $PAGE->set_context($context);
    $PAGE->set_title($title);
    $PAGE->set_url($url);
    if ($data->permission) {
        $PAGE->navbar->add(
            get_string('reports'),
            new moodle_url("$CFG->wwwroot/$CFG->admin/search.php#linkreports")
        );
    }
    $PAGE->navbar->add($title, $data->permission ? $url : null);

    $sort = optional_param('sort', 'name', PARAM_ALPHANUM);
    $dir = optional_param('dir', 'ASC', PARAM_ALPHA);
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', 30, PARAM_INT);        // how many per page

} catch (moodle_exception|coding_exception $e) {
    $sort = 'name';
    $dir = 'ASC';
    $page = 0;
    $perpage = 30;
    $content = $e->getMessage() . "\n" . $e->getTraceAsString();
    mtrace($content);
}

// Create the user filter form.
$ufiltering = new user_filtering();

echo $OUTPUT->header();

try {
    // Carry on with the user listing
    $context = context_system::instance();
    // These columns are always shown in the users list.
    $requiredcolumns = ['city', 'country', 'lastaccess'];
    // Extra columns containing the extra user fields, excluding the required columns (city and country, to be specific).
    $extracolumns = (fields::for_identity($context, false)->excluding(...$requiredcolumns))->get_required_fields();
    // Get all user name fields as an array.
    $allusernamefields = local_user_completion_moodle4_get_all_user_name_fields(false, null, null, null, true);
    $columns = array_merge($allusernamefields, $extracolumns, $requiredcolumns);

    $fields = local_user_completion_table_fields($columns, $sort, $dir);

    // We need to check that alternativefullnameformat is not set to '' or language.
    // We don't need to check the fullnamedisplay setting here as the fullname function call further down has
    // the override parameter set to true.
    $fullnamesetting = $CFG->alternativefullnameformat;
    // If we are using language, or it is empty, then retrieve the default user names of just 'firstname' and 'lastname'.
    if ($fullnamesetting == 'language' || empty($fullnamesetting)) {
        // Set $a variables to return 'firstname' and 'lastname'.
        $a = new stdClass();
        $a->firstname = 'firstname';
        $a->lastname = 'lastname';
        // Getting the fullname display will ensure that the order in the language file is maintained.
        $fullnamesetting = get_string('fullnamedisplay', null, $a);
    }

    // Order in string will ensure that the name columns are in the correct order.
    $usernames = order_in_string($allusernamefields, $fullnamesetting);
    $fullnamedisplay = [];
    foreach ($usernames as $name) {
        // Use the link from $$column for sorting on the user's name.
        $fullnamedisplay[] = $fields[$name];
    }
    // All the names are in one column. Put them into a string and separate them with a /.
    $fullnamedisplay = implode(' / ', $fullnamedisplay);
    // If $sort = name then it is the default for the setting, and we should use the first name to sort by.
    if ($sort == "name") {
        // Use the first item in the array.
        $sort = reset($usernames);
    }

    list($extrasql, $params) = $ufiltering->get_sql_filter();
    $users = get_users_listing(
        $sort,
        $dir,
        $page * $perpage,
        $perpage,
        '',
        '',
        '',
        $extrasql,
        $params,
        $context
    );
    $usercount = get_users(false);
    $usersearchcount = get_users(
        false,
        '',
        false,
        null,
        "",
        '',
        '',
        '',
        '',
        '*',
        $extrasql,
        $params
    );

    if ($extrasql !== '') {
        $content .= $OUTPUT->heading("$usersearchcount / $usercount " . get_string('users'));
        $usercount = $usersearchcount;
    } else {
        $content .= $OUTPUT->heading("$usercount " . get_string('users'));
    }

    $strall = get_string('all');

    $baseurl = new moodle_url('/local/user_completion/index.php',
        ['sort' => $sort, 'dir' => $dir, 'perpage' => $perpage]
    );
    $content .= $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);

    flush();

    if (!$users) {
        $match = array();
        $content .= $OUTPUT->heading(get_string('nousersfound'));
        $table = NULL;

    } else {
        $countries = get_string_manager()->get_list_of_countries(true);
        foreach ($users as $key => $user) {
            if (isset($countries[$user->country])) {
                $user->country = $countries[$user->country];
            }
        }
        if ($sort == "country") {
            // Need to resort by full country name, not code.
            foreach ($users as $user) {
                $susers[$user->id] = $user->country;
            }
            // Sort by country name, according to $dir.
            if ($dir === 'DESC') {
                arsort($susers);
            } else {
                asort($susers);
            }
            $nusers = [];
            foreach ($susers as $key => $value) {
                $nusers[] = $users[$key];
            }
            $users = $nusers;
        }

        $data = (object)[
            'fullnamedisplay' => $fullnamedisplay,
            'extracolumns' => $extracolumns,
            'users' => $users,
            'fields' => $fields,
        ];

        $table = local_user_completion_table($data);
    }

    // Add filters.
    $ufiltering->display_add();
    $ufiltering->display_active();

    // Show the data table.
    if (!empty($table)) {
        $content .= html_writer::start_tag('div', array('class' => 'no-overflow'));
        $content .= html_writer::table($table);
        $content .= html_writer::end_tag('div');
        $content .= $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);
    }

    $content .= <<<STYLE
<style>
 #page-content .btn-primary,
 #page-content .page-link {
   border-radius: 0.25em;
 }
</style>
STYLE;
} catch (dml_exception|coding_exception|moodle_exception $e) {
    $content = $e->getMessage() . "\n" . $e->getTraceAsString();
    mtrace($content);
}
echo $content;
echo $OUTPUT->footer();
