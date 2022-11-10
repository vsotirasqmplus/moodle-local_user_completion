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

defined('MOODLE_INTERNAL') || die();

const LOCAL_USER_COMPLETION_STRING = 'local_user_completion';
const LOCAL_USER_COMPLETION_FOLDER = '/local/user_completion/';


/**
 * Create a post link to hide from the URL session keys.
 *
 * @param string $url
 * @param string $text
 * @param array $params
 * @return string
 * @throws moodle_exception
 */
function local_user_completion_link(string $url, string $text, array $params): string
{
    global $CFG;
    $url = new moodle_url($CFG->wwwroot . $url);
    $time = time();

    $elems = '<input type="hidden" name="sesskey" value="' . sesskey() . '" >';
    foreach ($params as $name => $value) {
        $elems .= "<input type='hidden' name='$name' value='$value' >";
    }
    return <<<LINK
<form id="link$time" style="display: initial" action="$url" method="post" >
$elems
<input class="btn btn-primary" style="border-radius: 0.25em;font-size: larger;" type="submit" value="$text">
</form>
LINK;
}

/**
 * Return a maximum length string with ellipsis ending for long ones.
 *
 * @param string $string
 * @param int $length
 * @return string
 */
function local_user_completion_ellipse_string(string $string, int $length): string
{
    $length = max(0, $length);
    $size = strlen($string);
    if ($size > $length) {
        $string = substr($string, 0, $length - 1) . '&mldr;';
    } else {
        $string = $string . str_repeat(' ', $length - $size);
    }
    return $string;
}


/**
 * Make a table of users for the index page.
 *
 * @param object $data
 * @return html_table
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_user_completion_table(object $data): html_table
{
    $table = new html_table();
    $table->head = [];
    $table->colclasses = [];
    $table->head[] = $data->fullnamedisplay;
    $table->attributes['class'] = 'admintable generaltable table-sm';
    foreach ($data->extracolumns as $field) {
        $table->head[] = $data->fields[$field];
    }

    $table->head[] = $data->fields['city'];
    $table->head[] = $data->fields['country'];
    $table->head[] = $data->fields['lastaccess'];
    $table->id = "users";
    foreach ($data->users as $user) {

        $strlastaccess = ($user->lastaccess) ? format_time(time() - $user->lastaccess) : get_string('never');
        $row = [];
        $fullname = fullname($user, true);
        $row[] = local_user_completion_link(LOCAL_USER_COMPLETION_FOLDER . 'report.php',
            $fullname,
            ['id' => $user->id]
        );

        foreach ($data->extracolumns as $field) {
            $row[] = s($user->{$field});
        }

        $row[] = $user->city;
        $row[] = $user->country;
        $row[] = $strlastaccess;
        if ($user->suspended) {
            foreach ($row as $k => $v) {
                $row[$k] = html_writer::tag('span', $v, array('class' => 'usersuspended'));
            }
        }
        $table->data[] = $row;
    }
    return $table;
}

/**
 * Make the header fields for sorting links.
 *
 * @param array $columns
 * @param string $sort
 * @param string $dir
 * @return array
 * @throws coding_exception
 */
function local_user_completion_table_fields(array $columns, string $sort, string $dir): array
{
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot .'/user/classes/fields.php');
    $fields = [];
    foreach ($columns as $column) {
        # $string[$column] = get_user_field_name($column);
        $string[$column] = fields::get_display_name($column);
        if ($sort != $column) {
            $columnicon = "";
            if ($column == "lastaccess") {
                $columndir = "DESC";
            } else {
                $columndir = "ASC";
            }
        } else {
            $columndir = $dir == "ASC" ? "DESC" : "ASC";
            if ($column == "lastaccess") {
                $columnicon = ($dir == "ASC") ? "sort_desc" : "sort_asc";
            } else {
                $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
            }
            $columnicon = $OUTPUT->pix_icon(
                't/' . $columnicon, get_string(strtolower($columndir)),
                'core',
                ['class' => 'iconsort']
            );

        }
        $fields[$column] = "<a href='index.php?sort=$column&amp;dir=$columndir'>" . $string[$column] . "</a>$columnicon";
    }
    return $fields;
}


function local_user_completion_moodle4_get_all_user_name_fields(
        $returnsql = false,
        $tableprefix = null,
        $prefix = null,
        $fieldprefix = null,
        $order = false){
    $alternatenames = [];
    foreach (fields::get_name_fields() as $field) {
        $alternatenames[$field] = $field;
    }

    // Let's add a prefix to the array of user name fields if provided.
    if ($prefix) {
        foreach ($alternatenames as $key => $altname) {
            $alternatenames[$key] = $prefix . $altname;
        }
    }

    // If we want the end result to have firstname and lastname at the front / top of the result.
    if ($order) {
        // Move the last two elements (firstname, lastname) off the array and put them at the top.
        for ($i = 0; $i < 2; $i++) {
            // Get the last element.
            $lastelement = end($alternatenames);
            // Remove it from the array.
            unset($alternatenames[$lastelement]);
            // Put the element back on the top of the array.
            $alternatenames = array_merge(array($lastelement => $lastelement), $alternatenames);
        }
    }

    // Create an sql field snippet if requested.
    if ($returnsql) {
        if ($tableprefix) {
            if ($fieldprefix) {
                foreach ($alternatenames as $key => $altname) {
                    $alternatenames[$key] = $tableprefix . '.' . $altname . ' AS ' . $fieldprefix . $altname;
                }
            } else {
                foreach ($alternatenames as $key => $altname) {
                    $alternatenames[$key] = $tableprefix . '.' . $altname;
                }
            }
        }
        $alternatenames = implode(',', $alternatenames);
    }
    return $alternatenames;

}
