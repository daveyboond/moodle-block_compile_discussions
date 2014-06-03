<?php

require_once('../../config.php');
require_once('../../mod/forum/lib.php');

global $DB, $PAGE, $OUTPUT;

$forumid = required_param('forumid', PARAM_INT);

if (!$forum = $DB->get_record('forum', array('id' => $forumid))) {
    print_error('no_forum', 'block_compile_discussions', '', $forumid);
}

require_login($forum->course);

$course = $DB->get_record('course', array('id' => $forum->course));
$course_context = context_course::instance($course->id);
$cm = get_coursemodule_from_instance('forum', $forum->id);
$context = context_module::instance($cm->id);

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    print_error('no_permission', 'block_quickmail');
}

    // *** Stolen from /forum/view.php ***


    // Some capability checks
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/forum:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'forum'));
    }

    $forum->intro = trim($forum->intro);

    // *** End of stuff stolen from /forum/view.php ***


    // Set up page and output header information.
    $PAGE->set_url('/blocks/compile_discussions/compile.php', array('forumid' => $forum->id));
    
    echo $OUTPUT->header();
    echo $OUTPUT->heading("Forum: " . $forum->name);
    echo $OUTPUT->box_start('mod_introbox');
    echo $forum->intro;
    echo $OUTPUT->box_end();

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

    echo $OUTPUT->footer();
    
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