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
     * Determines whether the response from the previous attempt was correct.
     * @return bool whether the response from the previous attempt was correct.
     */
    protected function is_previous_attempt_correct(): bool {
        // Get the response from the previous attempt. This should always be the first step.
        $prevresponse = $this->qa->get_step(0)->get_qt_data();
        if (!$prevresponse || !$this->question->is_gradable_response($prevresponse)) {
            return false;
        }

        // Grade the response to see if it was correct.
        [$fraction, $state] = $this->question->grade_response($prevresponse);
        return $state === question_state::$gradedright || $fraction == $this->question->get_max_fraction();
    }
}
