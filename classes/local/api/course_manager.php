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
 * AWS validation helpers.
 *
 * @package   local_xray
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2016 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xray\local\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class course_manager
 * @package local_xray
 */
abstract class course_manager {

    const PLUGIN = 'local_xray';

    const MAXSTUDENTS = 1000;

    /**
     * Retrieve the available courses for usage with X-Ray
     *
     * @param string $cid Id of the category
     * @return \stdClass[] Has all course information and selection status
     * @throws \moodle_exception
     */
    public static function list_courses_as_simple_array($cid = 'all') {
        return self::get_xray_courses($cid, null);
    }

    /**
     * Validate if a course is selected for and usable with X-Ray
     * @param int $courseid The course that needs to know if it is part of the X-Ray club
     * @return bool true if course is part of club, false otherwise
     */
    public static function is_xray_course($courseid) {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || (defined('BEHAT_SITE_RUNNING') &&  BEHAT_SITE_RUNNING)) {
            return true;
        }

        $checkifvalid = false;
        $xraycourse = self::get_xray_courses(null, $courseid, $checkifvalid);
        return $xraycourse['checked'];
    }

    /**
     * Retrieves the xray courses for the specified category id or a specific course for the specified course id.
     * @param int|string|null $categoryid Category id or 'all'
     * @param int $courseid
     * @param bool $checkifvalid Check if the courses are valid in XRF
     * @return array Array of courses or single course that has all course information and selection status
     */
    public static function get_xray_courses($categoryid = 'all', $courseid = null, $checkifvalid = true) {
        global $CFG, $DB;
        if ((is_null($categoryid) && is_null($courseid)) || $categoryid === 0) {
            return array();
        }

        $res = array();
        $wherequery = '';
        if ($checkifvalid) {
            $validcourseids = valid_course_handler::instance()->get_valid_course_list_as_string();
            if (!empty($validcourseids)) {
                $wherequery .= 'WHERE mdc.id IN ('.$validcourseids.')';
            } else {
                return self::process_xray_courses_response($courseid, $res);
            }
        }

        if (!is_null($categoryid) && $categoryid !== 'all') {
            $wherequery .= (empty($wherequery) ? 'WHERE ' : ' AND ').'mdc.category = :categoryid';
        } else if (!is_null($courseid)) {
            $wherequery .= (empty($wherequery) ? 'WHERE ' : ' AND ').'mdc.id = :courseid';
        }

        list($inoreqsql, $params) = $DB->get_in_or_equal(explode(',', $CFG->gradebookroles), SQL_PARAMS_NAMED, 'grbr0');
        $params['contextcourse'] = CONTEXT_COURSE;
        $params['categoryid'] = $categoryid;
        $params['courseid'] = $courseid;

        $xraycoursequery = "
            SELECT mdc.id,
                   mdc.shortname,
                   mdc.fullname,
                   xsc.id AS xrayid

              FROM {course} mdc
         LEFT JOIN {local_xray_selectedcourse} xsc ON (mdc.id = xsc.cid)

             $wherequery

          ORDER BY mdc.fullname
        ";

        $xraycourses = $DB->get_records_sql($xraycoursequery, $params);

        foreach ($xraycourses as $xraycourse) {
            $res[] = array(
                'id' => $xraycourse->id,
                'name' => strip_tags($xraycourse->fullname),
                'shortname' => $xraycourse->shortname,
                'checked' => !is_null($xraycourse->xrayid)
            );
        }

        return self::process_xray_courses_response($courseid, $res);
    }

    /**
     * @param null|int $courseid
     * @param array $res
     * @return array Array of courses or single course that has all course information and selection status
     */
    private static function process_xray_courses_response($courseid = null, $res) {
        if (is_null($courseid)) {
            return $res;
        }

        $emptycourse = array(
            'id' => $courseid,
            'checked' => false
        );
        return empty($res) ? $emptycourse : $res[0];
    }

    /**
     * Retrieve the available courses for usage with X-Ray as json encoded string
     *
     * @param string $categoryid Id of the category
     * @param int $options JSON constant options
     * @return string[] Format $id=>$fullname
     * @throws \moodle_exception
     */
    public static function list_courses_as_json($categoryid = 'all', $options = 0) {
        return json_encode(self::list_courses_as_simple_array($categoryid), $options);
    }

    /**
     * Retrieve the available categories for usage with X-Ray
     *
     * @param  int $cid Id of the category
     * @return array
     * @throws \moodle_exception
     */
    public static function list_categories_as_simple_array($cid = 0) {
        global $DB;

        $res = array();

        $validcourseids = valid_course_handler::instance()->get_valid_course_list_as_string();
        if (empty($validcourseids)) {
            return $res;
        }

        $query = "SELECT mdcat.id, mdcat.name,

                         COUNT(mdc.id) AS totcourses,
                         COUNT(lxc.id) AS xraycourses

                    FROM {course_categories} mdcat
               LEFT JOIN {course} mdc ON mdc.category = mdcat.id AND mdc.id IN ($validcourseids)
               LEFT JOIN {local_xray_selectedcourse} lxc ON mdc.id = lxc.cid

                   WHERE mdcat.parent = :cid
                GROUP BY mdcat.id, mdcat.name
                ORDER BY mdcat.name ASC, mdcat.timemodified";

        $categories = $DB->get_records_sql($query, array('cid' => $cid));

        foreach ($categories as $cat) {
            $xraycourses = $cat->xraycourses;
            $totcourses = $cat->totcourses;

            $subcatcourses = self::query_categories_selected_courses($cat->id);
            $xraycourses += $subcatcourses->xraycourses;
            $totcourses += $subcatcourses->totcourses;

            if($totcourses === 0) {
                continue;
            }

            $checkstatus = self::compute_check_status($xraycourses, $totcourses);

            $res[] = array(
                'id' => $cat->id,
                'name' => $cat->name,
                'checked' => $checkstatus->checked,
                'indeterminate' => $checkstatus->indeterminate
            );
        }

        return $res;
    }

    /**
     * Counts number of selected courses and total courses in category and sub categories
     * @param string|int $cid Parent category id
     * @return \stdClass response with attributes: xraycourses, totcourses
     */
    private static function query_categories_selected_courses($cid = 0) {
        global $DB;

        $res = new \stdClass();

        $validcourseids = valid_course_handler::instance()->get_valid_course_list_as_string();
        if (empty($validcourseids)) {
            return $res;
        }

        $query = "SELECT mdcat.id,

                         COUNT(mdc.id) AS totcourses,
                         COUNT(lxc.id) AS xraycourses

                    FROM {course_categories} mdcat
               LEFT JOIN {course} mdc ON mdc.category = mdcat.id AND mdc.id IN ($validcourseids)
               LEFT JOIN {local_xray_selectedcourse} lxc ON mdc.id = lxc.cid

                   WHERE mdcat.parent = :cid
                GROUP BY mdcat.id, mdcat.name
                ORDER BY mdcat.name ASC, mdcat.timemodified";

        $categories = $DB->get_records_sql($query, array('cid' => $cid));

        $res->xraycourses = 0;
        $res->totcourses = 0;
        foreach ($categories as $cat) {
            $res->xraycourses += $cat->xraycourses;
            $res->totcourses += $cat->totcourses;

            $subcatcourses = self::query_categories_selected_courses($cat->id);
            $res->xraycourses += $subcatcourses->xraycourses;
            $res->totcourses += $subcatcourses->totcourses;
        }

        return $res;
    }

    /**
     * Calculates the check status based on number of xray selected courses and total category courses
     * @param int $xraycourses X-Ray selected courses
     * @param int $totcourses Total courses for this category
     * @return \stdClass response with attribures: disabled, checked, indeterminate
     */
    public static function compute_check_status($xraycourses, $totcourses) {
        $res = new \stdClass();

        $res->disabled = $totcourses == 0;
        $res->checked = $xraycourses > 0;
        $res->indeterminate = $res->checked && $xraycourses < $totcourses;

        return $res;
    }

    /**
     * Retrieve the available categories for usage with X-Ray as json encoded string
     *
     * @param  int $cid Id of the category
     * @return string
     * @throws \moodle_exception
     */
    public static function list_categories_as_json($cid = 0) {
        return json_encode(self::list_categories_as_simple_array($cid));
    }

    /**
     * Checks if course is selected for X-Ray usage
     * @param int|string $cid Course id
     * @return boolean
     */
    public static function is_course_selected($cid) {
        if (defined('BEHAT_SITE_RUNNING')) {
            return true;
        }

        global $DB;
        $res = $DB->record_exists('local_xray_selectedcourse', array('cid' => $cid));
        return $res;
    }

    /**
     * Loads selected courses on X-Ray server
     * @return array
     * @throws \moodle_exception
     */
    public static function load_course_ids_from_xray() {
        if (!defined('XRAY_OMIT_CACHE')) {
            define('XRAY_OMIT_CACHE', true);
        }

        $res = array();

        if (defined('BEHAT_SITE_RUNNING')) {
            return $res;
        }

        if ($xraycourses = wsapi::get_analysis_filter()) {
            $res = $xraycourses->filtervalue;
        } else {
            throw new \moodle_exception(
                'xray_check_global_settings',
                self::PLUGIN,
                '',
                self::generate_xray_settings_link()
            );
        }

        return $res;
    }

    /**
     * Verifies if courses in X-Ray match courses selected in moodle
     * @return boolean
     * @throws \moodle_exception
     */
    public static function courses_match() {
        $cids = self::list_selected_course_ids();
        $cidcount = count($cids);
        $xraycids = self::load_course_ids_from_xray();

        return $cidcount === count(array_intersect($xraycids, $cids));
    }

    /**
     * Processes a UI course record for X-Ray selection persistence.
     * @param string $courseid Course that comes from the UI
     * @return \stdClass Processed course for persistence.
     */
    private static function process_ui_record($courseid) {
        $res = new \stdClass();
        $res->cid = $courseid;
        return $res;
    }

    /**
     * Saves selected courses
     * @param array $courseids
     */
    public static function save_selected_courses($courseids = null) {
        if (is_null($courseids)) {
            return;
        }

        global $DB, $USER;

        $selcourtable = 'local_xray_selectedcourse';
        $allcategoriesid = 'all';

        $context = \context_system::instance();
        $before_xraycourses_saved = $DB->get_records_menu($selcourtable, array());
        // Clear the table from old selection.
        if ($DB->record_exists($selcourtable, array())) {
            // Record deletion.
            $DB->delete_records($selcourtable);
        }
        // If no course ids are selected for saving, do not continue.
        if (!empty($courseids)) {
            // Save all records.
            $records = array_map(array(__CLASS__, 'process_ui_record'), $courseids);
            $DB->insert_records($selcourtable, $records);
        }
        $after_xraycourses_saved = $DB->get_records_menu($selcourtable, array());
        $added_xraycourses = array_diff($after_xraycourses_saved, $before_xraycourses_saved);
        if ($added_xraycourses) {
            $diff = array();
            foreach ($added_xraycourses as $id => $course) {
                $diff[] = 'ID: ' . $course;
            }
            // Order the numbers of the course ids on the log page from the lowest to the highest.
            usort($diff, function ($a, $b) {
                return strcasecmp($a, $b);
            });
            $diff = implode(', ', $diff);
            // X-Ray Course addition event trigger.
            $addeventdata = array(
                'context' => $context,
                'other' => array('userid' => $USER->id,
                                 'courses' => $diff));
            \local_xray\event\course_selection_added::create($addeventdata)->trigger();
        }
        $deleted_xraycourses = array_diff($before_xraycourses_saved, $after_xraycourses_saved);
        if ($deleted_xraycourses) {
            $diff = array();
            foreach ($deleted_xraycourses as $id => $course) {
                $diff[] = 'ID: ' . $course;
            }
            // Order the numbers of the course ids on the log page from the lowest to the highest.
            usort($diff, function ($a, $b) {
                return strcasecmp($a, $b);
            });
            $diff = implode(', ', $diff);
            // X-Ray Course removal event data.
            $remeventdata = array(
                'context' => $context,
                'other' => array('userid' => $USER->id,
                                 'courses' => $diff));
            // X-Ray Course removal event trigger.
            \local_xray\event\course_selection_removed::create($remeventdata)->trigger();
        }
        // Save courses to X-Ray as well.
        if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
            self::save_courses_to_xray();
        }
    }

    /**
     * Retrieve the selected courses for usage with X-Ray
     *
     * @return int[] course ids
     * @throws \moodle_exception
     */
    public static function list_selected_course_ids() {
        global $DB;

        $selcourtable = 'local_xray_selectedcourse';

        $courseids = $DB->get_records($selcourtable);

        $res = array();
        foreach ($courseids as $course) {
            $res[] = $course->cid;
        }

        return $res;
    }

    /**
     * Retrieve the selected courses for usage with X-Ray
     *
     * @return \stdClass[] courses
     * @throws \moodle_exception
     */
    private static function list_selected_courses() {
        global $DB;
        $selcourtable = 'local_xray_selectedcourse';
        $courseids = $DB->get_records($selcourtable);
        return $courseids;
    }

    /**
     * Saves the enabled course list and dates to the X-Ray server
     * @throws \moodle_exception
     */
    private static function save_courses_to_xray() {
        if (!defined('XRAY_OMIT_CACHE')) {
            define('XRAY_OMIT_CACHE', true);
        }
        self::save_analysis_filter();
    }

    /**
     * Saves the enabled course list to the X-Ray server
     * @throws \moodle_exception
     */
    private static function save_analysis_filter() {
        $cids = self::list_selected_course_ids();
        $wsapires = wsapi::save_analysis_filter($cids);
        self::process_wsapi_save_response($wsapires);
    }

    /**
     * Processes a response form the server which should result in {ok: true}
     * @param $wsapires
     * @throws \moodle_exception
     */
    private static function process_wsapi_save_response($wsapires) {
        if ($wsapires !== false) {
            if (!empty($wsapires->ok)) {
                return;
            } else {
                $error = get_string('error_xray_unknown', self::PLUGIN);
                if (isset($wsapires->error)) {
                    $error = $wsapires->error;
                } else if (isset($wsapires)) {
                    $error = $wsapires;
                }
                throw new \moodle_exception('xray_save_course_filter_error', self::PLUGIN, '', $error);
            }
        } else {
            throw new \moodle_exception(
                    'xray_save_course_filter_error', self::PLUGIN,
                    '', get_string('error_xray', self::PLUGIN));
        }
    }

    /**
     * Generates X-Ray connection error text with a link to the global settings
     * @return string Text with a connection error to X-Ray and link to settings
     */
    public static function generate_xray_settings_link() {
        $globalseturl = new \moodle_url('/admin/settings.php',
                array('section' => self::PLUGIN.'_global'));

        $globalsetlink = '&nbsp;<a href="'.$globalseturl->out(false).'">'
                .get_string('xray_check_global_settings_link', self::PLUGIN)
                .'</a>';

        return $globalsetlink;
    }

    /**
     * Check if the Risk Status report is disabled on X-Ray side.
     * @return array
     * @throws \moodle_exception
     */
    public static function is_risk_disabled() {
        if (!defined('XRAY_OMIT_CACHE')) {
            define('XRAY_OMIT_CACHE', true);
        }
        $res = false;
        if ($xraycourses = wsapi::get_risk_configuration()) {
            if (isset($xraycourses->currentSettings->RISK_REPORT)
                    && $xraycourses->currentSettings->RISK_REPORT == "false") {
                $res = true;
            }
        } else {
            throw new \moodle_exception(
                'xray_check_global_settings',
                self::PLUGIN,
                '',
                self::generate_xray_settings_link()
            );
        }
        return $res;
    }

    /*
    * Check if the course is an X-Ray course and delete it.
    * @param int $courseid
    */
    public static function check_xray_course_to_delete($courseid) {
        global $DB;

        // If the course is in local_xray_selectedcourse table, it should be deleted and saved in X-Ray side.
        if ($DB->record_exists('local_xray_selectedcourse', array('cid' => $courseid))) {
            // Delete the course in local_xray_selectedcourse table.
            $DB->delete_records_select('local_xray_selectedcourse', "cid = :courseid", array('courseid' => $courseid));
            // Update X-Ray side.
            if (!PHPUNIT_TEST) {
                self::save_courses_to_xray();
            }
        }
    }
}
