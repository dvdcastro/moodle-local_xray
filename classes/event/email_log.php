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
 * Class email_log.
 *
 * This event is fired when the email is sent.
 *
 * @package   local_xray
 * @author    German Vitale
 * @copyright Copyright (c) 2016 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_xray\event;

use core\event\base;

defined('MOODLE_INTERNAL') || die();

class email_log extends base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('emaillog', 'local_xray');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $courseid = (isset($this->other['courseid']) ? $this->other['courseid'] : 'unknown');
        $messagedesc = get_string('email_log_desc', 'local_xray', array('courseid' => $courseid, 'to' => $this->other['to']));
        if (empty($this->other['pdf'])) {
            $messagedesc .= ' '.get_string('pdfnotattached', 'local_xray');
        }
        return $messagedesc;
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        if (isset($this->other['courseid'])) {
            return new \moodle_url('/course/view.php', array('id' => $this->other['courseid']));
        } else {
            return new \moodle_url('/');
        }

    }
}
