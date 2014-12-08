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
 * Compile discussions block
 *
 * @package    block_compile_discussions
 * @copyright  2014 Steve Bond
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_compile_discussions extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_compile_discussions');
    }

    public function applicable_formats() {
        return array('course-view' => true, 'site' => true);
    }

    public function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        // Set up the content object.
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass;

        $course = $this->page->course;
        $modinfo = get_fast_modinfo($course);
        $forums = $DB->get_records('forum', array('course' => $course->id));
        // Need a check here to see if hsuforum is actually installed.
        $hsuforuminstalled = false;
        if ($DB->get_records('modules', array('name' => 'hsuforum'))) {
            $hsuforuminstalled = true;
            $hsuforums = $DB->get_records('hsuforum', array('course' => $course->id));
        }

        // Collect names of forums in this course.
        $menu = array();

        // This loop taken from forum/index.php.
        if (isset($modinfo->instances['forum'])) {
            foreach ($modinfo->instances['forum'] as $forumid => $cm) {
                if (!$cm->uservisible or !isset($forums[$forumid])) {
                    continue;
                }

                $forum = $forums[$forumid];

                if (!$context = context_module::instance($cm->id)) {
                    continue;   // Shouldn't happen.
                }

                if (!has_capability('mod/forum:viewdiscussion', $context)) {
                    continue;
                }

                // Add to array for the menu. Truncate the name if long.
                $fname = $forum->name;
                if (strlen($fname) > 20) {
                    $fname = substr($fname, 0, 20) . '...';
                }
                $menu[$forum->id] = $fname;
            }
        }

        // Sort the forum names alphabetically.
        natcasesort($menu);

        // Collect names of Advanced Forums, if installed.
        if ($hsuforuminstalled) {

            $menuhsu = array();

            if (isset($modinfo->instances['hsuforum'])) {
                foreach ($modinfo->instances['hsuforum'] as $hsuforumid => $cm) {
                    if (!$cm->uservisible or !isset($hsuforums[$hsuforumid])) {
                        continue;
                    }

                    $hsuforum = $hsuforums[$hsuforumid];

                    if (!$context = context_module::instance($cm->id)) {
                        continue;   // Shouldn't happen.
                    }

                    if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
                        continue;
                    }

                    // Add to array for the menu. Truncate the name if long.
                    $hname = $hsuforum->name;
                    if (strlen($hname) > 20) {
                        $hname = substr($hname, 0, 20) . '...';
                    }
                    $menuhsu[$hsuforum->id] = $hname;
                }
            }

            // Sort the forum names alphabetically.
            natcasesort($menuhsu);

        }

        // Now we have an array of all forums. Use this to populate a drop-down menu. Selecting an option
        // will call the compile.php script and pass the forum ID as argument.
        $actionurl = new moodle_url('/blocks/compile_discussions/compile.php', array('type' => 'forum'));
        $actionurlhsu = new moodle_url('/blocks/compile_discussions/compile.php', array('type' => 'hsuforum'));
        $select = new single_select($actionurl, 'forumid', $menu, null,
        array('' => get_string('chooseforum', 'block_compile_discussions')));
        if ($hsuforuminstalled) {
            $selecthsu = new single_select($actionurlhsu, 'forumid', $menuhsu, null,
                array('' => get_string('choosehsuforum', 'block_compile_discussions')));
        }
        $this->content = new stdClass;
        $this->content->text = html_writer::tag('p', get_string('select', 'block_compile_discussions'));
        $this->content->text .= $OUTPUT->render($select);
        if ($hsuforuminstalled) {
            $this->content->text .= $OUTPUT->render($selecthsu);
        }

        return $this->content;
    }
}