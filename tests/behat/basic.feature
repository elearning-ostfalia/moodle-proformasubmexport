@mod @mod_quiz @quiz @quiz_reponses
Feature: Basic use of the Proforma Submission export
  In order to see how my students are progressing
  As a teacher
  I need to see all their quiz responses

  Background: Create quiz and responses
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher  | The       | Teacher  |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour      |
      | quiz       | Quiz 1 | Quiz 1 description | C1     | quiz1    | adaptiveexternalgrading |
    And the following "questions" exist:
      | questioncategory | qtype    | name          | template |
      | Test questions   | essay    | essay-text    | editor   |
      | Test questions   | essay    | essay-file    | editorfilepicker   |
      | Test questions   | proforma | proforma-text | editor   |
      | Test questions   | proforma | proforma-file | filepicker   |
      | Test questions   | proforma | proforma-ide  | explorer   |

    # Bug in essay questions:
    # un_summarize does not return a valid response for input for summarize.
    # So essay questions cannot be tested this way.
    And quiz "Quiz 1" contains the following questions:
      | question      | page | maxmark |
#      | essay-text    | 1    | 3.0     |
#      | essay-file    | 1    | 3.0     |
      | proforma-text | 1    | 3.0     |
      | proforma-file | 1    | 3.0     |
      | proforma-ide  | 1    | 3.0     |

  @javascript
  Scenario: Export responses when there are no attempts
    Given I am on the "Quiz 1" "quiz activity" page logged in as teacher
    When I navigate to "Results > Responses" in current page administration
    Then I should see "Attempts: 0"
    And I should see "Nothing to display"
    And I set the field "Attempts from" to "enrolled users who have not attempted the quiz"

  @javascript
  Scenario: Export responses works when there are attempts
    Given user "student1" has started an attempt at quiz "Quiz 1"
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | response 1  |
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | reponse 2   |
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | response 3   |
    And user "student1" has finished an attempt at quiz "Quiz 1"

    Given user "student2" has started an attempt at quiz "Quiz 1"
    Given user "student2" has attempted "Quiz 1" with responses:
      | slot | response |
      |   1  | response from student 2  |
    And user "student2" has finished an attempt at quiz "Quiz 1"

    And I am on the "Quiz 1" "quiz activity" page logged in as teacher
    And I navigate to "Results > Download Essay and ProFormA submissions" in current page administration
    And I pause
    Then I should see "Attempts: 4"
    And I should see "Student One"
    And I should not see "Student Two"
    And I set the field "Attempts from" to "enrolled users who have, or have not, attempted the quiz"
    And I set the field "Which tries" to "All tries"
    And I press "Show report"
    And "Student OneReview attempt" row "Response 1Sort by Response 1 Ascending" column of "responses" table should contain "1.0"
    And "Student OneReview attempt" row "State" column of "responses" table should contain ""
    And "Finished" row "Grade/100.00Sort by Grade/100.00 Ascending" column of "responses" table should contain "33.33"
    And "Finished" row "Response 1Sort by Response 1 Ascending" column of "responses" table should contain "3.14"
    And "Student Two" row "State" column of "responses" table should contain "-"
    And "Student Two" row "Response 1Sort by Response 1 Ascending" column of "responses" table should contain "-"

  @javascript
  Scenario: Export responses does not allow strange combinations of options
    Given I am on the "Quiz 1" "quiz activity" page logged in as teacher
    And I navigate to "Results > Responses" in current page administration
    And the "Which tries" "select" should be enabled
    When I set the field "Attempts from" to "enrolled users who have not attempted the quiz"
    Then the "Which tries" "select" should be disabled
