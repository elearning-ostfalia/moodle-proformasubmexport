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
 * This file defines the quiz proformasubmexport report class.
 *
 * @package   quiz_proformasubmexport
 * @copyright 2017 IIT Bombay
 * @author      Kashmira Nagwekar, K.Borm (Ostfalia)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/proformasubmexport/proformasubmexport_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/proformasubmexport/classes/quiz_proforma_responses_table.php');


/**
 * Quiz report subclass for the proformasubmexport report.
 *
 * This report allows you to download file attachments submitted
 * by students as a response to quiz proforma questions.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_proformasubmexport_report extends quiz_attempts_report {

    private $mem_info;
    private $max_mem = 0;
    private function set_mem($text) {
        if (memory_get_usage() > $this->max_mem)
            $this->max_mem = memory_get_usage();
        $this->mem_info .= ' _' . $text . ': ' . $this->max_mem;
    }

    public function display($quiz, $cm, $course) {
        global $OUTPUT, $DB;

        /*if (!ini_set('memory_limit','1024')) {
            throw new coding_exception('cannot set memory limit');
        }*/
        $this->mem_info = ' ';
        $this->max_mem = 0;

        // Create form.
        list($currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoins) = $this->init('proformasubmexport',
                'quiz_proformasubmexport_settings_form', $quiz, $cm, $course);

        $options = new mod_quiz_attempts_report_options('proformasubmexport', $quiz, $cm, $course);

        if ($fromform = $this->form->get_data()) {
            $options->process_settings_from_form($fromform);
        } else {
            $options->process_settings_from_params();
        }
        $this->form->set_data($options->get_initial_form_data());

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Prepare for downloading, if applicable.
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $table = new quiz_proforma_responses_table($quiz, $this->context, $this->qmsubselect,
                $options, $groupstudentsjoins, $studentsjoins, $questions, $options->get_url());
        $filename = quiz_report_download_filename('proformasubm', // get_string('responsesfilename', 'quiz_responses'),
                $courseshortname, $quiz->name);

        // Method 1 : Check $quiz object for existence of proforma type questions.
        $hasproformaquestions = $this->has_quiz_proforma_questions($quiz);
        // Method 2 : Check {quiz_slots} table
        // $hasproformaquestions = $this->quiz_has_proforma_questions($quiz->id);

        $hasstudents = false;
        $sql = "SELECT DISTINCT u.id
                FROM {user} 			u
                JOIN {user_enrolments} 	ej1_ue 	ON 	ej1_ue.userid 	= u.id
                JOIN {enrol} 			ej1_e 	ON 	(ej1_e.id 		= ej1_ue.enrolid
                								AND ej1_e.courseid 	= $course->id)
                WHERE
                	1 = 1 AND u.deleted = 0";
        $hasstudents = $DB->record_exists_sql($sql);

        $downloading_submissions = false;
        $ds_button_clicked = false;
        $user_attempts = false;
        $hassubmissions = false;

        // Check if downloading file submissions.
        if ($data = $this->form->get_data()) {
            $ds_button_clicked = !empty($data->proformasubmexport);
            if ($ds_button_clicked) {
                $this->set_mem('US');
                $user_attempts = $this->get_user_attempts($quiz, $course);
                $this->set_mem('UE');
                $downloading_submissions = $hasproformaquestions && $user_attempts; // && $ds_button_clicked; which is true at this position
                $this->set_mem('US');
            }
        }

        // Start output.
        $this->set_mem('END');
        // echo $this->mem_info;

        // Download file submissions for proforma questions.
        if ($downloading_submissions) {
            // If no attachments are found then it returns true;
            // else returns zip folder with attachments submitted by the students.
            $table->is_downloading('zip'//$options->download
                    , $filename,
                    $courseshortname . ' ' . format_string($quiz->name, true));
            if ($table->is_downloading()) {
                raise_memory_limit(MEMORY_EXTRA);
            }
            // $hassubmissions = $this->download_proforma_submissions($quiz, $cm, $course, $user_attempts, $data);
        }
        if (!$downloading_submissions | !$hassubmissions) {
            $currentgroup = null;
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $quiz, 'proformasubmexport');
            $this->print_messagees($ds_button_clicked, $cm, $quiz, $OUTPUT, $user_attempts,
                    $hassubmissions, $currentgroup,
                    $hasproformaquestions, $hasstudents);

            // Print the display options.
            $formdata = new stdClass;
            $formdata->id = optional_param('id', $quiz->id, PARAM_INT);
            $formdata->mode = optional_param('mode', 'proformasubmexport', PARAM_ALPHA);
            $this->form->set_data($formdata);
            echo '<div class="plugindescription">' . get_string('plugindescription', 'quiz_proformasubmexport') . '</div>';
            $this->form->display();
        }

        return true;
    }



    /**
     * Are there any proforma type questions in this quiz?
     *
     * @param int $quizid the quiz id.
     */
    /*
        public function quiz_has_proforma_questions($quizid) {
            global $DB;

            return $DB->record_exists_sql("
                SELECT slot.slot,
                       q.id,
                       q.qtype,
                       q.length,
                       slot.maxmark

                  FROM {question} q
                  JOIN {quiz_slots} slot ON slot.questionid = q.id

                 WHERE q.qtype = 'proforma'

              ORDER BY slot.slot", array($quiz->id));
        }
    */
    /**
     *  Get user attempts (quiz attempt alongwith question attempts) : Method 1
     */
    public function get_user_attempts($quiz, $course) {
        global $DB;

        $sql = "SELECT DISTINCT CONCAT(u.id, '#', COALESCE(qa.id, 0)) AS uniqueid,
        				quiza.uniqueid 		AS quizuniqueid,
        				quiza.id 			AS quizattemptid,
        				quiza.attempt 		AS userattemptnum,		/*1*/
        				u.id 				AS userid,
        				u.username,									/*2*/
        				u.idnumber, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.firstname, u.lastname,
        				qa.id 				AS questionattemptid,	/*3*/
        				qa.questionusageid 	AS qubaid,				/*4*/
        				qa.slot,									/*5*/
        				qa.questionid,								/*6*/
        				quiza.state,
        				quiza.timefinish,
        				quiza.timestart,
				        CASE WHEN quiza.timefinish = 0
				        		THEN null
				        	 WHEN quiza.timefinish > quiza.timestart
				        	 	THEN quiza.timefinish - quiza.timestart
				        	 ELSE 0
				        END AS duration

		        FROM		{user} 				u
		        LEFT JOIN 	{quiz_attempts} 	quiza	ON	quiza.userid 		= u.id
		        										AND quiza.quiz 			= $quiz->id
		        JOIN 		{question_attempts} qa 		ON	qa.questionusageid	= quiza.uniqueid		/*7*/
		       /* JOIN 		{user_enrolments} 	ej1_ue 	ON	ej1_ue.userid 		= u.id
		        JOIN 		{enrol} 			ej1_e 	ON	(ej1_e.id 			= ej1_ue.enrolid
														AND ej1_e.courseid 		= $course->id) */

		        WHERE
		        	quiza.preview = 0
		        	AND quiza.id IS NOT NULL
		        	AND 1 = 1
		        	AND u.deleted = 0";
        $user_attempts = $DB->get_records_sql($sql);

        return $user_attempts;
    }

    /**
     * Download a zip file containing quiz proforma submissions.
     *
     * @param object $quiz
     * @param cm $cm
     * @param course $course
     * @param array $student_attempts Array of student's attempts to download proforma submissions in a zip file
     * @return string - If an error occurs, this will contain the error notification.
     */
    protected function download_proforma_submissions($quiz, $cm, $course, $student_attempts, $data) {
        global $CFG;

        // More efficient to load this here.
        require_once($CFG->libdir . '/filelib.php');

        // Increase the server timeout to handle the creation and sending of large zip files.
        core_php_time_limit::raise();

        // Increase memory limit for intermediate data handling.
        raise_memory_limit(MEMORY_EXTRA);

        // Build a list of files to zip.
        $filesforzipping = array();
        // $context = context_course::instance($course->id);

        $dm = new question_engine_data_mapper();

        // Get the file submissions of each attempt.
        foreach ($student_attempts as $attempt) {
            // Get question attempt data.
            $quba = $dm->load_questions_usage_by_activity($attempt->qubaid);
            $qa = $quba->get_question_attempt($attempt->slot);
            $question = $qa->get_question();
            if ($question->get_type_name() != 'proforma') {
                // This is not a ProFormA question => skip.
                continue;
            }

            // Construct download folder name.
            $questionid = 'Q' . $attempt->slot;   // Or use slot number from {quiz_slots} table.

            $prefix1 = str_replace('_', ' ', $questionid);

            $prefix2 = '';
            if (!empty($attempt->idnumber)) {
                $prefix2 .= $attempt->idnumber;
            } else {
                $prefix2 .= $attempt->username;
            }
            $prefix2 .= ' - ' . str_replace('_', ' ', fullname($attempt)) . ' - ' . 'Attempt' . $attempt->userattemptnum . ' - ' .
                    date("Y-m-d g_i a", $attempt->timestart);

            $prefix3 = 'Attempt' . $attempt->userattemptnum . '_';

            // echo 'QUBAID = ' . $attempt->qubaid . PHP_EOL;
            $quba_contextid = $quba->get_owning_context()->id;

            $questionname = $question->name;
            $prefix1 .= ' - ' . $questionname;

            // Get response filename set in question
            $responsefilename = $question->responsefilename;
            if (empty($responsefilename)) {
                $responsefilename = 'unknownfilename.txt';
            }

            // Write question text to a file.
            $questiontextfile = null;
            if ($data->questiontext == 1 && !empty($qa->get_question_summary())) {
                $qttextfilename = '/' . $questionid . ' - ' . $questionname . ' - ' . 'questiontext';
                $questiontextfile = $qa->get_question_summary();
            }

            // Write text response to a file.
            $editortext = null;
            $answer = $qa->get_last_qt_var('answer');
            if (isset($answer)) {
                if (is_string($answer)) {
                    $editortext = $answer;
                } else if (get_class($answer) == 'question_file_loader') {
                    $editortext = $answer->__toString();
                } else {
                    debugging(get_class($answer));
                    $editortext = $answer;
                }
            }

            // Fetch attachments.
            $name = 'attachments';

            // Check if attachments are allowed as response.
            $response_file_areas = $question->qtype->response_file_areas();
            $has_responsefilearea_attachments = in_array($name, $response_file_areas);

            // Check if attempt has submitted any attachment.
            $var_attachments = $qa->get_last_qt_var($name);
            $has_submitted_attachments = (isset($var_attachments));

            // Get files.
            if ($has_responsefilearea_attachments && $has_submitted_attachments) {
                $files = $qa->get_last_qt_files($name, $quba_contextid);
            } else {
                $files = array();
            }

            // Set the download folder hierarchy.
            if ($data->folders == 'questionwise') {
                $pathprefix = $prefix1 . '/' . $prefix2 . '/';
            } else if ($data->folders == 'attemptwise') {
                $pathprefix = $prefix2 . '/' . $prefix1 . '/';
            }

            // Send files for zipping.
            // I. File attachments/submissions.
            $fs_count = 0;
            foreach ($files as $zipfilepath => $file) {
                $fs_count++;
                $zipfilename = $file->get_filename();
                $pathfilename = $pathprefix . $file->get_filepath() . $zipfilename;
                $pathfilename = clean_param($pathfilename, PARAM_PATH);
                $filesforzipping[$pathfilename] = $file;
            }

            // II. text response strings
            if ($editortext != null) {
                switch ($data->editorfilename) {
                    case 'fix':
                        $filename = get_string('editorresponsename', 'quiz_proformasubmexport');
                        break;
                    case 'pathname':
                        $filename = $responsefilename;
                        break;
                    case 'basename':
                        $filename = basename($responsefilename);
                        break;
                    default:
                        throw new coding_exception('invalid case for editorfilename');
                }
                if (empty($filename)) {
                    throw new coding_exception('editorfilename is not set');
                }
                $pathfilename = $pathprefix . '/' . $filename; // 'editorresponse.txt';
                $pathfilename = clean_param($pathfilename, PARAM_PATH);
                $filesforzipping[$pathfilename] = array($editortext);
            }

            // III. question text strings
            if (!empty($files) | $editortext != null) {
                if ($questiontextfile) {
                    if ($data->folders == 'questionwise') {
                        $pathfilename = $prefix1 . '/' . 'questiontext.txt';
                    } else if ($data->folders == 'attemptwise') {
                        $pathfilename = $pathprefix . '/' . 'questiontext.txt';
                    }
                    $pathfilename = clean_param($pathfilename, PARAM_PATH);
                    $filesforzipping[$pathfilename] = array($questiontextfile);
                }
            }
        }

        $this->set_mem('DL');

        if (count($filesforzipping) == 0) {
            return false;
        } else if ($zipfile = $this->pack_files($filesforzipping)) {
            // Construct the zip file name.
            $filename = clean_filename($course->fullname . ' - ' .
                    $quiz->name . ' - ' .
                    $cm->id . '.zip');
            // Send file and delete after sending.
            // echo $this->mem_info;
            send_temp_file($zipfile, $filename);
            // We will not get here - send_temp_file calls exit.
        }

        return true;
    }

    /**
     * Generate zip file from array of given files.
     *
     * @param array $filesforzipping - array of files to pass into archive_to_pathname.
     *                                 This array is indexed by the final file name and each
     *                                 element in the array is an instance of a stored_file object.
     * @return path of temp file - note this returned file does
     *         not have a .zip extension - it is a temp file.
     */
    public function pack_files($filesforzipping) {
        global $CFG;
        // Create path for new zip file.
        $tempzip = tempnam($CFG->tempdir . '/', 'quiz_proforma_submissions_');

        // Zip files.
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
    }

    /**
     * returns true if the quiz object has proforma questions
     * @param $quiz
     * @return bool
     */
    private function has_quiz_proforma_questions($quiz): bool {
        $questions = quiz_report_get_significant_questions($quiz);

        // Check if the quiz contains proforma type questions.
        $hasproformaquestions = false;
        if ($questions) {
            foreach ($questions as $question) {
                if ($question->qtype == 'proforma') {
                    $hasproformaquestions = true;
                    break;
                }
            }
        }
        return $hasproformaquestions;
    }

    /**
     * @param bool $ds_button_clicked
     * @param stdClass $quiz
     * @param stdClass $OUTPUT
     * @param mod_quiz_attempts_report_options $user_attempts
     * @param int $hassubmissions
     * @param bool $hasproformaquestions
     * @param bool $hasstudents
     * @throws coding_exception
     */
    protected function print_messagees($ds_button_clicked, $cm, $quiz, $OUTPUT, $user_attempts, $hassubmissions,
            $currentgroup, bool $hasproformaquestions, bool $hasstudents): void {
        // Print information on the number of existing attempts.
        if ($strattemptnum = quiz_num_attempt_summary($quiz, $cm, true, $currentgroup)) {
            echo '<div class="quizattemptcounts">' . $strattemptnum . '</div>';
        }

        if ($ds_button_clicked) {
            if (!quiz_has_questions($quiz->id)) {
                echo $OUTPUT->notification(get_string('noquestions', 'quiz_proformasubmexport'));
            } else if (!$hasstudents) {
                echo $OUTPUT->notification(get_string('nostudentsyet'));
                // 	            } else if ($currentgroup && !$this->hasgroupstudents) {
                // 	                echo $OUTPUT->notification(get_string('nostudentsingroup'));
            } else if (!$hasproformaquestions) {
                echo $OUTPUT->notification(get_string('noproformaquestion', 'quiz_proformasubmexport'));
            } else if (!$user_attempts) {
                echo $OUTPUT->notification(get_string('noattempts', 'quiz_proformasubmexport'));
            } else if (!$hassubmissions) {
                echo $OUTPUT->notification(get_string('nosubmission', 'quiz_proformasubmexport'));
            }
        }
    }
}