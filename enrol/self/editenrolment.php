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
 * Self user enrolment edit script.
 *
 * This page allows the current user to edit a self user enrolment.
 * It is not compatible with the frontpage.
 *
 * @package    enrol
 * @subpackage self
 * @copyright  2011 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/locallib.php"); // Required for the course enrolment manager
require_once("$CFG->dirroot/enrol/renderer.php"); // Required for the course enrolment users table
require_once("$CFG->dirroot/enrol/self/editenrolment_form.php"); // Forms for this page

$ueid   = required_param('ue', PARAM_INT); // user enrolment id
$filter = optional_param('ifilter', 0, PARAM_INT); // table filter for return url

// Get the user enrolment object
$ue     = $DB->get_record('user_enrolments', array('id' => $ueid), '*', MUST_EXIST);
// Get the user for whom the enrolment is
$user   = $DB->get_record('user', array('id'=>$ue->userid), '*', MUST_EXIST);
// Get the course the enrolment is to
list($ctxsql, $ctxjoin) = context_instance_preload_sql('c.id', CONTEXT_COURSE, 'ctx');
$sql = "SELECT c.* $ctxsql
          FROM {course} c
     LEFT JOIN {enrol} e ON e.courseid = c.id
               $ctxjoin
         WHERE e.id = :enrolid";
$params = array('enrolid' => $ue->enrolid);
$course = $DB->get_record_sql($sql, $params, MUST_EXIST);
context_instance_preload($course);

// Make sure the course isn't the front page
if ($course->id == SITEID) {
    redirect(new moodle_url('/'));
}

// Obvioulsy
require_login($course);
// The user must be able to manage self enrolments within the course
require_capability("enrol/self:manage", context_course::instance($course->id, MUST_EXIST));

// Get the enrolment manager for this course
$manager = new course_enrolment_manager($PAGE, $course, $filter);
// Get an enrolment users table object. Doign this will automatically retrieve the the URL params
// relating to table the user was viewing before coming here, and allows us to return the user to the
// exact page of the users screen they can from.
$table = new course_enrolment_users_table($manager, $PAGE);

// The URL of the enrolled users page for the course.
$usersurl = new moodle_url('/enrol/users.php', array('id' => $course->id));
// The URl to return the user too after this screen.
$returnurl = new moodle_url($usersurl, $manager->get_url_params()+$table->get_url_params());
// The URL of this page
$url = new moodle_url('/enrol/self/editenrolment.php', $returnurl->params());

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url($usersurl);

// Gets the compontents of the user enrolment
list($instance, $plugin) = $manager->get_user_enrolment_components($ue);
// Check that the user can manage this instance, and that the instance is of the correct type
if (!$plugin->allow_manage($instance) || $instance->enrol != 'self' || !($plugin instanceof enrol_self_plugin)) {
    print_error('erroreditenrolment', 'enrol');
}

// Get the self enrolment edit form
$mform = new enrol_self_user_enrolment_form($url, array('user'=>$user, 'course'=>$course, 'ue'=>$ue));
$mform->set_data($PAGE->url->params());

// Check the form hasn't been cancelled
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->is_submitted() && $mform->is_validated() && confirm_sesskey()) {
    // The forms been submit, validated and the sesskey has been checked ... edit the enrolment.
    $data = $mform->get_data();
    if ($manager->edit_enrolment($ue, $data)) {
        redirect($returnurl);
    }
}

$fullname = fullname($user);
$title = get_string('editenrolment', 'enrol_self');

$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);
$PAGE->navbar->add($fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($fullname);
$mform->display();
echo $OUTPUT->footer();