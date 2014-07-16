<?php

/**
 * Get User
 * ========
 * 
 * NOTE: Either "id" or both "auth_username" and "auth_password" are required.
 * 
 * POST variables:
 * "id" The id of the user you want to get. Defaults to the current user.
 * "auth_username" The current user's username, used for authentication.
 * "auth_password" The current user's password, used for authentication.
 * 
 * Return on success:
 * "success" The success message.
 * "user" The user object.
 * 
 * Return on error:
 * "error" The error message.
 * 
 * Ryff API <http://www.github.com/rfotino/ryff-api>
 * Released under the MIT License.
 */

if (isset($_POST['id'])) {
    $USER_ID = (int)$_POST['id'];
} else {
    define("REQUIRES_AUTHENTICATION", true);
}

set_include_path(implode(PATH_SEPARATOR, array(
    get_include_path(),
    __DIR__."/../../resources"
)));

require_once("global.php");

if (isset($USER_ID)) {
    $user = User::get_by_id($USER_ID);
    if ($user) {
        echo json_encode(array("success" => "Retrieved user.", "user" => $user));
    } else {
        echo json_encode(array("error" => "Invalid user id."));
    }
} else {
    echo json_encode($CURRENT_USER);
}
