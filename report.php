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
 * @author	  Kashmira Nagwekar, K.Borm (Ostfalia)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/proformasubmexport/proformasubmexport_form.php');
require_once($CFG->libdir . '/filestorage/zip_archive.php');

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

        // Check if the quiz contains proforma or essay type questions.
        // Method 1 : Check $questions object for existence proforma type questions
        $hasproformaquestions = false;
        if ($questions) {
	        foreach ($questions as $question) {
                if ($question->qtype == 'proforma' || $question->qtype == 'essay' || $question->qtype == 'random') {
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
        // Construct the zip file name.
        $ziptmpfilename = tempnam('/tmp', 'proformaexport') . '.zip';
        $ziparchive = new zip_archive();
        // debugging($ziptmpfilename);
        if (!$ziparchive->open($ziptmpfilename)) {
            debugging('cannot create zip file ' . $ziptmpfilename);
            return false;
        }
        // $counter = 0;
    	// Get the file submissions of each student.
    	foreach ($student_attempts as $student) {
    		// echo 'Attempt ' . $counter;
    		// $counter++;

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
		    $qtype = $qa->get_question()->get_type_name();
    		if ($qtype == 'proforma' || $qtype == 'essay' || $qtype == 'random') {
    		    $questionname = $qa->get_question()->name;
    		    $prefix1 .= ' - ' . $questionname;

                // Get response filename set in question
                $responsefilename = get_string('editorresponsename', 'quiz_proformasubmexport');
                if (!empty($qa->get_question()->responsefilename)) {
                    $responsefilename = $qa->get_question()->responsefilename;
                }

    		    // Writing question text to a file.
                $questiontextfile = null;
    		    if ($data->questiontext == 1) {
        		    if(!empty($qa->get_question_summary())) {
//     		        if(!empty($qa->get_question()->questiontext)) {
        		        $qttextfilename = '/' . $questionid . ' - ' . $questionname . ' - ' . 'questiontext';
                        $questiontextfile = $qa->get_question_summary();

        		    }
    		    }


    		    // Writing text response to a file.
                $editortext = null;
                {
                    $answer = $qa->get_last_qt_var('answer');
                    if (isset($answer)) {
                        if (is_string($answer))
                            $editortext = $answer;
                        else if (get_class($answer) == 'question_file_loader')
                            $editortext = $answer->__toString();
                        else {
                            debugging(get_class($answer));
                            $editortext = $answer;
                        }
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
                    // file is stored_file
                    $file->archive_file($ziparchive, $pathfilename);
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
                        case 'noeditor':
                            $editortext = null;
                            break;
                        default:
                            throw new coding_exception('invalid case for editorfilename');
                    }
                    if (!empty($editortext)) {
                        if (empty($filename)) {
                            throw new coding_exception('editorfilename is not set');
                        }                        $pathfilename = $pathprefix . '/' . $filename;
                        $pathfilename = clean_param($pathfilename, PARAM_PATH);
                        $ziparchive->add_file_from_string($pathfilename, $editortext);
                    }
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
    	    		    $ziparchive->add_file_from_string($pathfilename, $questiontextfile);
    	    		}
	    		}
    		}
    	}

        $ziparchive->close();
        $zipfilename = clean_filename($course->fullname . ' - ' .
                $quiz->name . '.zip');

        send_temp_file($ziptmpfilename, $zipfilename);
    }

}