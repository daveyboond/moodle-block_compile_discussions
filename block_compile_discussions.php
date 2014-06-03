<?php

class block_compile_discussions extends block_base {
    function init() {
        $this->title = get_string('pluginname', 'block_compile_discussions');
    }

    function applicable_formats() {
        return array('course-view' => true, 'site' => true);
    }

    function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;
	require_once($CFG->dirroot . '/mod/forum/lib.php');

        // Set up the content object
        if ($this->content !== NULL) {
            return $this->content;
        }
        $this->content = new stdClass;

        $course = $this->page->course;
        $modinfo = get_fast_modinfo($course);
        $forums = $DB->get_records('forum', array('course' => $course->id));
        $menu = array();

        foreach ($modinfo->instances['forum'] as $forumid=>$cm) { // This loop taken from forum/index.php
            if (!$cm->uservisible or !isset($forums[$forumid])) {
                continue;
            }

            $forum = $forums[$forumid];

            if (!$context = context_module::instance($cm->id)) {
                continue;   // Shouldn't happen
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

        // Now we have an array of all forums. Use this to populate a drop-down menu. Selecting an option
        // will call the compile.php script and pass the forum ID as argument.
        $actionurl = new moodle_url('/blocks/compile_discussions/compile.php');
        $select = new single_select($actionurl, 'forumid', $menu, null,
	    array(''=>get_string('chooseforum', 'block_compile_discussions')));
        $this->content = new stdClass;
	$this->content->text = html_writer::tag('p', get_string('select', 'block_compile_discussions'));
	$this->content->text .= $OUTPUT->render($select);

	return $this->content;
    }
}


