<?php
// This file is part of ProFormA Question Type for Moodle
//
// ProFormA Question Type for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// ProFormA Question Type for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat extensions for proforma
 *
 * @package    qtype_proforma
 * @copyright  2019 Ostfalia Hochschule fuer angewandte Wissenschaften
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/../../../../../../mod/quiz/tests/behat/behat_mod_quiz.php');

use Behat\Gherkin\Node\TableNode as TableNode;

use Behat\Mink\Exception\ExpectationException as ExpectationException;

class behat_proformasubmexport extends behat_mod_quiz {

    /**
     * Attempt a quiz.
     *
     * The first row should be column names:
     * | slot | actualquestion | variant | response |
     * The first two of those are required. The others are optional.
     *
     * slot           The slot
     * actualquestion This column is optional, and is only needed if the quiz contains
     *                random questions. If so, this will let you control which actual
     *                question gets picked when this slot is 'randomised' at the
     *                start of the attempt. If you don't specify, then one will be picked
     *                at random (which might make the response meaningless).
     *                Give the question name.
     * variant        This column is similar, and also options. It is only needed if
     *                the question that ends up in this slot returns something greater
     *                than 1 for $question->get_num_variants(). Like with actualquestion,
     *                if you specify a value here it is used the fix the 'random' choice
     *                made when the quiz is started.
     * response       The response that was submitted. How this is interpreted depends on
     *                the question type. It gets passed to
     *                {@link core_question_generator::get_simulated_post_data_for_question_attempt()}
     *                and therefore to the un_summarise_response method of the question to decode.
     *
     * Then there should be a number of rows of data, one for each question you want to add.
     * There is no need to supply answers to all questions. If so, other qusetions will be
     * left unanswered.
     *
     * @param string $username the username of the user that will attempt.
     * @param string $quizname the name of the quiz the user will attempt.
     * @param TableNode $attemptinfo information about the questions to add, as above.
     *
     * @Given /^user "([^"]*)" has attempted "([^"]*)" with text responses:$/
     */
    public function user_has_attempted_with_text_responses($username, $quizname, TableNode $attemptinfo) {
        global $DB;

        /** @var mod_quiz_generator $quizgenerator */
        $quizgenerator = behat_util::get_data_generator()->get_plugin_generator('mod_quiz');

        $quizid = $DB->get_field('quiz', 'id', ['name' => $quizname], MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        list($forcedrandomquestions, $forcedvariants) =
            $this->extract_forced_randomisation_from_attempt_info($attemptinfo);
        $responses = $this->extract_text_responses_from_attempt_info($attemptinfo);

        $this->set_user($user);

        $attempt = $quizgenerator->create_attempt($quizid, $user->id,
            $forcedrandomquestions, $forcedvariants);

        $quizgenerator->submit_responses($attempt->id, $responses, false, true);

        $this->set_user();
    }

    /**
     * Helper used by user_has_attempted_with_responses, user_has_checked_answers_in_their_attempt_at_quiz,
     * user_has_input_answers_in_their_attempt_at_quiz, etc.
     *
     * @param TableNode $attemptinfo data table from the Behat step
     * @return array of responses that can be passed to $quizgenerator->submit_responses.
     */
    protected function extract_text_responses_from_attempt_info(TableNode $attemptinfo) {
        $responses = [];
        foreach ($attemptinfo->getHash() as $slotinfo) {
            if (empty($slotinfo['slot'])) {
                throw new ExpectationException('When simulating a quiz attempt, ' .
                    'the slot column is required.', $this->getSession());
            }
            if (!array_key_exists('response', $slotinfo)) {
                throw new ExpectationException('When simulating a quiz attempt, ' .
                    'the response column is required.', $this->getSession());
            }
            if (!array_key_exists('answerformat', $slotinfo)) {
                throw new ExpectationException('When simulating a text quiz attempt, ' .
                    'the answerformat column is required.', $this->getSession());
            }
            $response = [$slotinfo['response'], $slotinfo['answerformat']];
            $responses[$slotinfo['slot']] = $response;
        }
        return $responses;
    }
}
