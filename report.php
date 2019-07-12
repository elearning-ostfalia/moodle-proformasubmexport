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
 * @author	  Kashmira Nagwekar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/proformasubmexport/proformasubmexport_form.php');

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

	public function display($quiz, $cm, $course) {
        global $OUTPUT, $DB;

        $mform = new quiz_proformasubmexport_settings_form();

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Check if the quiz contains proforma type questions.
        // Method 1 : Check $questions object for existence proforma type questions
        $hasproformaquestions = false;
        if ($questions) {
	        foreach ($questions as $question) {
	        	if ($question->qtype == 'proforma') {
	        		$hasproformaquestions = true;
	        		break;
	        	}
	        }
        }
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
        if ($data = $mform->get_data()){
        	if ($ds_button_clicked = !empty($data->proformasubmexport)) {
        		$user_attempts = $this->get_user_attempts($quiz, $course);
	        	$downloading_submissions = $this->downloading_submissions($ds_button_clicked, $hasproformaquestions, $user_attempts);

	           	// Download file submissions for proforma questions.
	        	if ($downloading_submissions) {
	        	    // If no attachments are found then it returns true;
	        	    // else returns zip folder with attachments submitted by the students.
	        	    $hassubmissions = $this->download_proforma_submissions($quiz, $cm, $course, $user_attempts, $data);
	        	}
	        }
        }

        // Start output.
        if (!$downloading_submissions | !$hassubmissions) {
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $quiz, 'proformasubmexport');
        }

        $currentgroup = null;
        // Print information on the number of existing attempts.
        if (!$downloading_submissions | !$hassubmissions) {
            // Do not print notices when downloading.
            if ($strattemptnum = quiz_num_attempt_summary($quiz, $cm, true, $currentgroup)) {
                echo '<div class="quizattemptcounts">' . $strattemptnum . '</div>';
            }
        }

        $hasquestions = quiz_has_questions($quiz->id);

        if (!$downloading_submissions | !$hassubmissions) {
        	if ($ds_button_clicked) {
	        	if (!$hasquestions) {
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

            // Print the form.
            $formdata = new stdClass;
            $formdata->id = optional_param('id', $quiz->id, PARAM_INT);
            $formdata->mode = optional_param('mode', 'proformasubmexport', PARAM_ALPHA);
            $mform->set_data($formdata);
            echo '<div class="plugindescription">' . get_string('plugindescription', 'quiz_proformasubmexport'). '</div>';
            $mform->display();
        }

        return true;
    }

    public function downloading_submissions($ds_button_clicked, $hasproformaquestions, $user_attempts) {
    	global $DB;
    	if ($ds_button_clicked && $hasproformaquestions && $user_attempts) {
    		return true;
    	} else {
    		return false;
    	}
    }

    /**
     * Are there any proforma type questions in this quiz?
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
    public function get_user_attempts($quiz, $course){
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
/*
    protected function create_draft_area($contextid) {
        $draftid = 0;
        //$contextid = 0;

        $component = 'quiz_proformasubmexport';
        $filearea = 'filecontent';

        // Create an empty file area.
        file_prepare_draft_area($draftid, $contextid, $component, $filearea, null);
        return $draftid;
    }

    protected function create_file_in_draft_area($contextid, $draftid, $filename, $content) {
        global $USER;

        $fs = get_file_storage();

        // Create the file in the provided draft area.
        $fileinfo = array(
                'contextid' => $contextid,
                'component' => 'user',
                'filearea'  => 'draft',
                'itemid'    => $draftid,
                'filepath'  => '/',
                'filename' => $filename,
        );
        return $fs->create_file_from_string($fileinfo, $content);
    }
*/

    /**
     * Download a zip file containing quiz proforma submissions.
     *
     * @param object $quiz
     * @param cm $cm
     * @param course $course
     * @param array $student_attempts Array of student's attempts to download proforma submissions in a zip file
     * @return string - If an error occurs, this will contain the error notification.
     */
    protected function download_proforma_submissions($quiz, $cm, $course, $student_attempts, $data = null) {
    	global $CFG;

    	// More efficient to load this here.
    	require_once($CFG->libdir.'/filelib.php');

    	// Increase the server timeout to handle the creation and sending of large zip files.
    	core_php_time_limit::raise();

    	// Build a list of files to zip.
    	$filesforzipping = array();
    	$fs = get_file_storage();
    	$context = context_course::instance($course->id);
        //$draftid = $this->create_draft_area($context->id);

    	// Construct the zip file name.
    	$filename = clean_filename($course->fullname . ' - ' .
    			$quiz->name . ' - ' .
    			$cm->id . '.zip');

    	// Get the file submissions of each student.
    	foreach ($student_attempts as $student) {

    		// Construct download folder name.
    		$userid = $student->userid;
    		$questionid = 'Q' . $student->slot;   // Or use slot number from {quiz_slots} table.

    		$prefix1 = str_replace('_', ' ', $questionid);

    		$prefix2 = '';
    		if(!empty($student->idnumber)) {
    			$prefix2 .= $student->idnumber;
    		} else {
    			$prefix2 .= $student->username;
    		}
    		$prefix2 .= ' - ' . str_replace('_', ' ', fullname($student)) . ' - ' . 'Attempt' . $student->userattemptnum . ' - '. date("Y-m-d g_i a", $student->timestart);

    		$prefix3 = 'Attempt' . $student->userattemptnum . '_';

    		// Get question attempt and question context id.
    		$dm = new question_engine_data_mapper();
    		$quba = $dm->load_questions_usage_by_activity($student->qubaid);
    		$qa = $quba->get_question_attempt($student->slot);
    		$quba_contextid = $quba->get_owning_context()->id;

    		if ($qa->get_question()->get_type_name() == 'proforma') {
    		    $questionname = $qa->get_question()->name;
    		    $prefix1 .= ' - ' . $questionname;

    		    $qa->get_question();  // Question object. (Has qt related info like responserequired, attachmentsrequired etc.)

    		    // Writing question text to a file.
                $questiontextfile = null;
    		    if ($data->questiontext == 1) {
        		    if(!empty($qa->get_question_summary())) {
//     		        if(!empty($qa->get_question()->questiontext)) {
        		        $qttextfilename = '/' . $questionid . ' - ' . $questionname . ' - ' . 'questiontext';
                        $questiontextfile = $qa->get_question_summary();
//                        $questiontextfile = $this->create_file_in_draft_area($context->id, $draftid,
//                                $qttextfilename . '.text', $qa->get_question_summary());

        		    }
    		    }


    		    // Writing text response to a file.
                $editortext = null;
    		    if ($data->textresponse == 1) {
                    $answer = $qa->get_last_qt_var('answer');
                    if (isset($answer)) {
        		        //$textfilename = '/' . $prefix1 . ' - ' . $prefix2 . ' - ' . 'textresponse';
                        $editortext = $answer->__toString();
        		    }
    		    }

    		    // Fetching attachments.
    			$name = 'attachments';

    			// Check if attachments are allowed as response.
    			$response_file_areas = $qa->get_question()->qtype->response_file_areas();
                $has_responsefilearea_attachments = in_array($name, $response_file_areas);

    			// Check if student has submitted any attachment.
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
        			//$prefixedfilename = clean_filename($prefix1 . '/' . $prefix2);
        			$pathprefix = $prefix1 . '/' . $prefix2 . '/';
    			} else if ($data->folders == 'attemptwise') {
    			    //$prefixedfilename = clean_filename($prefix2 . '/' . $prefix1);
    			    $pathprefix = $prefix2 . '/' . $prefix1 . '/';
    			}

    			// Send files for zipping.
    			// I. File attachments/submissions.
    			$fs_count = 0;
	    		foreach ($files as $zipfilepath => $file) {
	    		    $fs_count++;
	    			$zipfilename = $file->get_filename();
	    			$pathfilename = $pathprefix . $file->get_filepath() . $prefix3 . 'filesubmission' . '_' . $zipfilename;
	    			$pathfilename = clean_param($pathfilename, PARAM_PATH);
	    			$filesforzipping[$pathfilename] = $file;
	    		}

	    		// II. text response strings
	    		if ($editortext != null) {
	    		    $pathfilename = $pathprefix . '/' . $prefix3 . 'textresponse';
	    		    $pathfilename = clean_param($pathfilename, PARAM_PATH);
	    		    $filesforzipping[$pathfilename] = array($editortext);
	    		}

	    		// III. question text strings
	    		if (!empty($files) | $editortext != null) {
    	    		if ($questiontextfile) {
//    	    		    $zipfilename = $questiontextfile->get_filename();
    // 	    		    $pathfilename = $pathprefix . $textfile->get_filepath() . $prefix3 . $zipfilename;

    	    		    if ($data->folders == 'questionwise') {
    	    		        $pathfilename = $prefix1 . '/' . 'Question text';
    	    		    } else if ($data->folders == 'attemptwise') {
    	    		        $pathfilename = $pathprefix . '/' . 'Question text';
    	    		    }
    	    		    $pathfilename = clean_param($pathfilename, PARAM_PATH);
    	    		    $filesforzipping[$pathfilename] = array($questiontextfile);
    	    		}
	    		}
    		}
    	}

    	if (count($filesforzipping) == 0) {
    	    return false;
    	} else if ($zipfile = $this->pack_files($filesforzipping)) {
    		// Send file and delete after sending.
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
}