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
 * X-Ray global settings page
 *
 * @package   local_xray
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

if ($hassiteconfig) {
    global $CFG;
    /* @noinspection PhpIncludeInspection */
    require_once($CFG->dirroot.'/local/xray/lib.php');
    $plugin = 'local_xray';
    
    $ADMIN->add('localplugins',
        new admin_category($plugin,
        new lang_string('pluginname', $plugin))
    );
    
    require_once($CFG->dirroot.'/local/xray/global_settings.php');
    // Add System Reports.
    $urlcourseselection = new moodle_url('/local/xray/view.php',
            array('controller'      => 'courseselection',
                  'action'          => 'view'));
    $ADMIN->add('local_xray', new admin_externalpage('courseselection_view',
        new lang_string('courseselection', $plugin),
        $urlcourseselection->out(false)));
    
}

// This has to be outside of global if so that we can permit non admin users to see this.
if (has_capability('local/xray:systemreports_view', context_system::instance())) {
    // Get the URL.
    $showsystemreport = !empty(get_config('local_xray', 'systemreportsurl'));
    if ($showsystemreport) {
        // Add tree X-ray -> Rebuke List to show in reports.
        $ADMIN->add('reports',
            new admin_category('local_xray_report', new lang_string('pluginname', 'local_xray'))
        );
        // Add System Reports.
        $urlsystemreports = new moodle_url('/local/xray/view.php', ['controller' => 'systemreports']);
        $ADMIN->add('local_xray_report',
            new admin_externalpage(
                'systemreports',
                new lang_string('systemreports', 'local_xray'),
                $urlsystemreports->out(false),
                'local/xray:systemreports_view'
            )
        );
    }
}
