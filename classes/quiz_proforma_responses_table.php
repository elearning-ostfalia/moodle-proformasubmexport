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
 * This file defines the quiz proforma responses table.
 *
 * @package   proformasubmexport
 * @copyright  2020 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/proformasubmexport/classes/dataformat_zip_writer.php');

/**
 * This is a table subclass for downloading the proforma responses.
 *
 * @package   proformasubmexport
 * @copyright  2020 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class quiz_proforma_responses_table extends quiz_attempts_report_table {

    public function __construct($quiz, $context, $qmsubselect, quiz_proforma_options $options,
            \core\dml\sql_join $groupstudentsjoins, \core\dml\sql_join $studentsjoins, $questions, $reporturl) {
        parent::__construct('mod-quiz-report-proforma-submission-export', $quiz, $context,
                $qmsubselect, $options, $groupstudentsjoins, $studentsjoins, $questions, $reporturl);
    }

    /**
     * Get, and optionally set, the export class.
     * @param $exportclass (optional) if passed, set the table to use this export class.
     * @return table_default_export_format_parent the export class in use (after any set).
     */
    function export_class_instance($exportclass = null) {
        if (!is_null($exportclass)) {
            $this->started_output = true;
            $this->exportclass = $exportclass;
            $this->exportclass->table = $this;
        } else if (is_null($this->exportclass) && !empty($this->download)) {
            // Change table format class.
            $this->exportclass = new table_zip_export_format($this, $this->download);
            if (!$this->exportclass->document_started()) {
                $this->exportclass->start_document($this->filename, $this->sheettitle);
            }
        }
        return $this->exportclass;
    }
    
    protected function requires_extra_data() {
        return true;
    }

    protected function field_from_extra_data($attempt, $slot, $field) {
        if (!isset($this->lateststeps[$attempt->usageid][$slot])) {
            return '-';
        }
        $stepdata = $this->lateststeps[$attempt->usageid][$slot];

        if (property_exists($stepdata, $field . 'full')) {
            $value = $stepdata->{$field . 'full'};
        } else {
            $value = $stepdata->$field;
        }
        return $value;
    }

    public function data_col($slot, $field, $attempt) {
        if ($attempt->usageid == 0) {
            return '-';
        }

        $value = $this->field_from_extra_data($attempt, $slot, $field);

        if (is_null($value)) {
            $summary = '-';
        } else {
            $summary = trim($value);
        }

        if ($this->is_downloading() && $this->is_downloading() != 'html') {
            return $summary;
        }
        $summary = s($summary);

        if ($this->is_downloading() || $field != 'responsesummary') {
            return $summary;
        }

        return $this->make_review_link($summary, $attempt, $slot);
    }

    public function other_cols($colname, $attempt) {
        if (preg_match('/^question(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionsummary', $attempt);

        } else if (preg_match('/^response(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'responsesummary', $attempt);

        } else if (preg_match('/^right(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'rightanswer', $attempt);

        } else {
            return null;
        }
    }

    /**
     * Returns html code for displaying "Download" button if applicable.
     */
    /*
    public function download_buttons() {
        return '';
        global $OUTPUT;

        if ($this->is_downloadable() && !$this->is_downloading()) {
            $label = get_string('downloadas', 'table');
            $hiddenparams = array();
            foreach ($this->baseurl->params() as $key => $value) {
                $hiddenparams[] = array(
                        'name' => $key,
                        'value' => $value,
                );
            }
            $data = array(
                'label' => $label,
                'base' =>  $this->baseurl->out_omit_querystring(),
                'name' => 'download',
                'params' => $hiddenparams,
                'options' => [[
                        'name' => 'zip',
                        'label' => 'zip'
                ]],
                'sesskey' => sesskey(),
                'submit' => get_string('download'),
            );

            return $OUTPUT->render_from_template('core/dataformat_selector', $data);

//            return $OUTPUT->download_dataformat_selector('KARIN', // get_string('downloadas', 'table'),
//                    $this->baseurl->out_omit_querystring(), 'download', $this->baseurl->params());
        } else {
            return '';
        }

    }*/
}


class table_zip_export_format extends table_dataformat_export_format {
    /**
     * Constructor
     *
     * @param string $table An sql table
     * @param string $dataformat type of dataformat for export
     */
    public function __construct(&$table, $dataformat) {
        // Prevent we are using csv instead of zip in order to pass the constructor call.
        parent::__construct($table, 'csv');

        if (ob_get_length()) {
            throw new coding_exception("Output can not be buffered before instantiating table_dataformat_export_format");
        }

        $classname = 'dataformat_zip_writer';
        if (!class_exists($classname)) {
            throw new coding_exception("Unable to locate " . $classname);
        }
        $this->dataformat = new $classname;

        // The dataformat export time to first byte could take a while to generate...
        set_time_limit(0);

        // Close the session so that the users other tabs in the same session are not blocked.
        \core\session\manager::write_close();
    }
}