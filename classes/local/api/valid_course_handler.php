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
 * Convenient wrappers and helper for using the X-Ray web service API.
 *
 * @package   local_xray
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xray\local\api;

defined('MOODLE_INTERNAL') || die();

/**
 * A helper class to manage the valid course list gotten from X-Ray services.
 *
 * @package   local_xray
 * @author    David Castro <david.castro@blackboard.com>
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class valid_course_handler {

    /**
     * @var null|valid_course_handler
     */
    private static $instance = null;

    /**
     * @var null|array
     */
    private $validcourselist = null;

    /**
     * valid_course_handler constructor.
     */
    private function __construct() {
        // Nothing to do here.
    }

    /**
     * valid_course_handler clone method.
     */
    private function __clone() {
        // Prevent cloning.
    }

    /**
     * Singleton instance getter.
     * @return valid_course_handler|null
     */
    public static function instance() {
        if (self::$instance === null) {
            $c = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
    }

    /**
     * Returns an array with the valid course ids.
     * @return array|null
     */
    public function get_valid_course_list() {
        global $DB;
        if ($this->validcourselist === null) {
            if (!defined('PHPUNIT_TEST') && !defined('XRAY_OMIT_CACHE')) {
                define('XRAY_OMIT_CACHE', true);
            }
            $wsapires = wsapi::validcourses();

            if (defined('BEHAT_SITE_RUNNING')) {
                $query = "SELECT id from {course}";
                $courseids = $DB->get_records_sql($query);
                $courseidarr = [];
                foreach ($courseids as $cids) {
                    $courseidarr[] = $cids->id;
                }
                $this->validcourselist = $courseidarr;
            } else if ($wsapires && $wsapires->data) {
                $this->validcourselist = $wsapires->data;
            } else {
                $this->validcourselist = [];
            }
        }

        return $this->validcourselist;
    }

    /**
     * Returns a comma separated string with the valid course ids.
     * @return string
     */
    public function get_valid_course_list_as_string() {
        return implode(',', $this->get_valid_course_list());
    }
}