<?php
/**
 * The editing form code for this question type.
 *
 * @copyright &copy; 2011 Universitat de Barcelona
 * @author jleyva@cvaconsulting.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ubhotspots
 *
 */

require_once($CFG->dirroot.'/question/type/edit_question_form.php');

/**
 * ubhotspots editing form definition.
 * 
 * See http://docs.moodle.org/en/Development:lib/formslib.php for information
 * about the Moodle forms library, which is based on the HTML Quickform PEAR library.
 */
class question_edit_ubhotspots_form extends question_edit_form {

    function definition_inner(&$mform) {
        global $CFG;
        
        $mform->addElement('header', 'ubhotspotsheader', get_string('ubhotspots', 'qtype_ubhotspots'));
        
        $mform->addElement('button', 'buttoneditor', get_string('openeditor', 'qtype_ubhotspots'),array('onclick'=>'hscheckImages(\''.(get_string('imagealert','qtype_ubhotspots')).'\',\''.(get_string('chooseanimage','qtype_ubhotspots')).'\',\''.$CFG->wwwroot.'\',this.form)'));
        
        $mform->addElement('hidden', 'hseditordata');
    }

    function set_data($question) {
        
        if(isset($question->options)){
            $default_values['hseditordata'] =  stripslashes($question->options->hseditordata);
            $question = (object)((array)$question + $default_values);
        }
        parent::set_data($question);
    }

    function validation($data) {
        $errors = array();

        if ($errors) {
            return $errors;
        } else {
            return true;
        }
    }

    function qtype() {
        return 'ubhotspots';
    }
}
?>