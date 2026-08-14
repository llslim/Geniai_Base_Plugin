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

namespace local_aacuracore;

defined('MOODLE_INTERNAL') || die;

/**
 * Custom admin setting that outputs raw (unescaped) HTML.
 *
 * Moodle's built-in admin_setting_heading escapes its description, which
 * prevents rendering rich HTML (tabs, cards, SVG graphs). This setting
 * renders the provided HTML verbatim.
 *
 * Note: adminlib.php is loaded by the admin settings page before this class
 * is instantiated, so \admin_setting is available without an explicit require.
 *
 * @package   local_aacuracore
 * @copyright 2026 AAC-RERC Chatbot Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_aacura_html extends \admin_setting {

    /** @var string Raw HTML to render. */
    protected $html;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $html Raw HTML content.
     */
    public function __construct($name, $visiblename, $html) {
        $this->html = $html;
        parent::__construct($name, $visiblename, '', '');
    }

    /**
     * Returns the raw HTML.
     *
     * @param string $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        return format_admin_setting($this, $this->visiblename, $this->html, '', false, '', '', $query);
    }

    /**
     * No data to write.
     *
     * @param string $data
     * @return string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * No stored value for this display-only setting.
     *
     * @return string
     */
    public function get_setting() {
        return '';
    }
}
