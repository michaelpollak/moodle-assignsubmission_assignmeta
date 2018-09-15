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
 * This file contains the definition for the library class for assignmeta submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_assignmeta
 * @copyright 2018 michael pollak <moodle@michaelpollak.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class assign_submission_assignmeta extends assign_submission_plugin {

    /**
     * Get the name of the assignmeta submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('assignmeta', 'assignsubmission_assignmeta');
    }

    /**
     * Get assignmeta submission information from the database
     *
     * @param  int $submissionid
     * @return mixed
     */
    private function get_assignmeta_submission($submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_assignmeta', array('submission'=>$submissionid));
    }

    /**
     * Get the settings for assignmeta submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;
        // Display menu for teachers to edit fields.
        $fields = array('title', 'meta1', 'meta2', 'meta3', 'meta4', 'meta5');
        foreach ($fields as $fieldname) {
            // Allow only the edit of fields that are not hidden by admin in lang files.
            if (get_string($fieldname, 'assignsubmission_assignmeta') != 'hidden') {
                // Load defaults. If we find a current configtext add, else use default from language files.
                if($this->get_config($fieldname.'_text') != '') $fieldtext = $this->get_config($fieldname.'_text');
                else $fieldtext = get_string($fieldname, 'assignsubmission_assignmeta');
                // Working set.
                /*
                $mform->addElement('text', $fieldname.'_text', "Text for ".$fieldname, ''); // Add element for meta.
                $mform->setType($fieldname.'_text', PARAM_TEXT);
                $mform->setDefault($fieldname.'_text', $fieldtext);
                $mform->hideIf($fieldname.'_text', 'assignsubmission_assignmeta_enabled', 'notchecked');
                */

                // Extension. I want to show all fields but allow teacher to hide with checkbox. See onlinetext-wordlimit.
                $fieldgrp = array();
                $fieldgrp[] = $mform->createElement('text', $fieldname.'_text', $fieldtext, '');
                $fieldgrp[] = $mform->createElement('advcheckbox', $fieldname.'_enabled', '', get_string('enable'), array(), array(0, 1));
                $mform->addGroup($fieldgrp, $fieldname.'_group', $fieldtext, ' ', false);

                // TODO: Add help buttons. $mform->addHelpButton('assignsubmission_onlinetext_wordlimit_group', 'wordlimit', 'assignsubmission_onlinetext');
                $mform->disabledIf($fieldname.'_text', $fieldname.'_enabled', 'notchecked');
                $mform->setDefault($fieldname.'_text', $fieldtext);
                $mform->setDefault($fieldname.'_enabled', $this->get_config($fieldname.'_enabled')); // I guess it'S smart to have all enabled?
                $mform->setType($fieldname.'_text', PARAM_TEXT);
                $mform->hideIf($fieldname.'_group', 'assignsubmission_assignmeta_enabled', 'notchecked');
            }
        }
    }

    /**
     * Returns an array of all fields we do have in the database.
     *
     * @return array An array of field names
     */
    public function get_fields() {
        return array('title', 'meta1', 'meta2', 'meta3', 'meta4', 'meta5');
    }

    /**
     * Save the settings for assignmeta submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        foreach ($this->get_fields() as $fieldname) {
            $field = $fieldname.'_text';
            if(isset($data->$field)) {
                $this->set_config($field, $data->$field);
                $fieldenabled = $fieldname.'_enabled';
                $this->set_config($fieldenabled, $data->$fieldenabled); // Also store enabled checkbox.
            }
        }
        return true;
    }

    /**
     * Add form elements for settings
     * This is what the submitting person sees.
     *
     * @param mixed $submission can be null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return true if elements were added to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        // TODO: Upgrade to _for_users

        // If we already submitted check content for fields.
        if ($submission) {
            $assignmetasubmission = $this->get_assignmeta_submission($submission->id);
            if ($assignmetasubmission) {
                foreach ($this->get_fields() as $fieldname) {
                    $data->$fieldname = $assignmetasubmission->$fieldname;
                }
            }
        }

        // Define attributes for all form fields once.
        $attr = array('size' => '100', 'maxlength' => '100');

        // Add formfields for metadata, hide fields with keyword hidden.
        foreach ($this->get_fields() as $fieldname) {
            // Allow only the edit of fields that are not hidden by admin in lang files.
            if (get_string($fieldname, 'assignsubmission_assignmeta') != 'hidden'
                && $this->get_config($fieldname.'_enabled')) {
                // If we find a current configtext add, else use default from language files.
                if($this->get_config($fieldname.'_text') != '') $fieldtext = $this->get_config($fieldname.'_text');
                else $fieldtext = get_string($fieldname, 'assignsubmission_assignmeta');
                $mform->addElement('text', $fieldname, $fieldtext, $attr);
                $mform->setType($fieldname, PARAM_TEXT);
            }
        }

/*



*/
        return true;
    }

    /**
     * Save data to the database
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;

        $assignmetasubmission = $this->get_assignmeta_submission($submission->id);

        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $params['relateduserid'] = $submission->userid;
        }

        $groupname = null;
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }

        // If we update an existing record.
        if ($assignmetasubmission) {
            foreach ($this->get_fields() as $fieldname) {
                if (isset($data->$fieldname)) $assignmetasubmission->$fieldname = $data->$fieldname;
            }
            $params['objectid'] = $assignmetasubmission->id;
            $updatestatus = $DB->update_record('assignsubmission_assignmeta', $assignmetasubmission);

            return $updatestatus;
        } else {
            $assignmetasubmission = new stdClass();
            foreach ($this->get_fields() as $fieldname) {
                if (isset($data->$fieldname)) $assignmetasubmission->$fieldname = $data->$fieldname;
            }
            $assignmetasubmission->submission = $submission->id;
            $assignmetasubmission->assignment = $this->assignment->get_instance()->id;
            $assignmetasubmission->id = $DB->insert_record('assignsubmission_assignmeta', $assignmetasubmission);

            return $assignmetasubmission->id > 0;
        }
    }

    /**
     * Return a list of the text fields that can be imported/exported by this plugin
     *
     * @return array An array of field names and descriptions. (name=>description, ...)
     */
    public function get_editor_fields() {
        return array('assignmeta' => get_string('pluginname', 'assignsubmission_assignmeta'));
    }

     /**
      * Display content summary.
      *
      * @param stdClass $submission
      * @param bool $showviewlink - If the summary has been truncated set this to true
      * @return string
      */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $CFG;

        $text = '';
        $assignmetasubmission = $this->get_assignmeta_submission($submission->id);

        // TODO: If we want to hide parts of assignmeta, use this and extend view.
        $showviewlink = false;

        if ($assignmetasubmission) {
            foreach ($this->get_fields() as $fieldname) {
                // Use keyword hidden in langfile to hide field.
                if (get_string($fieldname, 'assignsubmission_assignmeta') != 'hidden' && $this->get_config($fieldname.'_enabled')) {
                    // $text .= '<p>' .get_string($fieldname, 'assignsubmission_assignmeta') . ': ' . $assignmetasubmission->$fieldname . '<br>';
                    $text .= '<p>' . $this->get_config($fieldname.'_text') . ': '
                     . $assignmetasubmission->$fieldname . '<br>';
                }
            }
        }
        return $text;
    }

    /**
     * Display the saved text content from the editor in the view table
     * This is only displayed when showviewlink is set to true and button is clicked.
     * Not used at the moment by assignmeta.
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $CFG;
        $result = "<h2>Title: Testing the longer form that is hidden.</h2>"; // TODO: We can add sth. to the longer version here.
        return $result;
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type and version.
     *
     * @param string $type old assignment subtype
     * @param int $version old assignment version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        return false;
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The new submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Format the info for each submission plugin (will be logged).
        $assignmetasubmission = $this->get_assignmeta_submission($submission->id);
        $assignmetaloginfo = '';
        $assignmetaloginfo .= "Some metadata was added to a submission."; // TODO: Multilang.

        return $assignmetaloginfo;
    }

    /**
     * The assignment has been deleted - cleanup.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $DB->delete_records('assignsubmission_assignmeta',
                            array('assignment'=>$this->assignment->get_instance()->id));
        return true;
    }

    /**
     * No text is set for this plugin.
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $assignmetasubmission = $this->get_assignmeta_submission($submission->id);
        // As soon as one is not empty we know the answer.
        foreach ($this->get_fields() as $fieldname) {
            if (empty($assignmetasubmission->$fieldname)) return false;
        }
        return true;
}

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        // We catch this by form required.
        return false;
    }

    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        $contextid = $this->assignment->get_context()->id;

        // Copy the assignsubmission_assignmeta record.
        $assignmetasubmission = $this->get_assignmeta_submission($sourcesubmission->id);
        if ($assignmetasubmission) {
            unset($assignmetasubmission->id);
            $assignmetasubmission->submission = $destsubmission->id;
            $DB->insert_record('assignsubmission_assignmeta', $assignmetasubmission);
        }
        return true;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of settings
     * @since Moodle 3.2
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }
}


