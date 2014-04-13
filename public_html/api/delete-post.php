<?php

define("REQUIRES_AUTHENTICATION", true);

set_include_path(implode(PATH_SEPARATOR, array(
    get_include_path(),
    __DIR__."/../../resources"
)));

require_once("global.php");

$POST_ID = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$post = get_post_from_id($POST_ID);
if (!$post) {
    echo json_encode(array("error" => "No post to delete!"));
    exit;
}

$query = "DELETE FROM `posts`
          WHERE `post_id`='".$db->real_escape_string($POST_ID)."'
          AND `user_id`=".$db->real_escape_string($CURRENT_USER->id);
$results = $db->query($query);
if ($results) {
    //If there is an .mp3 file attached to this post, unlink the file
    if ($post->riff && $post->riff->id) {
        $path = RIFF_ABSOLUTE_PATH."/{$post->riff->id}.mp3";
        if (file_exists($path)) {
            unlink($path);
        }
    }
    echo json_encode(array("success" => "Successfully deleted post from user."));
    exit;
}

echo json_encode(array("error" => "Error deleting post from user."));