# moodle-qbehaviour_remembercorrect

This question behaviour plugin enhances the “Each attempt builds on the last” setting by remembering answers that were answered correctly and making them readonly on subsequent attempts. This allows students to focus on the questions they answered incorrectly, while keeping their correct answers visible to reinforce learning.

This requires "**Each attempt builds on the last**" to be enabled.

## Question types

The plugin supports question types that are automatically gradeable, while limited support is available questions that are manually graded.

For this plugin to support essay question types you will need to apply the core hack from `patches/essay_make_behaviour.patch`.
