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

defined('MOODLE_INTERNAL') || die();

/**
 * Valid courses test.
 *
 * @package   local_xray
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/base.php');

use \local_xray\local\api\course_manager;

/**
 * Class local_xray_valid_courses_test
 *
 * All tests in this class will fail in case there is no appropriate fixture to be loaded.
 *
 * @package   local_xray
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_xray_valid_courses_test extends local_xray_base_testcase {

    /**
     * Array of idnumbers for all courses.
     * @var array
     */
    private $all = [100, 200, 300, 400, 500, 600, 700, 800, 900, 1000];

    /**
     * Array of idnumbers for valid courses.
     * @var array
     */
    private $valid = [100, 200, 300, 400, 500];

    /**
     * Array of idnumbers for selected courses.
     * @var array
     */
    private $selected = [200, 300, 400, 500, 600];

    /**
     * Array of idnumbers for courses which are selected but not valid.
     * @var array
     */
    private $selectednotvalid = [600];

    /**
     * Array of idnumbers for courses which are neither selected nor valid.
     * @var array
     */
    private $none = [700, 800, 900, 1000];

    /**
     * Array with all courses, Key is idnumber.
     * @var array
     */
    private $courses = [];

    /**
     * Array of ids for all courses.
     * @var array
     */
    private $allids = [];

    /**
     * Array of ids for valid courses.
     * @var array
     */
    private $validids = [];

    /**
     * Array of ids for selected courses.
     * @var array
     */
    private $selectedids = [];

    /**
     * Array of ids for courses which are selected but not valid.
     * @var array
     */
    private $selectednotvalidids = [];

    /**
     * Array of ids for courses which are neither selected nor valid.
     * @var array
     */
    private $noneids = [];

    /**
     * @return void
     */
    public function setUp() {
        $this->reset_ws();
    }

    /**
     * Creates and populates some courses to be used in the tests.
     */
    private function create_and_populate_selected_and_valid_courses() {
        global $CFG;
        // Create courses.
        $datagen = $this->getDataGenerator();
        foreach ($this->all as $idnum) {
            $this->courses[$idnum] = $datagen->create_course(['idnumber' => $idnum]);

            $this->allids[] = $this->courses[$idnum]->id;

            if (in_array($idnum, $this->valid)) {
                $this->validids[] = $this->courses[$idnum]->id;
            }

            if (in_array($idnum, $this->selected)) {
                $this->selectedids[] = $this->courses[$idnum]->id;
            }

            if (in_array($idnum, $this->none)) {
                $this->noneids[] = $this->courses[$idnum]->id;
            }

            if (in_array($idnum, $this->selectednotvalid)) {
                $this->selectednotvalidids[] = $this->courses[$idnum]->id;
            }
        }
        // Mark courses as enabled.
        $CFG->local_xray_disable_analysisfilter = true;
        course_manager::save_selected_courses($this->selectedids);
    }

    /**
     * Tests various ways in which course can be queried when valid/selected/none.
     */
    public function test_valid_courses() {
        $this->resetAfterTest(true);
        $this->config_set_ok();

        $this->create_and_populate_selected_and_valid_courses();

        // Pushing fixture data for login and valid course listing.
        \local_xray\local\api\testhelper::push_pair(
            'http://xrayserver.foo.com/user/login',
            'user-login-final.json'
        );

        $validres = new \stdClass();
        $validres->ok = true;
        $validres->data = $this->validids;

        \local_xray\local\api\testhelper::push_to_mem(
            'http://xrayserver.foo.com/demo/course/valid',
            json_encode($validres)
        );

        $nocategoryid = null;
        $nocourseid = null;

        // Selected courses should be X-Ray courses.
        foreach ($this->selectedids as $selectedid) {
            $xraycourse = course_manager::get_xray_courses($nocategoryid, $selectedid, false);
            $this->assertTrue($xraycourse['checked']);
        }

        // All valid courses should be returned.
        $xraycourses = course_manager::get_xray_courses($nocategoryid, $nocourseid);
        foreach ($xraycourses as $xraycourse) {
            $this->assertContains($xraycourse->id, $this->validids);
        }

        // A selected but invalid course should not be returned for xray course selection.
        foreach ($this->selectednotvalidids as $selectednotvalidid) {
            $xraycourse = course_manager::get_xray_courses($nocategoryid, $selectednotvalidid);
            $this->assertFalse($xraycourse['checked']);
        }

        // All other courses should never be valid / returned for xray course selection.
        foreach ($this->noneids as $noneid) {
            $xraycourse = course_manager::get_xray_courses($nocategoryid, $noneid, false);
            $this->assertFalse($xraycourse['checked']);
            $xraycourse = course_manager::get_xray_courses($nocategoryid, $noneid);
            $this->assertFalse($xraycourse['checked']);
        }
    }
}
