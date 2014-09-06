<?php

/**
 * @class ApiTests
 * ===============
 * 
 * Implements unit tests for API scripts in /public_html/api.
 * 
 * Ryff API <http://www.github.com/rfotino/ryff-api>
 * Released under the Apache License 2.0.
 */

require_once("test-environment.class.php");

class ApiTests extends TestEnvironment {
    /**
     * An array of cookies sent with the post_to_api curl requests. Used to
     * keep users logged in.
     * 
     * @var array
     */
    private $cookies = array();
    
    /**
     * Sends a POST request to the API with the given field names and files.
     * Returns the decoded JSON object or false on failure. Also echoes an error
     * message on failure.
     * 
     * @param string $script_name The name of the API script without the file extension.
     * @param array $fields [optional] An associative $key => $value array.
     * @param array $files [optional] An associative $key => $filepath array.
     * @return mixed The decoded JSON response or false on failure.
     */
    private function post_to_api($script_name, $fields = array(), $files = array()) {
        $ch = curl_init();

        foreach ($files as $key => $path) {
            if (!file_exists($path)) {
                continue;
            }
            $fields[$key] = "@".$path.";filename=".basename($path);
        }
        curl_setopt($ch, CURLOPT_URL, SITE_ROOT."/api/$script_name.php");
        curl_setopt($ch, CURLOPT_COOKIE, implode("; ", $this->cookies));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $data = curl_exec($ch);
        if ($data === false) {
            echo "Fatal Error: Unable to complete HTTP request.\n";
            return false;
        }
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($data, 0, $header_size);
        $output = substr($data, $header_size);
        
        curl_close($ch);

        $temp_cookies = array();
        preg_match_all("/^Set-cookie: (.*?);/ism", $header, $temp_cookies);
        foreach( $temp_cookies[1] as $cookie ){
            $key = substr($cookie, 0, strpos($cookie, "="));
            $this->cookies[$key] = $cookie;
        }
        
        $obj = json_decode($output);
        if ($obj === null) {
            echo "Fatal Error: Invalid JSON: $output\n";
            return false;
        } else {
            return $obj;
        }
    }
    
    /**
     * Attempts to log in with the given username using the post_to_api method.
     * Returns true if the request succeeded, or false on failure.
     * 
     * @param string $username
     * @return boolean
     */
    private function log_user_in($username) {
        $fields = array("auth_username" => $username, "auth_password" => "password");
        $results = $this->post_to_api("login", $fields);
        return $results && property_exists($results, "success");
    }
    
    /**
     * Creates some fake credentials and creates a new user, with an avatar
     * image if one is available. Then checks to make sure the user is logged
     * in after creation.
     * 
     * @return boolean
     */
    protected function create_user_test() {
        $output = true;
        $fields = array(
            "username" => $this->get_unique_word(),
            "password" => "password",
            "name" => $this->get_words(2),
            "email" => $this->get_unique_word()."@example.com",
            "bio" => $this->get_words(10),
            "latitude" => 50,
            "longitude" => 50
        );
        $files = array();
        if (!empty($this->sample_avatars)) {
            $files["avatar"] = $this->sample_avatars[0];
        }
        $results = $this->post_to_api("create-user", $fields, $files);
        if (!$results || property_exists($results, "error")) {
            echo "Failed to create user.\n";
            return false;
        }
        $get_results = $this->post_to_api("get-user", array("id" => $results->user->id));
        if (!$get_results || property_exists($get_results, "error")) {
            echo "Failed to get user after creation.\n";
            $output = false;
        }
        User::delete($results->user->id);
        return $output;
    }
    
    /**
     * Creates a new user, attempts to log them in, then checks that the login
     * worked by calling get-user on the logged in user. Then the user is deleted.
     * 
     * @return boolean
     */
    protected function login_test() {
        $user = $this->get_test_user();
        $output = true;
        if (!$this->log_user_in($user->username)) {
            echo "Failed to log user in.\n";
            $output = false;
        }
        $results = $this->post_to_api("get-user", array("id" => $user->id));
        if (!$results || property_exists($results, "error")) {
            echo "Failed to get user after login.\n";
            $output = false;
        }
        User::delete($user->id);
        return $output;
    }
    
    /**
     * Creates a user, logs them in, logs them out, then tests that get-user
     * fails.
     * 
     * @return boolean
     */
    protected function logout_test() {
        $user = $this->get_test_user();
        $output = true;
        if (!$this->log_user_in($user->username)) {
            echo "Failed to log user in.\n";
            $output = false;
        }
        $results = $this->post_to_api("logout");
        if (!$results || property_exists($results, "error")) {
            echo "Failed to log user out.\n";
            $output = false;
        }
        $get_results = $this->post_to_api("get-user", array("id" => $user->id));
        if (!$get_results || property_exists($get_results, "success")) {
            echo "Error, not logged out after calling logout.\n";
            $output = false;
        }
        User::delete($user->id);
        return $output;
    }
    
    protected function add_apns_token_test() {
        $output = true;
        $user = $this->get_test_user();
        $this->log_user_in($user->username);
        $fields = array(
            "token" => str_repeat("0", 64),
            "uuid" => str_repeat("0", 36)
        );
        $results = $this->post_to_api("add-apns-token", $fields);
        if (!$results || property_exists($results, "error")) {
            echo "Failed to add APNs token (API level).\n";
            $output = false;
        }
        $tokens = PushNotification::get_apns_tokens($user->id);
        if (empty($tokens)) {
            echo "Failed to add APNs token (Database level).\n";
            $output = false;
        }
        User::delete($user->id);
        return $output;
    }
    
    protected function add_conversation_test() {
        $output = true;
        $user1 = $this->get_test_user();
        $user2 = $this->get_test_user();
        $this->log_user_in($user1->username);
        $results = $this->post_to_api("add-conversation", array("ids" => $user2->id));
        if (!$results || property_exists($results, "error")) {
            echo "Failed to add conversation.\n";
            $output = false;
        }
        $fail_results = $this->post_to_api("add-conversation", array("ids" => ""));
        if (!$fail_results || !property_exists($fail_results, "error")) {
            echo "Failed to detect conversation with not enough ids.\n";
            $output = false;
        }
        User::delete($user1->id);
        User::delete($user2->id);
        return $output;
    }
    
    protected function add_follow_test() {
        $output = true;
        $user1 = $this->get_test_user();
        $user2 = $this->get_test_user();
        $this->log_user_in($user1->username);
        $results = $this->post_to_api("add-follow", array("id" => $user2->id));
        if (!$results || property_exists($results, "error")) {
            echo "Failed to add follow.\n";
            $output = false;
        }
        User::delete($user1->id);
        User::delete($user2->id);
        return $output;
    }
    
    /**
     * Overrides abstract method run_test() from class TestEnvironment.
     * 
     * @return boolean If the tests succeeded or not.
     */
    protected function run_tests() {
        $tests = array(
            "create_user_test" => "Create user test",
            "login_test" => "Login test",
            "logout_test" => "Logout test",
            "add_apns_token_test" => "Add APNs token test",
            "add_conversation_test" => "Add conversation test",
            "add_follow_test" => "Add follow test"
        );
        foreach ($tests as $test => $message) {
            if (!$this->do_test($test, $message)) {
                return false;
            }
        }
        return true;
    }
}