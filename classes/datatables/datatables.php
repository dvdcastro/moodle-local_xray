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
 * Create configuration for Datatable Jquery plugin integration.
 *
 * @package local_xray
 * @author Pablo Pagnone
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xray\datatables;
defined('MOODLE_INTERNAL') || die();

class datatables {

    /**
     * id for table
     * @var string
     */
    public $id;
    
    /**
     * title for table
     * @var string
     */
    public $title;
    
    /**
     * Url to get data with json format.
     * @var string
     */
    public $jsonurl;

    /**
     * Show pager
     * @var boolean
     */
    public $paging;

    /**
     * Change Dom
     * @var string
     */
    public $dom;

    /**
     * Length menu
     * @var array
     */
    public $lengthMenu;

    /**
     * Show search
     * @var boolean
     */
    public $search;

    /**
     * Message to process data
     * @var string
     */
    public $sProcessingMessage;
    
    /**
     * Texts of datatables
     * @var string
     */
    public $sFirst;   
    public $sLast;
    public $sNext;    
    public $sPrevious;
    public $sProcessing;
    public $sLengthMenu;
    public $sZeroRecords; 
    public $sEmptyTable;
    public $sInfo;
    public $sInfoEmpty;
    public $sLoadingRecords;
    public $sSortAscending;
    public $sSortDescending;
    
    /**
     * Message to show in error case.
     * @var string
     */
    public $errorMessage;

    /**
     * Array with objects local_xray_datatableColumn
     * @var array
     */
    public $columns;
    
    /**
     * Enable/Disable sortable.
     * @var boolean
     */
    public $sort;
    
    /**
     * Default field sort(number of column).
     * The default is 0 , the first column.
     * @var integer
     */
    public $default_field_sort;
    
    /**
     * Default sort order
     * Values required: "desc" or "asc"
     * @var string
     */
    public $sort_order;    
    
    /**
     * Construct
     * 
     * @param integer $id
     * @param string $jsonurl
     * @param array $columns
     * @param bool $search
     * @param bool $paging
     * @param string $dom
     * @param array $lengthMenu
     * @param integer $default_field_sort
     * @param string $sort_order
     */
    public function __construct($id, $title, $jsonurl, $columns, $search = false, $paging=true, $dom = 'lftipr',
                                $lengthMenu = array(10, 50, 100), $sort = true, $default_field_sort = 0, $sort_order = "asc") {
        $this->id = $id;
        $this->title = $title;
        $this->jsonurl = $jsonurl;
        $this->columns = $columns;
        $this->search = $search;
        $this->paging = $paging;
        $this->dom = $dom;
        $this->lengthMenu = $lengthMenu;
        $this->sort = $sort;
        $this->default_field_sort = $default_field_sort;
        $this->sort_order = $sort_order;
        
        // Support lang with moodle lang.
        $this->sProcessingMessage = get_string('sProcessingMessage', 'local_xray');
        $this->sFirst = get_string('sFirst', 'local_xray');
        $this->sLast = get_string('sLast', 'local_xray');
        $this->sNext = get_string('sNext', 'local_xray');
        $this->sPrevious = get_string('sPrevious', 'local_xray');
        $this->sEmptyTable = get_string('sEmptyTable', 'local_xray');
        $this->sInfo = get_string('sInfo', 'local_xray');
        $this->sInfoEmpty = get_string('sInfoEmpty', 'local_xray');
        $this->sLengthMenu = get_string('sLengthMenu', 'local_xray');
        $this->sLoadingRecords = get_string('sLoadingRecords', 'local_xray');
        $this->sSortAscending = get_string('sSortAscending', 'local_xray');
        $this->sSortDescending = get_string('sSortDescending', 'local_xray'); 
        $this->sProcessing = get_string('sProcessing', 'local_xray');
        $this->sZeroRecords = get_string('sZeroRecords', 'local_xray');
             
        $this->errorMessage = get_string('error_datatables','local_xray');
    }
}