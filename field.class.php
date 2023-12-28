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
    function display_add_field($recordid = 0, $formdata = null) {
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
            'data-courseid' => $COURSE->id,
            'valuehtmlcallback' => function($value) {
                global $OUTPUT;

                $allusernames = \core_user\fields::get_name_fields(true);
                $fields = 'id, email, ' . implode(',', $allusernames);
                $user = \core_user::get_user($value, $fields);

                if (!$user) {
                    return;
                }

                $useroptiondata = [
                    'fullname' => fullname($user),
                    //'hasidentity' => true,
                    'identity' => $user->email,
                ];

                return $OUTPUT->render_from_template('datafield_share/form-user-selector-suggestion', $useroptiondata);
            }
        ];

        $field = new MoodleQuickForm_autocomplete($fieldname, '', [], $attributes);

        $field->setValue($content);

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
        $param = 'f_' . $this->field->id;
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
                        $DB->sql_like("{$tablealias}.content1", ":$name", false) . ") ",
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

    function update_content($recordid, $value, $name = '') {
        global $DB, $data, $CFG, $USER;

        $content = new stdClass();
        $content->fieldid = $this->field->id;
        $content->recordid = $recordid;
        $content->content = $this->format_data_field_share_content($value);
        $content->content1 = strip_tags(self::get_content_value($content));

        $mailmessage = $this->field->param1;

        if ($oldcontent = $DB->get_record('data_content', array('fieldid' => $this->field->id, 'recordid' => $recordid))) {
            $content->id = $oldcontent->id;
            $res = $DB->update_record('data_content', $content);
        } else {
            $res = $DB->insert_record('data_content', $content);
        }

        if ($res && !empty($mailmessage)) {
            $url = $CFG->wwwroot . '/mod/data/view.php?d=' . $data->id . '&rid=' . $recordid;
            $subject = get_string('subjectmail', 'datafield_share', $data->name);
            $messagehtml = str_replace('{link}', '<a href="' . $url . '">' . $url . '</a>', $mailmessage);
            $messagetext = strip_tags($messagehtml);

            $users = explode(',', $content->content);

            foreach ($users as $userid) {

                if (empty($userid)) {
                    continue;
                }

                $user = $DB->get_record('user', ['id' => $userid]);

                if (!$user) {
                    continue;
                }

                email_to_user($user, $USER, $subject, $messagetext, $messagehtml);
            }
        }

        return $res;
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
        return self::get_content_readable($content);
    }

    /**
     * Returns the presentable string value.
     *
     * @param stdClass $content
     * @return string
     */
    private static function get_content_readable($content, $html = false) {
        global $OUTPUT;

        $arr = explode(',', $content->content);

        $values = [];
        foreach ($arr as $a) {

            $allusernames = \core_user\fields::get_name_fields(true);
            $fields = 'id, email, ' . implode(',', $allusernames);
            $user = \core_user::get_user($a, $fields);

            if (!$user) {
                continue;
            }

            if ($html) {
                $useroptiondata = [
                    'fullname' => fullname($user),
                    //'hasidentity' => true,
                    'identity' => $user->email,
                    'profileurl' => new \moodle_url('/user/profile.php', array('id' => $user->id))
                ];
                $values[] = $OUTPUT->render_from_template('datafield_share/form-user-read', $useroptiondata);
            } else {
                $values[] = fullname($user);
            }
        }

        return implode(($html ? '-' : ', '), $values);
    }

    // Display the content of the field in browse mode.
    function display_browse_field($recordid, $template) {
        global $DB;

        if ($content = $DB->get_record('data_content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid))) {
            return self::get_content_readable($content, true);
        }
        return false;
    }

    /**
     * Returns the presentable string value for a field content.
     * The returned string should be plain text.
     *
     * @param stdClass $record
     * @return string
     */
    function export_text_value($record) {
        return $record->content1;
    }

}