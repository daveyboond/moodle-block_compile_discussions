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

require_once('../../config.php');
require_once('../../mod/forum/lib.php');

global $DB, $PAGE, $OUTPUT;

$forumid = required_param('forumid', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);

// The parameter type must match the table name of a forum type.
if ($type != 'forum' && $type != 'hsuforum') {
    print_error('error:invalidtype', 'block_compile_discussions', '', $type);
}

// Get the forum with that ID.
$forum = $DB->get_record($type, array('id' => $forumid), '*', MUST_EXIST);

// From here on, $forum may be a record for either a normal forum or an hsuforum.
require_login($forum->course);

$course = $DB->get_record('course', array('id' => $forum->course));
$coursecontext = context_course::instance($course->id);
$cm = get_coursemodule_from_instance($type, $forum->id);
$context = context_module::instance($cm->id);

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    print_error('error:nopermission', 'block_compile_discussions');
}

// Stolen from /forum/view.php.


// Some capability checks.
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!has_capability('mod/forum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'forum'));
}

$forum->intro = trim($forum->intro);

// End of stuff stolen from /forum/view.php.


// Set up page and output header information.
$PAGE->set_url('/blocks/compile_discussions/compile.php', array('forumid' => $forum->id));

echo $OUTPUT->header();
echo $OUTPUT->heading("Forum: " . $forum->name);
echo $OUTPUT->box_start('mod_introbox');
echo $forum->intro;
echo $OUTPUT->box_end();

// Get discussions in reverse chronological order.
$discussions = $DB->get_records($type."_discussions", array('forum' => $forumid), "timemodified DESC");

// Loop through discussions.
foreach ($discussions as $discussion) {

    // Output discussion name.
    echo "<h3>Discussion: " . $discussion->name . "</h3>\n";

    // Get posts for this discussion in order of creation time.
    $posts = $DB->get_records($type."_posts", array("discussion" => $discussion->id), "created");

    // Loop through posts and recurse down threads, gathering child posts.
    $postsordered = array();
    $indent = 0;
    while ($post = array_shift($posts)) {
        $post->indent = $indent;
        $postsordered[] = $post;
        get_children($posts, $postsordered, $post->id, $indent);
    }

    // Output post information.
    // If an 'anonymous' field exists for this forum and is set, and the
    // post has a 'reveal' field that is not set, anonymise the author.
    foreach ($postsordered as $post) {
        for ($i = 0; $i < $post->indent; $i++) {
            echo "<blockquote>\n"; // Indent child posts.
        }
        echo "<p><strong>" . $post->subject . "</strong><br />\n";
        $user = $DB->get_record("user", array("id" => $post->userid)); // Get user details.
        if (isset($forum->anonymous) && $forum->anonymous == 1 && $post->reveal == 0) {
            $fullname = get_string('anonuser', 'block_compile_discussions');
        } else {
            $fullname = $user->firstname . " " . $user->lastname;
        }
        echo "<em>" . $fullname . ", "
            . strftime("%a %d %b %Y %H:%M", $post->modified) . "</em></p>\n";
        echo "<p>" . $post->message . "</p>\n";
        echo forum_print_attachments($post, $cm, "html");
        for ($i = 0; $i < $post->indent; $i++) {
            echo "</blockquote>\n";
        }
    }
}

echo $OUTPUT->footer();

exit;

function get_children (&$posts, &$postsordered, $id, &$indent) {
    // This function will searches the $posts array for any records that have 'parent' = $id,
    // removes them, and adds them to the end of $postsordered.
    $p = 0;
    $indent++;
    while ($p < count($posts)) {
        if ($posts[$p]->parent == $id) {
            if ($childpost = array_splice($posts, $p, 1)) { // Extract the child post as a single-element array.
                $childpost[0]->indent = $indent;
                $postsordered[] = $childpost[0];
                get_children($posts, $postsordered, $childpost[0]->id, $indent); // Recurse.
            }
        } else {
            $p++; // Only move pointer on if last post wasn't removed.
        }
    }
    $indent--;
    return;
}