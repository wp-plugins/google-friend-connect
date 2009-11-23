<?php
/*
Copyright 2009 Google Inc.
Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

     http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
 */

if (!function_exists('add_action')) {
    require_once("../../../wp-config.php");
}
/**
 * the user is created using <gfc_id@friendconnect.google.com>
 * and the user display name is set to the current displayName 
 */

if (isset($_POST['username'])) {
  global $wpdb;
  
  // Extract POST into local variables to avoid confusion
  $uname = $_POST['username'];
  //$profile_id_url = $_POST['profile_id_url'];
  $profileurl = $_POST['profileurl'];
  $image_url = $_POST['image_url'];
  $gfcid = $_POST['uid'];
  
  // The usermeta field for each user is of the form:
  // userid    meta_key    meta_value
  // where
  //    userid is the key into the users database
  //    meta_key is of the form: <name>_fc_meta_key
  //    meta_value is the profileId
  $meta_key = $gfcid."_fc_meta_key";
  
  // Check if a user with this profileId and meta_key exists
  $metas = $wpdb->get_col( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '$meta_key' LIMIT 1") );   
  
  if (count($metas) > 0) {
    // We found me
    $userid = $metas[0];
  } else {
    // Since this user is not available, we'll create him.
    // First check if a user with the same name is present
	$pass = md5($gfcid.microtime()); //generate a random password
    $basev = $wpdb->get_col( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '$meta_key';") );
    $basev2 = $wpdb->get_col( $wpdb->prepare("SELECT user_login FROM $wpdb->users WHERE user_login = '$gfcid';") );
    $userid = wp_create_user($gfcid, $pass, $gfcid."@friendconnect.google.com"); 
    update_usermeta($userid, $meta_key, $pass);
  }
  // Log me in
  update_usermeta($userid, "user_url", $profileurl);
  update_usermeta($userid, "image_url", $image_url);
  update_usermeta($userid, "display_name", $uname);
  $cred['user_login'] = $gfcid;
  $cred['user_password'] = get_usermeta( $userid, $meta_key);
  wp_signon($cred);
}
die('200');

?>
