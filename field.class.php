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
 * @subpackage jSignature
 * @copyright  2021 onwards Andrei Bautu (abautu) {@link https://www.linkedin.com/in/andreibautu/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class data_field_jsignature extends data_field_base {

    /** @var string name of this plugin */
    var $type = 'jsignature';

    /**
     * Generates the image URL of a signature (uses @see get_site_identifier for hashing).
     *
     * @param $data jSignature base30 data
     * @return string
     */
    public static function get_download_image_url($data) {
        global $CFG;
        $secret = get_site_identifier();
        $url = $CFG->wwwroot . '/mod/data/field/jsignature/img.php?data=' . $data;
        $url .= '&s=' . md5($url . $secret);
        return $url;
    }

    /**
     * Downloads an SVG version of the $data if the $signature matches that generated by @see get_download_image_url
     *
     * @param string $data jSignature base30 data
     * @param string $signature the signature of this image request
     */
    public static function download_image($data, $signature) {
        global $CFG;
        $secret = get_site_identifier();
        $url = $CFG->wwwroot . '/mod/data/field/jsignature/img.php?data=' . $data;
        if ($signature != md5($url . $secret)) {
            header('401 Access denied');
            echo 'Invalid signature';
            exit;
        }
        header('Content-type: image/svg+xml');
        header('Cache-Control:public, max-age=31536000');
        echo data_field_jsignature::get_svg_image($data);
        exit;
    }

    /**
     * Returns a SVG image created from the a jSignature base30 encoded data.
     *
     * @param string $data jSignature base30 data
     * @return string
     */
    public static function get_svg_image($data) {
        require_once(__DIR__ . '/classes/jSignature_Tools_Base30.php');
        require_once(__DIR__ . '/classes/jSignature_Tools_SVG.php');
        $signatureParser = new jSignature_Tools_Base30();
        $svgGenerator = new jSignature_Tools_SVG();
        $svg = $svgGenerator->NativeToSVG($signatureParser->Base64ToNative($data));
        return $svg;
    }

    /**
     * Returns a raster (JPEG, PNG, BMP, etc) image created from the a jSignature base30 encoded data.
     *
     * @param string $data jSignature base30 data
     * @return string
     */
    public static function get_raster_image($data, $format = 'png8') {
        $svg = self::get_svg_image($data);
        $img = new Imagick();
        $img->readImageBlob($svg);
        $img->setImageFormat($format);
        $img->paintTransparentImage("rgb(255,255,255)", 0, 0);
        $img->trimImage(0);
        return $img->getImageBlob();
    }

    /**
     * This field just sets up a default field object
     *
     * @return bool
     */
    function define_default_field() {
        parent::define_default_field();
        $this->field->param1 = '#000080';
        $this->field->param2 = '#FFFFFF';
        return true;
    }

    /**
     * Print the relevant form element in the ADD template for this field
     *
     * @global object
     * @param int $recordid
     * @return string
     */
    public function display_add_field($recordid = 0, $formdata = null)
    {
        global $PAGE;
        $PAGE->requires->js_call_amd('datafield_jsignature/jsignature', 'init', array(
            'field_' . $this->field->id,
            $this->field->param1,
            $this->field->param2,
        ));
        return parent::display_add_field($recordid, $formdata);
    }

    /**
     * Display the content of the field in browse mode
     *
     * @global object
     * @param int $recordid
     * @param object $template
     * @return bool|string
     */
    function display_browse_field($recordid, $template) {
        global $DB;

        $content = $DB->get_record('data_content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid));
        if (empty($content->content)) {
            return false;
        }
        $str = self::get_download_image_url($content->content);
        return "<img class=\"jsignaturefield_img\" src=\"$str\">";
    }

    /**
     * Renders the HTML code of the searching form this field.
     *
     * @param string $value current value
     * @return string
     */
    function display_search_field($value = '') {
        return '<label class="accesshide" for="f_' . $this->field->id . '">'. $this->field->name.'</label>' .
            '<select id="f_'.$this->field->id.'" name="f_'.$this->field->id.'">' .
                '<option></option>' .
                '<option value="0" '.($value===0 ? "selected":"").'>'. get_string('no', 'core') . '</option>' .
                '<option value="1" '.($value===1 ? "selected":"").'>'. get_string('yes', 'core') . '</option>' .
            '</select>';
    }

    /**
     * Parse query string parameters into search filters
     *
     * @return string
     */
    function parse_search_field() {
        return optional_param('f_'.$this->field->id, '', PARAM_BOOL);
    }

    /**
     * Generates the SQL conditions related to searching in this field.
     * @param $tablealias
     * @param $value
     */
    function generate_sql($tablealias, $value) {
        global $DB;

        static $i=0;
        $i++;
        $sql = "{$tablealias}.fieldid = {$this->field->id}";
        if ($value === 0) {
            $sql .= " AND {$tablealias}.content=''";
        }
        if ($value === 1) {
            $sql .= " AND {$tablealias}.content<>''";
        }
        return array(" ($sql) ", array());
    }

    /**
     * Per default, return the record's text value only from the "content" field.
     * Override this in fields class if necesarry.
     *
     * @param string $record
     * @return string
     */
    function export_text_value($record) {
        if ($this->text_export_supported()) {
            return self::get_download_image_url($record->content);
        }
    }

    /**
     * Returns the name/type of the field
     *
     * @return string
     */
    function name() {
        return get_string('fieldtypelabel', "datafield_jsignature");
    }
}


