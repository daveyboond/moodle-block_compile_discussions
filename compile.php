<?php

require_once('../../config.php');
require_once('../../mod/forum/lib.php');

global $CFG, $DB, $PAGE;

$forumid = required_param('forumid', PARAM_INT);

if (!$forum = $DB->get_record('forum', array('id' => $forumid))) {
    print_error('no_forum', 'block_compile_discussions', '', $forumid);
}

require_login($forum->course);

$course = $DB->get_record('course', array('id' => $forum->course));
$course_context = get_context_instance(CONTEXT_COURSE, $course->id);
$cm = get_coursemodule_from_instance('forum', $forum->id);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    print_error('no_permission', 'block_quickmail');
}

    // *** Stolen from /forum/view.php ***


/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/forum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'forum'));
    }

/// find out current groups mode
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);


    $forum->intro = trim($forum->intro);

    // *** End of stuff stolen from /forum/view.php ***



    // Output header information
    $url = qualified_me();
    if (($strpos = strpos($url, '?')) !== false) {
        $querystring = substr($url, $strpos);
    }
    echo "<span style=\"float: right\"><a href=\"" . $course_context->get_url() . "\">"
        . get_string('back', 'block_compile_discussions', $course->shortname) . "</a></span>";
    echo "<h1>Course: " . $course->fullname . "</h1>\n";
    echo "<h2>Forum: " . $forum->name . "</h2>\n";
    echo "<p>" . $forum->intro . "</p>\n";

    // Get discussions in reverse chronological order
    $discussions = $DB->get_records("forum_discussions", array('forum' => $forumid), "timemodified DESC");

    // Loop through discussions
    foreach ($discussions as $discussion) {

        // Output discussion name
        echo "<h3>Discussion: " . $discussion->name . "</h3>\n";

        // Get posts for this discussion in order of creation time
        $posts = $DB->get_records("forum_posts", array("discussion" => $discussion->id), "created");

        // Loop through posts and recurse down threads, gathering child posts
        $postsordered = array();
        $indent = 0;
        while ($post = array_shift($posts)) {
            $post->indent = $indent;
            $postsordered[] = $post;
            get_children($posts, $postsordered, $post->id, $indent);
        }

        // Output post information
        foreach ($postsordered as $post) {
            for ($i = 0; $i < $post->indent; $i++) echo "<blockquote>\n"; // Indent child posts
            echo "<p><strong>" . $post->subject . "</strong><br />\n";
            $user =  $DB->get_record("user", array("id" => $post->userid)); // Get user details
            echo "<em>" . $user->firstname . " " . $user->lastname . ", "
                . strftime("%a %d %b %Y %H:%M", $post->modified) . "</em></p>\n";
            echo "<p>" . $post->message . "</p>\n";
			echo forum_print_attachments($post, $cm, "html");
            for ($i = 0; $i < $post->indent; $i++) echo "</blockquote>\n";
        }
    }

    exit;

    function get_children (&$posts, &$postsordered, $id, &$indent) {
        // This function will searches the $posts array for any records that have 'parent' = $id,
        // removes them, and adds them to the end of $postsordered
        $p = 0;
        $indent++;
        while ($p < count($posts)) {
            if ($posts[$p]->parent == $id) {
                if ($childpost = array_splice($posts, $p,1)) { // Extract the child post as a single-element array
                    $childpost[0]->indent = $indent;
                    $postsordered[] = $childpost[0];
                    get_children($posts, $postsordered, $childpost[0]->id, $indent); // Recurse
                }
            } else {
                $p++; // Only move pointer on if last post wasn't removed
            }
        }
        $indent--;
        return;
    }