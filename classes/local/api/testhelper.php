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
 * Unit test helpers.
 *
 * @package   local_xray
 * @author    Darko Miletic
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xray\local\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Class testhelper
 *
 * @package   local_xray
 * @author    Darko Miletic
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class testhelper {

    /**
     * @var array
     */
    protected static $fixturestash = [];


    /**
     * @var array
     */
    protected static $memstash = [];

    /**
     * @param  string $rpath
     * @param  string $name
     * @return null|string
     */
    protected static function load_data($rpath,  $name) {
        $result = null;

        $repath = realpath($rpath.$name);
        if ($repath !== false) {
            $lresult = file_get_contents($repath);
            if ($lresult !== false) {
                $result = $lresult;
            }
        }

        return $result;
    }

    /**
     * @param  string $url
     * @return null|string
     */
    public static function get_fixture_data($url) {
        global $CFG;
        $result = null;
        $filename = self::generate_name($url);
        if (!empty($filename)) {
            $result = self::load_data($CFG->dirroot.'/local/xray/tests/fixtures/', $filename);
        } else if (!empty(self::$memstash[$url])) {
            $result = self::$memstash[$url];
        }
        return $result;
    }

    /**
     * @param string $url
     * @param string $filename
     * @return void
     */
    public static function push_pair($url, $filename) {
        self::$fixturestash[$url] = $filename;
    }

    /**
     * @param string $url
     * @param string $contents
     * @return void
     */
    public static function push_to_mem($url, $contents) {
        self::$memstash[$url] = $contents;
    }

    /**
     * @param string $url
     * @return null|string
     */
    public static function generate_name($url) {
        if (!empty(self::$fixturestash[$url])) {
            return self::$fixturestash[$url];
        }
        return null;
    }

    /**
     * @return void
     */
    public static function reset_stash() {
        self::$fixturestash = [];
    }

}
