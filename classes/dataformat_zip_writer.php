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
    public $mimetype = "text/plain"; // "application/zip";

    /** @var $extension */
    public $extension = ".txt";

    /** @var $sheetstarted */
    public $sheetstarted = false;

    /** @var $sheetdatadded */
    public $sheetdatadded = false;

    /**
     * Write the start of the file.
     */
    public function start_output() {
        echo "[";
    }

    /**
     * Write the start of the sheet we will be adding data to.
     *
     * @param array $columns
     */
    public function start_sheet($columns) {
        if ($this->sheetstarted) {
            echo ",";
        } else {
            $this->sheetstarted = true;
        }
        $this->sheetdatadded = false;
        echo "[";
    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        if ($this->sheetdatadded) {
            echo ",";
        }

        // echo 'DAS IST EIN DATENSATZ ';
        echo $record;
        echo json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $this->sheetdatadded = true;
    }

    /**
     * Write the end of the sheet containing the data.
     *
     * @param array $columns
     */
    public function close_sheet($columns) {
        echo "]";
    }

    /**
     * Write the end of the file.
     */
    public function close_output() {
        echo "]";
    }
}
