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


namespace quiz_responses;

defined('MOODLE_INTERNAL') || die();

use question_bank ;
use quiz_attempt;

define('UNITTEST_IS_RUNNING', true);

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/tests/attempt_walkthrough_from_csv_test.php');
// require_once($CFG->dirroot . '/mod/quiz/report/default.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/report.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/proformasubmexport/report.php');



/**
 * Quiz attempt walk through using data from csv file.
 *
 * CSV data files for these tests were generated using :
 * https://github.com/jamiepratt/moodle-quiz-tools/tree/master/responsegenerator *
 *
 * @package    quiz_responses
 * @category   test
 * @copyright  2013 The Open University
 * @author     Jamie Pratt <me@jamiep.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proformasubmexport_from_steps_walkthrough_test extends \mod_quiz\tests\attempt_walkthrough_testcase {
    const delete_tmp_archives = false;

    protected $slots = null;

    protected $files = array('questions', 'steps', 'responses');

    /**
     * @param $filenamearchive
     * @param $csvdata
     * @param string $editorfilename
     * @param \stdClass $data
     * @param $i
     */
    protected function checkZipContent($filenamearchive, $csvdata, string $editorfilename, \stdClass $data): void
    {
        $archive = new \ZipArchive();
        $archive->open($filenamearchive);
        $countMatches = 0; // count number of matching files.
        $countSteps = 0;

        foreach ($csvdata['steps'] as $stepsfromcsv) {
            $steps = $this->explode_dot_separated_keys_to_make_subindexs($stepsfromcsv);

            foreach ($steps['responses'] as $index => $answer) {

                switch ($this->slots[$index]->options->responseformat) {
                    case 'editor':
                        if ('noeditor' != $editorfilename) {
                            $countSteps++;
                            if (!$this->find_answer($steps, $index, $answer, $archive, $data)) {
                                $countMatches++;
                            }
                        }
                        break;
                    case 'filepicker':
                    case 'explorer':
                        $countSteps++;
                        break;
                    default:
                        throw new \coding_exception('invalid proforma subtype ' . $this->slots[$index]->options->responseformat);
                }

                if ($this->find_answer($steps, $index, $answer, $archive, $data)) {
                    $countMatches++;
                    $this->assertEquals($data->questiontext, $this->find_questiontext($steps, $index, $answer, $archive, $data, $this->slots[$index]));
                }
            }
        }

        $this->assertEquals($countSteps, $countMatches);
        // Note: Two attempts come from qtype_proforma - Test helper
        $this->assertTrue($archive->numFiles >= $countMatches);
/*
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $filename = $archive->getNameIndex($i);
            $filecontent = $archive->getFromName($filename);
            // Dump first file name and content.
            var_dump($filename);
            var_dump($filecontent);
            break;
        }
*/
        if (self::delete_tmp_archives) {
            unlink($filenamearchive);
        }
    }

    protected static function get_full_path_of_csv_file(string $setname, string $test): string {
        // Overridden here so that __DIR__ points to the path of this file.
        return  __DIR__."/fixtures/{$setname}{$test}.csv";
    }


    /**
     * Helper method: Store a test file with a given name and contents in a
     * draft file area.
     *
     * @param int $context context.
     * @param int $draftitemid draft item id.
     * @param string $contents file contents.
     * @param string $filename filename.
     */
    protected function upload_file($context, $draftitemid, $contents, $filename = 'MyString.java') {
        $fs = get_file_storage();

        $filerecord = new \stdClass();
        $filerecord->contextid = $context->id;
        $filerecord->component = 'user';
        $filerecord->filearea = 'draft';
        $filerecord->itemid = $draftitemid;
        $filerecord->filepath = '/';
        $filerecord->filename = $filename;

        // print_r($filerecord);
        $fs->create_file_from_string($filerecord, $contents);
        return $draftitemid;
    }


    /**
     * @param $steps array the step data from the csv file.
     * @return array attempt no as in csv file => the id of the quiz_attempt as stored in the db.
     */
    protected function my_walkthrough_attempts($steps) {
        global $DB;
        $attemptids = array();
        foreach ($steps as $steprow) {

            $step = $this->explode_dot_separated_keys_to_make_subindexs($steprow);
            // Find existing user or make a new user to do the quiz.
            $username = array('firstname' => $step['firstname'],
                'lastname'  => $step['lastname']);

            if (!$user = $DB->get_record('user', $username)) {
                $user = $this->getDataGenerator()->create_user($username);
            }

            global $USER;
            // Change user.
            $USER = $user;

            if (!isset($attemptids[$step['quizattempt']])) {
                // Start the attempt.
                $quizobj = \quiz::create($this->quiz->id, $user->id);
                if ($quizobj->has_questions()) {
                    $quizobj->load_questions();
                }
                $this->slots = [];
                foreach ($quizobj->get_questions() as $question) {
                    $this->slots[$question->slot] = $question;
                }

                $usercontext = \context_user::instance($user->id);
                foreach ($step['responses'] as $slot => &$response) { // slot or question??
                    $type = $this->slots[$slot]->qtype;
                    switch ($type) {
                        case 'proforma':
                            // Check for filepicker and explorer
                            switch ($this->slots[$slot]->options->responseformat) {
                                case 'editor':
                                    break;
                                case 'filepicker':
                                case 'explorer':
                                    $attachementsdraftid = file_get_unused_draft_itemid();
                                    $response['attachments'] = $this->upload_file($usercontext
                                        /*$quizobj->get_context()*/, $attachementsdraftid, $response['answer']);
                                    unset($response['answer']);
                                    break;
                                default:
                                    throw new \coding_exception('invalid proforma subtype ' . $this->slots[$slot]->options->responseformat);
                            }
                            break;
                        case 'essay':
                            $response['answerformat'] = FORMAT_PLAIN;
                            break;
                    }
                }


                $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', /* $usercontext*/ $quizobj->get_context());
                $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

                $prevattempts = quiz_get_user_attempts($this->quiz->id, $user->id, 'all', true);
                $attemptnumber = count($prevattempts) + 1;
                $timenow = time();
                $attempt = quiz_create_attempt($quizobj, $attemptnumber, false, $timenow, false, $user->id);
                // Select variant and / or random sub question.
                if (!isset($step['variants'])) {
                    $step['variants'] = array();
                }
                if (isset($step['randqs'])) {
                    // Replace 'names' with ids.
                    foreach ($step['randqs'] as $slotno => $randqname) {
                        $step['randqs'][$slotno] = $this->randqids[$slotno][$randqname];
                    }
                } else {
                    $step['randqs'] = array();
                }

                quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow, $step['randqs'], $step['variants']);
                quiz_attempt_save_started($quizobj, $quba, $attempt);
                // \question_engine::save_questions_usage_by_activity($quba);
                $attemptid = $attemptids[$step['quizattempt']] = $attempt->id;
            } else {
                $attemptid = $attemptids[$step['quizattempt']];
            }



            // Process some responses from the student.
            $attemptobj = quiz_attempt::create($attemptid);
            $attemptobj->process_submitted_actions($timenow, false, $step['responses']);

            // Finish the attempt.
            if (!isset($step['finished']) || ($step['finished'] == 1)) {
                $attemptobj = quiz_attempt::create($attemptid);
                $attemptobj->process_finish($timenow, false);
            }
        }
        return $attemptids;
    }

    /**
     * Create a quiz add questions to it, walk through quiz attempts and then check results.
     *
     * @param array $quizsettings settings to override default settings for quiz created by generator. Taken from quizzes.csv.
     * @param array $csvdata of data read from csv file "questionsXX.csv", "stepsXX.csv" and "responsesXX.csv".
     * @dataProvider get_data_for_walkthrough
     */
    public function test_walkthrough_from_csv($quizsettings, $csvdata): void {

        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->create_quiz($quizsettings, $csvdata['questions']);

        $quizattemptids = $this->my_walkthrough_attempts($csvdata['steps']);

        foreach ($csvdata['responses'] as $responsesfromcsv) {
            $responses = $this->explode_dot_separated_keys_to_make_subindexs($responsesfromcsv);

            if (!isset($quizattemptids[$responses['quizattempt']])) {
                throw new \coding_exception("There is no quizattempt {$responses['quizattempt']}!");
            }
            $this->assert_response_test($quizattemptids[$responses['quizattempt']], $responses);
        }

        $report = new \quiz_proformasubmexport_report();
        // call of protected method $report->download_proforma_submissions
        $r = new \ReflectionMethod('\quiz_proformasubmexport_report', 'download_proforma_submissions');
        $r->setAccessible(true);
        $cm = null; // unused.
        global $DB;
        $course = $DB->get_record('course', array('id' => $this->quiz->course));
        $user_attempts = $report->get_user_attempts($this->quiz, $course);

        // Possible combinations
        $questionstexts = [0, 1];
        $folders = ['questionwise', 'attemptwise'];
        $editorfilenames = ['pathname', 'fix', 'pathname', 'noeditor'];

        foreach ($editorfilenames as $editorfilename) {
            foreach ($questionstexts as $questionstext) {
                foreach ($folders as $folder) {
                    $data = new \stdClass();
                    $data->folders = $folder;
                    $data->questiontext = $questionstext;
                    $data->editorfilename = $editorfilename;
                    // print_r($data);

                    // Create zip.
                    $filenamearchive = $r->invoke($report, $this->quiz, $cm, $course, $user_attempts, $data);
                    // echo $filenamearchive;

                    // Check zip content.
                    $this->checkZipContent($filenamearchive, $csvdata, $editorfilename, $data);
                }
            }
        }
    }

    protected function find_answer($steps, $questionindex, $answer, $archive, $data) {
        $question = 'Q' . $questionindex;
        // var_dump($question);
        $path = $steps['firstname'] . ' ' . $steps['lastname'] . ' - Attempt1';
        // var_dump($path);
//              $path = 'username' . ' - ' . $steps['firstname'] . ' ' . $steps['lastname'] . ' - Attempt';
        $content = $answer['answer'];

        for( $i = 0; $i < $archive->numFiles; $i++ ) {
            $filename = $archive->getNameIndex($i);
            if (strpos($filename, $question) === false) {
                continue;
            }
            if (strpos($filename, $path) === false) {
                continue;
            }
            // filename found => check content.
            // var_dump($filename);
            $filecontent = $archive->getFromName($filename);
            // var_dump($filecontent);
            $this->assertEquals($filecontent, $content);
            return true;

            // $stat = $archive->statIndex( $i );
            // print_r( basename( $stat['name'] ) . PHP_EOL );
        }

        return false;
    }

    protected function find_questiontext($steps, $questionindex, $answer, $archive, $data, $questionobj) {
        $question = 'Q' . $questionindex;
        // var_dump($question);
        $path = $steps['firstname'] . ' ' . $steps['lastname'] . ' - Attempt1';
        // var_dump($path);
//              $path = 'username' . ' - ' . $steps['firstname'] . ' ' . $steps['lastname'] . ' - Attempt';
        $content = $answer['answer'];

        for( $i = 0; $i < $archive->numFiles; $i++ ) {
            $filename = $archive->getNameIndex($i);
            if (strpos($filename, 'questiontext.txt') === false) {
                continue;
            }

            if (strpos($filename, $question) === false) {
                continue;
            }

            // Attempt path is only in filepath if folders are attemptwise
            if ((strpos($filename, $path) > 0) != ($data->folders == 'attemptwise')) {
                continue;
            }

            // filename found => check content.
            // var_dump($filename);
            $filecontent = $archive->getFromName($filename);
            // var_dump($filecontent);
            $this->assertEquals($questionobj->questiontext, $filecontent);
            return true;

            // $stat = $archive->statIndex( $i );
            // print_r( basename( $stat['name'] ) . PHP_EOL );
        }

        return false;
    }

    protected function assert_response_test($quizattemptid, $responses) {
        $quizattempt = quiz_attempt::create($quizattemptid);

        foreach ($responses['slot'] as $slot => $tests) {
            $slothastests = false;
            foreach ($tests as $test) {
                if ('' !== $test) {
                    $slothastests = true;
                }
            }
            if (!$slothastests) {
                continue;
            }
            $qa = $quizattempt->get_question_attempt($slot);
            $stepswithsubmit = $qa->get_steps_with_submitted_response_iterator();
            // var_dump($stepswithsubmit);
            $step = $stepswithsubmit[$responses['submittedstepno']];
            if (null === $step) {
                throw new \coding_exception("There is no step no {$responses['submittedstepno']} ".
                                           "for slot $slot in quizattempt {$responses['quizattempt']}!");
            }
            foreach (array('responsesummary', 'fraction', 'state') as $column) {
                if (isset($tests[$column]) && $tests[$column] != '') {
                    switch($column) {
                        case 'responsesummary' :
                            $actual = $qa->get_question()->summarise_response($step->get_qt_data());
                            break;
                        case 'fraction' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the fraction after the question has been
                                // finished.
                                $actual = $qa->get_fraction();
                            } else {
                                $actual = $step->get_fraction();
                            }
                           break;
                        case 'state' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the state after the question has been
                                // finished.
                                $state = $qa->get_state();
                            } else {
                                $state = $step->get_state();
                            }
                            $actual = substr(get_class($state), strlen('question_state_'));
                    }
                    $expected = $tests[$column];
                    $failuremessage = "Error in  quizattempt {$responses['quizattempt']} in $column, slot $slot, ".
                    "submittedstepno {$responses['submittedstepno']}";
                    $this->assertEquals($expected, $actual, $failuremessage);
                }
            }
        }
    }
}
