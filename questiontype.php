<?php
/**
 * The question type class for the ubhotspots question type.
 *
 * @copyright &copy; 2011 Universitat de Barcelona
 * @author <jleyva@cvaconsulting.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ubhotspots
 *//** */

require_once($CFG->libdir.'/pear/HTML/AJAX/JSON.php');
 
/**
 * The ubhotspots question class
 */
class ubhotspots_qtype extends default_questiontype {

    function name() {
        return 'ubhotspots';
    }
    
    /**
     * @return boolean to indicate success of failure.
     */
    function get_question_options(&$question) {
        if (!$question->options = get_record('question_ubhotspots', 'question', $question->id)) {
            notify('Error: Missing question options for ubhotspots question'.$question->id.'!');
            return false;
        }
        
        if (!$question->options->answers = get_records('question_answers', 'question', $question->id)) {
           notify('Error: Missing question answers for ubhotspots question'.$question->id.'!');
           return false;
        }
        
        return true;
    }

    /**
     * Save the units and the answers associated with this question.
     * @return boolean to indicate success of failure.
     */
    function save_question_options($question) {
        
        
        $answers = json_decode(stripslashes($question->hseditordata));
        
        foreach($answers as $key=>$a){            
            if(!$a || !$a->draw || !$a->shape || !$a->text){
                unset($answers[$key]);
            }
        }
        
        if(!$answers){        
            $result->notice = get_string("failedloadinganswers", "qtype_ubhotspots");
            return $result;
        }
        
        if (!$oldanswers = get_records("question_answers", "question",$question->id, "id ASC")) {
            $oldanswers = array();
        }
        
        // TODO - Javascript Interface for fractions in the editor
        $fraction = round(1 / count($answers), 2);
        
        foreach($answers as $a){            
                         
            if ($answer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                
                $answer->answer     = addslashes(json_encode($a));
                $answer->fraction   = $fraction;
                $answer->feedback = '';
                if (!update_record("question_answers", $answer)) {
                    $result->error = "Could not update quiz answer! (id=$answer->id)";
                    return $result;
                }
            } else {
                
                unset($answer);
                $answer->answer   = addslashes(json_encode($a));
                $answer->question = $question->id;
                $answer->fraction = $fraction;
                $answer->feedback = '';
                if (!$answer->id = insert_record("question_answers", $answer)) {
                    $result->error = "Could not insert quiz answer! ";
                    return $result;
                }
            }
            
        }
        
                
        // delete old answer records
        if (!empty($oldanswers)) {
            foreach($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }
        
        $update = true;
        $options = get_record("question_ubhotspots", "question", $question->id);
        if (!$options) {
            $update = false;
            $options = new stdClass;
            $options->question = $question->id;
        }
        
        $options->hseditordata = addslashes($question->hseditordata);
        
        if ($update) {
            if (!update_record("question_ubhotspots", $options)) {
                $result->error = "Could not update quiz ubhotspots options! (id=$options->id)";
                return $result;
            }
        } else {
            if (!insert_record("question_ubhotspots", $options)) {
                $result->error = "Could not insert quiz ubhotspots options!";
                return $result;
            }
        }        

        return true;
    }

    /**
     * Deletes question from the question-type specific tables
     *
     * @param integer $questionid The question being deleted
     * @return boolean to indicate success of failure.
     */
    function delete_question($questionid) {
        delete_records("question_ubhotspots", "question", $questionid);
        return true;
    }
    
    
    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
        $state->responses = array();
        return true;
    }

    function restore_session_and_responses(&$question, &$state) {
                        
        list($keys, $values) = explode(':',$state->responses['']);
        $state->responses = array_combine(explode(';',$keys),explode(';',$values));
        
        return true;
    }
    
    function save_session_and_responses(&$question, &$state) {
    
        $responses = implode(';',array_keys($state->responses)).':';
        $responses .= implode(';', $state->responses);
    
        return set_field('question_states', 'answer', $responses, 'id', $state->id);
    }    
    
    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG;

        $readonly = empty($options->readonly) ? '' : 'disabled="disabled"';

        // Print formulation
        $questiontext = $this->format_text($question->questiontext,$question->questiontextformat, $cmoptions);
        $image = get_question_image($question, $cmoptions->course);
    
        
        $isfinished = question_state_is_graded($state->last_graded) || $state->event == QUESTION_EVENTCLOSE;
        $feedback = '';
        if ($isfinished && $options->generalfeedback){
            $feedback = $this->format_text($question->generalfeedback, $question->questiontextformat, $cmoptions);
        }
    
        $nameprefix = $question->name_prefix;
        
        $imgfeedback = array();
        
        if(!empty($state->responses)){
            foreach ($state->responses as $key=>$response){
                if(isset($question->options->answers[$key]))
                    $imgfeedback[$key] = $this->check_coords($response,$question->options->answers[$key]->answer);                
            }
        }
                        
        include("$CFG->dirroot/question/type/ubhotspots/display.html");
        
    }
    
    function grade_responses(&$question, &$state, $cmoptions) {
        $state->raw_grade = 0;
               
        foreach ($state->responses as $key=>$response) {
            if ($this->check_coords($response,$question->options->answers[$key]->answer)) {
                $state->raw_grade += $question->options->answers[$key]->fraction;
            }
        }
       
       // Make sure we don't assign negative or too high marks
        $state->raw_grade = min(max((float) $state->raw_grade, 0.0), 1.0) * $question->maxgrade;

        // Apply the penalty for this attempt
        $state->penalty = $question->penalty * $question->maxgrade;

        // mark the state as graded
        $state->event = ($state->event ==  QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;

        return true;
    }
    

    function get_all_responses(&$question, &$state) {
        $result = new stdClass;
        // TODO, return a link to a php that displays the correct response
        return $result;
    }

    function get_actual_response($question, $state) {
        // TODO, return a link to a php that displays the correct response
        $responses = '';
        return $responses;
    }
    
    /**
     * Check if the user entered coords are inside the correct shape
     *
     * @param object $response The users response
     * @param object $answer The correct answer object containing the shape settings
     * @return boolean to indicate success of failure.
     */
    function check_coords($response, $answer){
                        
        if(!$response || !strpos($response,',')){
            return false;
        }        
        
        $answer = json_decode($answer);
        list($x,$y) = explode(',',$response);
        
        if($answer && $answer->shape){                        
            
            $s = $answer->shape;
            // Rectangle
            if($answer->shape->shape == 'rect'){
                
                if($x >= $s->startX && $x <= $s->endX && $y >= $s->startY && $y <= $s->endY){                    
                    return true;
                }
            }
            // Ellipse
            else if($answer->shape->shape == 'ellip'){
                $w = $s->endX - $s->startX;
                $h = $s->endY - $s->startY;
                                 
                // Ellipse radius
                $rx = $w / 2;
                $ry = $h / 2; 
        
                // Ellipse center
                $cx = $s->startX + $rx;
                $cy = $s->startY + $ry;
                    
                $dx = ($x - $cx) / $rx;
                $dy = ($y - $cy) / $ry;
                $distance = $dx * $dx + $dy * $dy;
                
                //if ((cuadrado(mouseX - cx)/cuadrado(w)) + (cuadrado(mouseY - cy)/cuadrado(h)) < 1)
                if ($distance < 1.0){
                    return true;
                }
            }    
        }        
        return false;
    }

    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf,$preferences,$question,$level=6) {

        $status = true;

        $ubhotspots = get_records("question_ubhotspots","question",$question,"id");
        //If there are ubhotspots
        if ($ubhotspots) {
            //Iterate over each ubhotspots
            foreach ($ubhotspots as $hs) {
                $status = fwrite ($bf,start_tag("UBHOTSPOTS",$level,true));
                //Print ubhotspots contents
                fwrite ($bf,full_tag("HSEDITORDATA",$level+1,false,$hs->hseditordata));
                $status = fwrite ($bf,end_tag("UBHOTSPOTS",$level,true));
            }

            //Now print question_answers
            $status = question_backup_answers($bf,$preferences,$question);
        }
        return $status;

    }

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {

        $status = true;

        //Get the ubhotspots array
        $ubhotspots = $info['#']['UBHOTSPOTS'];

        //Iterate over ubhotspots
        for($i = 0; $i < sizeof($ubhotspots); $i++) {
            $mul_info = $ubhotspots[$i];

            //Now, build the question_ubhotspots record structure
            $ubhotspot = new stdClass;
            $ubhotspot->question = $new_question_id;
            $ubhotspot->hseditordata = backup_todb($mul_info['#']['HSEDITORDATA']['0']['#']);                      

            //The structure is equal to the db, so insert the question_shortanswer
            $newid = insert_record ("question_ubhotspots",$ubhotspot);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if (!$newid) {
                $status = false;
            }
        }

        return $status;        
    }
}

// Register this question type with the system.
question_register_questiontype(new ubhotspots_qtype());
?>
