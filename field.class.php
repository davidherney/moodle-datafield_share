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
 * @package    datafield
 * @subpackage share
 * @copyright  2022 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once($CFG->libdir . '/form/autocomplete.php');

/**
 * Component class
 *
 * @package    datafield
 * @subpackage share
 * @copyright  2022 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_field_share extends data_field_base {

    var $type = 'share';

    /**
     * priority for globalsearch indexing
     *
     * @var int
     */
    protected static $priority = self::MAX_PRIORITY;

    /**
     * Print the relevant form element in the ADD template for this field
     *
     * @global object
     * @param int $recordid
     * @return string
     */
    function display_add_field($recordid=0, $formdata=null) {
        global $DB, $OUTPUT, $PAGE, $COURSE;

        $fieldname = 'field_' . $this->field->id;

        if ($formdata) {
            $content = $formdata->$fieldname;
        } else if ($recordid) {
            $content = $DB->get_field('data_content', 'content', array('fieldid' => $this->field->id, 'recordid' => $recordid));
            $content = explode(',', $content);
        } else {
            $content = [];
        }

        $str = '<div title="' . s($this->field->description) . '">';
        $str .= '<label for="field_' . $this->field->id . '"><span class="accesshide">' . $this->field->name . '</span>';

        if ($this->field->required) {
            $image = $OUTPUT->pix_icon('req', get_string('requiredelement', 'form'));
            $str .= html_writer::div($image, 'inline-req');
        }

        $attributes = [
            'ajax' => 'datafield_share/main',
            'multiple' => true,
            'data-courseid' => $COURSE->id
        ];
        $field = new MoodleQuickForm_autocomplete($fieldname, '', [],  $attributes);

        $str .= $field->toHtml();

 //       $PAGE->requires->js_call_amd('datafield_share/main', 'init', array('field_' . $this->field->id));

        $str .= '</div>';

        return $str;
    }

    /**
     * Prints the respective type icon
     *
     * @global object
     * @return string
     */
    function image() {
        global $OUTPUT;

        $params = array('d' => $this->data->id, 'fid' => $this->field->id, 'mode' => 'display', 'sesskey' => sesskey());
        $link = new moodle_url('/mod/data/field.php', $params);
        $str = '<a href="' . $link->out() . '">';
        $str .= $OUTPUT->pix_icon('field/' . $this->type, $this->type, 'datafield_' . $this->type);
        $str .= '</a>';
        return $str;
    }

    function display_search_field($value = '') {
        return '<label class="accesshide" for="f_' . $this->field->id . '">' . $this->field->name.'</label>' .
               '<input type="text" class="form-control" size="16" id="f_' . $this->field->id . '" ' .
               'name="f_' . $this->field->id . '" value="' . s($value) . '" />';
    }

    public function parse_search_field($defaults = null) {
        $param = 'f_'.$this->field->id;
        if (empty($defaults[$param])) {
            $defaults = array($param => '');
        }
        return optional_param($param, $defaults[$param], PARAM_NOTAGS);
    }

    function generate_sql($tablealias, $value) {
        global $DB;

        static $i=0;
        $i++;
        $name = "df_text_$i";
        return array(" ({$tablealias}.fieldid = {$this->field->id} AND " .
                        $DB->sql_like("{$tablealias}.content", ":$name", false) . ") ",
                        array($name => "%$value%"));
    }

    /**
     * Check if a field from an add form is empty
     *
     * @param mixed $value
     * @param mixed $name
     * @return bool
     */
    function notemptyfield($value, $name) {
        return is_array($value) && count($value) > 0;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of config parameters
     * @since Moodle 3.3
     */
    public function get_config_for_external() {
        // Return all the config parameters.
        $configs = [];
        for ($i = 1; $i <= 10; $i++) {
            $configs["param$i"] = $this->field->{"param$i"};
        }
        return $configs;
    }

    function update_content($recordid, $value, $name='') {
        global $DB;

        $content = new stdClass();
        $content->fieldid = $this->field->id;
        $content->recordid = $recordid;
        $content->content = $this->format_data_field_share_content($value);

        if ($oldcontent = $DB->get_record('data_content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid))) {
            $content->id = $oldcontent->id;
            return $DB->update_record('data_content', $content);
        } else {
            return $DB->insert_record('data_content', $content);
        }
    }

    function format_data_field_share_content($content) {
        if (!is_array($content)) {
            return null;
        }

        $vals = [];
        foreach ($content as $key => $val) {
            if (!is_numeric($val)) {
                continue;
            }
            $vals[] = (int)$val;
        }

        if (empty($vals)) {
            return null;
        }

        return implode(',', $vals);
    }

    /**
     * Returns the presentable string value for a field content.
     *
     * The returned string should be plain text.
     *
     * @param stdClass $content
     * @return string
     */
    public static function get_content_value($content) {
        $arr = explode(',', $content->content);

        $strvalue = '';
        foreach ($arr as $a) {
            $strvalue .= $a . ' ';
        }

        return trim($strvalue, "\r\n ");
    }

}


