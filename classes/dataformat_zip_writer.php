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
 * Zip data format writer for proforma responses
 *
 * @package   proformasubmexport
 * @copyright  2020 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Zip data format writer for proforma responses
 *
 * @package   proformasubmexport
 * @copyright  2020 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dataformat_zip_writer extends \core\dataformat\base {

    /** @var $mimetype */
    public $mimetype = "application/zip";

    /** @var $extension */
    public $extension = ".zip";

    /** @var $sheetdatadded */
    public $sheetdatadded = false;

    /** @var string response filename  */
    protected $responsefilename = 'editorresponse.txt';

    /** @var $zipper zip_archive object  */
    protected $ziparch = null;

    protected $ignoreinvalidfiles = true;
    protected $abort = false;
    /** @var null database column names */
    protected $columns = null;

    public function __construct() {
        $this->ziparch = new zip_archive();
    }

    /**
     * store database column names
     * @param $columns
     */
    public function set_columns($columns) {
        $this->columns = $columns;
    }

    /**
     * Write the start of the file.
     */
    public function start_output() {
        if (!$this->ziparch->open($this->filename, file_archive::OVERWRITE)) {
            debugging("Can not open zip file", DEBUG_DEVELOPER);
            $this->abort = true;
        } else {
            $this->abort = false;
        }
    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        $q = 1;
        $end = false;
        while (!$end) {
            if (!isset($this->columns['response' . $q])) {
                $end = true;
                break;
            }
            $file = $record[$this->columns['response' . $q]];
            $archivepath = 'Q' . $q. '-'. $record[$this->columns['question' . $q]] . '/'. $record[$this->columns['lastname']] . '-' .
                    $record[$this->columns['firstname']] . '-R' . $rownum;
            $archivepath = trim($archivepath, '/') . '/';

            // Create folder.
            if (!$this->ziparch->add_directory($archivepath)) {
                debugging("Can not zip '$archivepath' directory", DEBUG_DEVELOPER);
                if (!$this->ignoreinvalidfiles) {
                    $this->abort = true;
                }
            }
            $this->sheetdatadded = true;

            if (is_null($file)) {
                // Directories have null as content.
                if (!$this->ziparch->add_directory($archivepath.'/')) {
                    debugging("Can not zip '$archivepath' directory", DEBUG_DEVELOPER);
                    if (!$this->ignoreinvalidfiles) {
                        $this->abort = true;
                    }
                }
            } else if (is_string($file)) {
                // Editor content.
                $archivepath = $archivepath . $this->responsefilename;
                $content = $file;
                if (!$this->ziparch->add_file_from_string($archivepath, $content)) {
                    debugging("Can not zip '$archivepath' file", DEBUG_DEVELOPER);
                    if (!$this->ignoreinvalidfiles) {
                        $this->abort = true;
                    }
                }
                /*
            } else {
                if (!$this->archive_stored($ziparch, $archivepath, $file, $progress)) {
                    debugging("Can not zip '$archivepath' file", DEBUG_DEVELOPER);
                    if (!$this->ignoreinvalidfiles) {
                        $this->abort = true;
                    }
                }
                                */
            }
            $q++;
        }
    }

    /**
     * Write the end of the file.
     */
    public function close_output() {
        if (!$this->ziparch->close()) {
            @unlink($this->filename);
            return false;
        }

        if ($this->abort) {
            @unlink($this->filename);
            return false;
        }

        echo readfile($this->filename);
    }


}
