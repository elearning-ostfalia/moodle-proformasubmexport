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
 * This file defines the setting form for the quiz proformasubmexport report.
 *
 * @package   quiz_proformasubmexport
 * @copyright 2017 IIT Bombay
 * @author    Kashmira Nagwekar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz proformasubmexport report settings form.
 *
 * @copyright 2017 IIT Bombay
 * @author    Kashmira Nagwekar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_form.php');

class quiz_proformasubmexport_settings_form extends mod_quiz_attempts_report_form { // moodleform {

    protected function definition() {
        parent::definition();
        // remove submit button
        // $this->_form->removeElement('submitbutton');
    }

    protected function standard_preference_fields(MoodleQuickForm $mform) {
        // No page preferences.
        $mform->addElement('hidden', 'pagesize', get_string('pagesize', 'quiz'));
        $mform->setType('pagesize', PARAM_INT);
        $mform->removeElement('preferencesuser');
    }

    protected function other_preference_fields(MoodleQuickForm $mform) {
        $mform->addElement('advcheckbox', 'qtext',
                get_string('questiontext', 'quiz_proformasubmexport'),
                get_string('include', 'quiz_proformasubmexport'));

        $mform->addElement('header', 'preferencespage',
                get_string('options', 'quiz_proformasubmexport'));

        $mform->addElement('select', 'folders',
                get_string('folderhierarchy', 'quiz_proformasubmexport'), array(
                        '1' => get_string('questionwise', 'quiz_proformasubmexport'),
                        '2' => get_string('attemptwise', 'quiz_proformasubmexport'
                        )));

        $mform->addElement('select', 'editorfilename',
                get_string('editorfilename', 'quiz_proformasubmexport'), array(
                        '1' => get_string('fix', 'quiz_proformasubmexport') . ' (' .
                                get_string('editorresponsename', 'quiz_proformasubmexport') . ')',
                        '2' => get_string('pathname', 'quiz_proformasubmexport'),
                        '3' => get_string('basename', 'quiz_proformasubmexport')
                ));

        // $mform->addElement('submit', 'proformasubmexport', get_string('proformasubmexport', 'quiz_proformasubmexport'));
        // $mform->addElement('submit', 'proformasubmexport', 'Download');
    }

    /**
     * Form definition method.
     */
    /*
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $mform->addElement('hidden', 'id', '');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'mode', '');
        $mform->setType('mode', PARAM_ALPHA);

        // $mform->addElement('header', 'preferencespage',
        //   get_string('reportwhattoinclude', 'quiz'));

        $mform->addElement('header', 'preferencespage',
                get_string('options', 'quiz_proformasubmexport'));

        $mform->addElement('select', 'folders',
                get_string('folderhierarchy', 'quiz_proformasubmexport'), array(
                        'questionwise' => get_string('questionwise', 'quiz_proformasubmexport'),
                        'attemptwise' => get_string('attemptwise', 'quiz_proformasubmexport'
                        )));

        $mform->addElement('advcheckbox', 'questiontext',
                get_string('questiontext', 'quiz_proformasubmexport'),
                get_string('include', 'quiz_proformasubmexport'));

        $mform->addElement('select', 'editorfilename',
                get_string('editorfilename', 'quiz_proformasubmexport'), array(
                        'fix' => get_string('fix', 'quiz_proformasubmexport') . ' (' .
                                get_string('editorresponsename', 'quiz_proformasubmexport') . ')',
                        'pathname' => get_string('pathname', 'quiz_proformasubmexport'),
                        'basename' => get_string('basename', 'quiz_proformasubmexport')
                ));

        // $mform->addElement('submit', 'proformasubmexport', get_string('proformasubmexport', 'quiz_proformasubmexport'));
        $mform->addElement('submit', 'proformasubmexport', 'Download');
    }
    */
}