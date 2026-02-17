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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../deferredfeedback/behaviour.php');

/**
 * Question behaviour for remember correct mode.
 *
 * @package    qbehaviour_remembercorrect
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @copyright  2026, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_remembercorrect extends qbehaviour_deferredfeedback {
    /** @var bool Whether a manual grade copy is in process. */
    protected bool $copyinprocess = false;

    /**
     * Handles both automatically gradeable answers and manual graded.
     * @param question_definition $question the question.
     */
    public function is_compatible_question(question_definition $question) {
        return $question instanceof question_automatically_gradable
            || $question instanceof question_with_responses;
    }

    /**
     * Adjusts the display options to lock correct answers.
     * @param question_display_options $options the options to adjust.
     */
    public function adjust_display_options(question_display_options $options) {
        parent::adjust_display_options($options);

        if ($this->qa->get_state()->is_active() && $this->is_previous_attempt_correct()) {
            $options->readonly = true;
            $options->correctness = question_display_options::VISIBLE;
            $options->extrainfocontent .= get_string('previouscorrect', 'qbehaviour_remembercorrect');
        }
    }

    /**
     * Gets the right answer summary.
     * @return string|null a simple textual summary of the correct resonse.
     */
    public function get_right_answer_summary(): string|null {
        if ($this->question instanceof question_automatically_gradable) {
            return parent::get_right_answer_summary();
        } else {
            return null;
        }
    }

    /**
     * Work out whether the response in $pendingstep are significantly different
     * from the last set of responses we have stored.
     * @param question_attempt_step $pendingstep contains the new responses.
     * @return bool whether the new response is the same as we already have.
     */
    protected function is_same_response(question_attempt_step $pendingstep): bool {
        // If we made the question readonly $pendingstep won't contain the response.
        return $this->is_previous_attempt_correct() ? true : parent::is_same_response($pendingstep);
    }

    /**
     * Handle the 'comment' case of {@see process_action()} to process manual grade/comments.
     * @param question_attempt_pending_step $pendingstep astep representing the action.
     * @return bool either {@see question_attempt::KEEP} or {@see question_attempt::DISCARD}.
     */
    public function process_comment(question_attempt_pending_step $pendingstep) {
        if ($this->copyinprocess) {
            // Skip the regular processing and just update the step with the previous values.
            $gradedattempt = $this->get_previous_graded_attempt();
            $pendingstep->set_fraction($gradedattempt->get_fraction());
            $pendingstep->set_state($gradedattempt->get_state());
            return question_attempt::KEEP;
        }
        return parent::process_comment($pendingstep);
    }

    /**
     * Handle the 'finish' case of {@see process_action()}.
     * @param question_attempt_pending_step $pendingstep step representing the action.
     * @return bool either {@see question_attempt::KEEP} or {@see question_attempt::DISCARD}.
     */
    public function process_finish(question_attempt_pending_step $pendingstep) {
        if ($this->question instanceof question_automatically_gradable) {
            return parent::process_finish($pendingstep);
        } else {
            return $this->process_manualgraded_finish($pendingstep);
        }
    }

    /**
     * Handle the manual graded 'finish' case of {@see process_action()}.
     * @param question_attempt_pending_step $pendingstep step representing the action.
     * @return bool either {@see question_attempt::KEEP} or {@see question_attempt::DISCARD}.
     */
    public function process_manualgraded_finish(question_attempt_pending_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        $response = $this->qa->get_last_step()->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $pendingstep->set_state(question_state::$gaveup);
        } else {
            if ($this->is_previous_attempt_correct()) {
                // Copy previous manual grade and comment.
                $gradedattempt = $this->get_previous_graded_attempt();
                if ($gradedattempt->has_manual_comment()) {
                    [$comment, $commentformat, $step] = $gradedattempt->get_manual_comment();
                    $this->copyinprocess = true;
                    $this->qa->manual_grade($comment, $gradedattempt->get_mark(), $commentformat, null, $step->get_user_id());
                    $this->copyinprocess = false;
                }
                // This step finishes after the manual grade, so need to set the state here as well.
                $pendingstep->set_fraction($gradedattempt->get_fraction());
                $pendingstep->set_state($gradedattempt->get_state());
            } else {
                $pendingstep->set_state(question_state::$needsgrading);
            }
        }
        $pendingstep->set_new_response_summary($this->question->summarise_response($response));
        return question_attempt::KEEP;
    }

    /**
     * Determines whether the response from the previous attempt was correct.
     * @return bool whether the response from the previous attempt was correct.
     */
    protected function is_previous_attempt_correct(): bool {
        // Get the response from the previous attempt. This should always be the first step.
        $prevresponse = $this->qa->get_step(0)->get_qt_data();
        if (!$prevresponse || !$this->question->is_gradable_response($prevresponse)) {
            return false;
        }

        if ($this->question instanceof question_automatically_gradable) {
            // Grade the response to see if it was correct.
            [$fraction, $state] = $this->question->grade_response($prevresponse);
        } else {
            // Look for a previously graded attempt.
            if (!$gradedattempt = $this->get_previous_graded_attempt()) {
                return false;
            }
            $fraction = $gradedattempt->get_fraction();
            $state = $gradedattempt->get_state();
        }

        return $state->is_correct() || $fraction == $this->question->get_max_fraction();
    }

    /**
     * Find the last graded user attempt for this question.
     * @return question_attempt|bool Return the question attempt, otherwise false.
     */
    public function get_previous_graded_attempt(): question_attempt|bool {
        global $DB;

        static $gradedattempts = [];
        $slot = $this->qa->get_slot();
        if (isset($gradedattempts[$slot])) {
            return $gradedattempts[$slot];
        }

        $gradedattempts[$slot] = false;

        // Load the user's previous quiz attempts at this quiz.
        if (!$record = $DB->get_record('quiz_attempts', ['uniqueid' => $this->qa->get_usage_id()])) {
            return false;
        }

        $quizattempts = quiz_get_user_attempts($record->quiz, $record->userid, 'finished', false);
        if (empty($quizattempts)) {
            return false;
        }

        // Find the most recent graded question attempt for the same response.
        $quizattempts = array_reverse($quizattempts);
        foreach ($quizattempts as $quizattempt) {
            if ($quizattempt->uniqueid == $this->qa->get_usage_id()) {
                continue;
            }

            $quba = question_engine::load_questions_usage_by_activity($quizattempt->uniqueid);
            if (!in_array($slot, $quba->get_slots())) {
                continue;
            }

            $attempt = $quba->get_question_attempt($slot);
            if (!$this->question->is_same_response($this->qa->get_last_qt_data(), $attempt->get_last_qt_data())) {
                return false;
            }

            if ($attempt->get_state()->is_graded()) {
                $gradedattempts[$slot] = $attempt;
                return $gradedattempts[$slot];
            }
        }

        return false;
    }
}
