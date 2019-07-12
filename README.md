# moodle-proformasubmexport

#### Moodle Quiz Report Plugin for downloading ProFormA submissions. 

The ProFormA Submission Export ()proformasubmexport) plugin offers users a convenient way by which teachers can download 
submissions in response to quiz ProForma questions.  

#### Installation
* The plugin folder ‘proformasubmexport’ is to be added under ‘moodle/mod/quiz/report’ directory.

#### How to use?
 * Go to a particular quiz.

 * Click on 'Settings' icon.

 * The plugin ‘Download ProFromA submissions’ link will appear under ‘Results’ section. Click on it.

 * The teacher needs to click on the button ‘Download ProFromA submissions’.

 * On clicking this button, the teacher will get a zip file consisting of files submitted by students in response to the quiz ProFromA questions.
 
 * The hierarchy of folders present in the downloaded zip file is explained through an example.
 
 <b> Example: Quiz Scenario </b>
 
 A Quiz has an ProFromA question (Question No.: 3).
 
 A student (Student name: Anisha Patki, Username: anisha) attempts the quiz twice, each time attaching a response file to 
 that particular ProFromA question.
 
 The files submitted by the student as responses are:
  - First Attempt - Answer.java
  - Second attempt - Answer.java
 
 Now, in the downloaded zip file, the folder hierarchy for this particular student's response files is as follows: 
 - Q8 - OOP Concept / anisha - Anisha Patki / Attempt1 / Answer.java
 - Q8 - OOP Concept / anisha - Anisha Patki / Attempt2 / Answer.java
 
 (<b>Note:</b> Here, in 'Q8', '8' is the database question id for that particular question and may not match the 
 question no. as it appeared in the quiz as shown in the example above.)
 
 
#### Usage

Through this feature, now teachers will be able to download/save all submissions of all attempts 
in response to the quiz ProFromA questions at one time.