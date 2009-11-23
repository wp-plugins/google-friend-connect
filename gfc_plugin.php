<?php
/*
 Plugin Name: Google Friend Connect Plugin
 Description: This plugin allows a user to authenticate using his or her
 <a href="http://www.google.com/friendconnect/">Friend Connect</a> 
 id to signin. More description can be found <a href="http://code.google.com/p/wp-gfc/">here</a>.
 Plugin URI: http://demo02.globant.com/wp_native_comments
 Version: 1.1.3
 Author: Mauro Gonzalez
 Author URI: http://demo02.globant.com/wp_native_comments

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


if ( !defined('WP_CONTENT_URL') )
define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

define('SOCIALBAR_POS_TOP',1);
define('SOCIALBAR_POS_BOTTOM',0);

// Guess the location
$gfcpluginpath = WP_CONTENT_URL.'/plugins/' 
  .plugin_basename(dirname(__FILE__)).'/';


include_once(ABSPATH . 'wp-includes/registration.php');
include_once(ABSPATH . 'wp-includes/comment.php');
include_once(ABSPATH . 'wp-includes/user.php');

/**
 * WP Hooks
 */
// wp_head is where we put all the javascript and css code
add_action( 'wp_head', 'gfc_wp_head');
/**
 * init the fc widgets
 */
add_action("plugins_loaded", "init_gfc_widgets");

/**
 * init the social bar
 */
add_action('wp_footer', 'init_fc_socialbar');

/**
 * capture the save post action (and trigger a friendconnect activity)
 */
add_action('save_post', 'gfc_save_post');

/**
 * catch plugin activation and fix user meta fo backwards compat
 */
register_activation_hook( __FILE__, 'initialize_friendconnect');
/**
 * This filter takes care of pulling out the avatar image 
 * for us to be displayed beside the comment. 
 * For our plugin, the avatar image is the one that
 * is obtained from the FC thumbnailUrl function call (see code below)
 */
add_filter('get_avatar', 'fc_wp_get_avatar', 20, 5);
/**
 * this filter gets the comments template and adds the FC required code to it
 */
add_filter('comments_template', 'fc_comments_template');
/**
 * this filter hides the xx comments link when GFC comments are enabled
 */
add_filter('comments_popup_link_attributes', 
  'fc_comments_popup_link_attributes');
/**
 * this filter captures the comments post event and triggers 
 * an activity to FC activity stream
 */
add_filter('comment_post', 'fc_comment_post');

/**
 * fixes old passwords 
 * @return void
 */
function initialize_friendconnect() {
  global $wpdb;

  $query = "SELECT ID, user_login FROM `{$wpdb->prefix}users` " 
  . "WHERE user_email like '%@friendconnect.google.com';";
  $res = $wpdb->get_results($query, $output = ARRAY_A);
  if(count($res)>0){
    foreach($res as $row){
       if(is_numeric($row['user_login'])) {
         $meta_key = $row['user_login']."_fc_meta_key";
         $pass = md5($row['user_login'].microtime());
         update_usermeta($row['ID'], $meta_key, $pass);
         wp_update_user(array('ID'=>$row['ID'], 'user_pass'=>$pass));
       }
    }
  }  
}

/**
 * populates an the post publishing action to fc activity stream
 * @param $id post id
 * @return boolean
 * 
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function gfc_save_post($id) {
  $options = get_option("FriendConnect");
  $post = get_post($id);
  if($post->post_status != 'publish') {
    return false;
  }


  $site_id = $options['gfcid'];
  $fcauth_token = $_COOKIE["fcauth" . $site_id];
  if(!$fcauth_token) {
    return false;
  }
  require_once realpath(dirname(__FILE__))."/osapi/osapi.php";
  $provider = new osapiFriendConnectProvider();
  $auth = new osapiFCAuth($fcauth_token);
  $osapi = new osapi($provider,$auth);
  $osapi->setStrictMode(true);
  $batch = $osapi->newBatch();

  $activity = new osapiActivity(null, null);
  $activity->setField('title', "New post " 
    . $post->post_title." has been published");
  $activity->setField('body', substr($post->post_content,0,140).' ...');

  $create_params = array(
        'userId' => '@me',
        'groupId' => '@self',
        'activity' => $activity,
        'appId' => $site_id
  );
  $batch->add($osapi->activities->create(
    $create_params), 'createActivity');
  $Activeties = $batch->execute();
}

/**
 * populates the comment action to FC activity stream
 * @param $id comment id
 * @return boolean
 * 
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_comment_post($id) {

  $options = get_option("FriendConnect");
  $comment = get_comment($id);
  $site_id = $options['gfcid'];
  $fcauth_token = $_COOKIE["fcauth" . $site_id];
  if(!$fcauth_token) {
    return false;
  }
  require_once realpath(dirname(__FILE__))."/osapi/osapi.php";
  $provider = new osapiFriendConnectProvider();
  $auth = new osapiFCAuth($fcauth_token);
  $osapi = new osapi($provider,$auth);
  $osapi->setStrictMode(true);
  $batch = $osapi->newBatch();

  $activity = new osapiActivity(null, null);
  $activity->setField('title', $comment->comment_author.' posted a comment');
  $activity->setField('body', $comment->comment_content);

  $create_params = array(
        'userId' => '@me',
        'groupId' => '@self',
        'activity' => $activity,
        'appId' => $site_id
  );
  $batch->add($osapi->activities->create($create_params), 'createActivity');
  $Activeties = $batch->execute();

}
/**
 * hides the comments link by adding a display:none 
 * to the anchor element style
 * @return void
 * 
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_comments_popup_link_attributes() {
  $options = get_option("FriendConnect");
  if($options['overridecomments']) {
    return 'style="display: none;"';
  }
}

/**
 * replaces the comments template as required
 * according to the user selected comments mechanism
 * @param $file
 * @return void
 * 
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_comments_template($file) {
  $options = get_option("FriendConnect");
  if($options['overridecomments']) {
    return realpath(dirname(__FILE__)).'/fc_comments_template_wrapper.php';
    return $file;
  } else {
    return realpath(dirname(__FILE__)).'/native_comments_wrapper.php';
  }
}

/**
 *  The fc_wp_get_avatar function.
 * After wordpress renders each comment, it calls the filter get_avatar
 * In this plugin, we have implemented this filter to return the FC url
 * location that we have stored in the wp_metadata database table. The
 * code to put it into the database is in server_code.php that comes
 * with this plugin
 * All that we are doing here is to get the email, lookup the userid from
 * the user database and then get the image_url from the wp_metadata table
 * 
 */

// Added wpdb prefix to query.

function fc_wp_get_avatar($avatar, $comment, $size, $default, $alt) {
  global $wpdb;

  if (!empty($comment->user_id)) {
    $email = $comment->comment_author_email;

    $query = "SELECT * FROM `{$wpdb->prefix}users` " 
    . "WHERE user_email = '$email' LIMIT 1;";
    $res = $wpdb->get_col($query);
    // We dont know if this user, so return whatever was given to me
    if (count($res) <= 0) {
      return $avatar;
    }
    // Do not change the admin's image
    if ($res[0] == 1) {
      return $avatar;
    }
    // Get the image and return the altered $avatar
    $image_url = get_usermeta( $res[0], "image_url");
    return "<img alt='' src='{$image_url}' class='avatar avatar-{$size}" 
    . " photo avatar-default' height='{$size}' width='{$size}' />";
  } else {
    return $avatar;
  }
}

/**
 * init_gfc_widgets
 * Initializes the FC widgets
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_gfc_widgets() {
  init_fc_members_widget();
  init_fc_recommendations_widget();
  init_fc_site_wide_comments_widget();
  init_fc_polls_widget();
  init_fc_newsletter_widget();
  init_fc_featured_content_widget();
  init_fc_adsense_widget();
}

/**
 * render_skin
 * @return string json representation of the gadgets skin
 * according to friendConnect settings
 * 
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function render_skin() {
   $options = get_option("FriendConnect");
   $ret = array();
   $skin_opts = array(
      'BORDER_COLOR',
      'ENDCAP_BG_COLOR',
      'ENDCAP_TEXT_COLOR',
      'ENDCAP_LINK_COLOR',
      'ALTERNATE_BG_COLOR',
      'CONTENT_BG_COLOR',
      'CONTENT_LINK_COLOR',
      'CONTENT_TEXT_COLOR',
      'CONTENT_SECONDARY_LINK_COLOR',
      'CONTENT_SECONDARY_TEXT_COLOR',
      'CONTENT_HEADLINE_COLOR'
      );
    foreach($skin_opts as $opt) {
      if($options[$opt]!='') {
        $ret[$opt] = "#$options[$opt]";
      } else {
        $ret[$opt] = 'transparent';
      }
    }
    
    $ret['BG_COLOR'] = $ret['CONTENT_BG_COLOR'];
    $ret['ANCHOR_COLOR'] = $ret['CONTENT_LINK_COLOR'];
    $ret['FONT_COLOR'] = $ret['CONTENT_TEXT_COLOR'];
    
    if ($options['FONT_FAMILY']!='default') {
      $ret['FONT_FAMILY'] = $options['FONT_FAMILY'];
    } else {
      unset($ret['FONT_FAMILY']);
    }
    return json_encode($ret);
}
/**
 * gfc_wp_head
 * callback function for the wp_head hook
 * @return unknown_type
 */
function gfc_wp_head() {

  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $user = wp_get_current_user();
  wp_print_scripts( array( 'sack' ));
  ?>
  <script	type="text/javascript" src="http://www.google.com/jsapi"></script>
  <script type="text/javascript">
    google.load('friendconnect', '0.8');
  </script>
  <script	type="text/javascript" src="<?php echo $gfcpluginpath; ?>/googlefriendconnect.js"></script>
  <script type="text/javascript">
      var SITE_ID = "<?php echo $options['gfcid']; ?>";
      var FC_PLUGIN_URL = "<?php echo $gfcpluginpath; ?>";
      var FC_LOGOUT_URL = "<?php echo wp_logout_url(get_permalink()); ?>";
      var FC_USER_ID = <?php echo $user->id?>;
      google.friendconnect.container.setParentUrl(FC_PLUGIN_URL);
    </script>
  <?php
}

/**
 * gfc_check_logged_in
 * tell whether the currently logged in user is a FC user or not.
 * @return boolean true if logged in false otherwise
 * 
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function gfc_check_logged_in() {
  $user = wp_get_current_user();
  $email = $user->data->user_email;
  if ( strpos($email,"friendconnect.google.com") !== false ) {
    return true;
  }
}
/**
 * gfc_wp_comment_form
 *
 * invoked by the comment_form hook, renders the divs required to render
 * the sign in option at the bottom of the comments form.
 * If the option to add friendconnect comments is selected, also
 * loads the js function to fix the logout url
 *
 * @param $post_id
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */

function gfc_wp_comment_form($post_id) {
  ?>
<div
	id="div-175458888026420902" style="display: block;"></div>
<br />
<script type="text/javascript">
  var FC_ELEMENT_ID = 'div-175458888026420902';	
  GFC.fixUrls();
  google.friendconnect.container.initOpenSocialApi({site: SITE_ID,onload: function(securityToken) { GFC.init(securityToken); }});
</script>
  <?php
}
/**
 * init_fc_members_widget
 * registers the members widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_fc_members_widget() {
  register_sidebar_widget("GFC Members", "fc_members_widget");
  register_widget_control("GFC Members", "fc_members_widget_control");
}

/**
 * init_fc_recommendations_widget
 * registers the recommendations widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */

function init_fc_recommendations_widget() {
  register_sidebar_widget("GFC Recommendations", "fc_recommendations_widget");
  register_widget_control("GFC Recommendations", "fc_recommendations_widget_control");
}
/**
 * init_fc_site_wide_comments_widget
 * registers the comments  widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_fc_site_wide_comments_widget() {
  register_sidebar_widget("GFC Site Wide Comments", "fc_site_wide_comments_widget");
  register_widget_control("GFC Site Wide Comments", "fc_site_wide_comments_widget_control");
}

/**
 * init_fc_polls_widget
 * registers the interests polls  widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_fc_polls_widget() {
  register_sidebar_widget("GFC Polls", "fc_polls_widget");
  register_widget_control("GFC Polls", "fc_polls_widget_control");
}
/**
 * init_fc_newsletter_widget
 * registers the newsletter  widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_fc_newsletter_widget() {
  register_sidebar_widget("GFC Newsletter", "fc_newsletter_widget");
  register_widget_control("GFC Newsletter", "fc_newsletter_widget_control");
}
/**
 * init_fc_featured_content_widget
 * registers the featured content  widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_fc_featured_content_widget() {
  register_sidebar_widget("GFC Featured content", "fc_featured_content_widget");
  register_widget_control("GFC Featured content", "fc_featured_content_widget_control");
}
/**
 * init_fc_adsense_widget
 * registers the adsense  widget as a sidebar widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function init_fc_adsense_widget() {
  register_sidebar_widget("GFC Adsense", "fc_adsense_widget");
  register_widget_control("GFC Adsense", "fc_adsense_widget_control");
}
/**
 * fc_members_widget
 * renders the members widget
 * @return unknown_type
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_members_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Members");
  echo $before_widget;
  echo $before_title . $wg_options['FriendConnectMembers_title']. $after_title;
  ?>
  <div id="div-6708123552665042589" 
  	style="width: <?php echo $wg_options['FriendConnectMembers_width'] 
  	  ? $wg_options['FriendConnectMembers_width'].'px;' : '100%;'?>" class="widget">
  </div>
  <script type="text/javascript">
  	  var members_skin = <?php echo render_skin();?>;	
      var FC_PLUGIN_URL = "<?php echo $gfcpluginpath; ?>";
      members_skin['NUMBER_ROWS'] = '<?php echo $wg_options['FriendConnectMembers_rows'];?>';
      google.friendconnect.container.renderMembersGadget(
        { id: 'div-6708123552665042589', site: '<?php echo $options['gfcid']?>' },
        members_skin
      );
    </script>
  <?php
  echo $after_widget;
}
/**
 * fc_members_widget_control
 * renders and handles the options for the members widget
 * in the admin panel.
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_members_widget_control() {
  $data = get_option("GFC Members");
  ?>
  <p><label for="FriendConnectMembers_title">Title: <input type="text"
  name="FriendConnectMembers_title" id="FriendConnectMembers_title"
  value="<?php echo (isset($data['FriendConnectMembers_width']) ? $data['FriendConnectMembers_title'] : '' )?>" />
</label><small>Blank means no title</small></p>
<p><label for="FriendConnectMembers_width">Width: <input type="text"
	name="FriendConnectMembers_width" id="FriendConnectMembers_width"
	value="<?php echo (isset($data['FriendConnectMembers_width']) ? $data['FriendConnectMembers_width'] : 0 )?>" />px
</label></p>
<p><label for="FriendConnectMembers_rows">Member rows to display: <input
	type="text" name="FriendConnectMembers_rows" id="FriendConnectMembers"
	value="<?php echo (isset($data['FriendConnectMembers_rows']) ? $data['FriendConnectMembers_rows'] : 4 )?>"
	style="width: 30px;" /> </label></p>
  <?php
  if(isset($_POST['FriendConnectMembers_rows'])) {
    $data['FriendConnectMembers_rows'] = attribute_escape((isset($_POST['FriendConnectMembers_rows']) && $_POST['FriendConnectMembers_rows'] > 0 ) ? $_POST['FriendConnectMembers_rows']:1);
    $data['FriendConnectMembers_width'] = attribute_escape($_POST['FriendConnectMembers_width']);
    $data['FriendConnectMembers_title'] = attribute_escape($_POST['FriendConnectMembers_title']);
    update_option('GFC Members', $data);
  }
}
/**
 * renders the recommendations widget
 * @return void
 */
function fc_recommendations_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Recommendations");
  echo $before_widget;
  echo $before_title.$wg_options['FriendConnectRecommendations_title'].$after_title;
  ?>
  <div id="div-2154215263602388786" class="widget" 
  	style="width: <?php echo $wg_options['FriendConnectRecommendations_width'] ? 
  	  $wg_options['FriendConnectRecommendations_width'].'px;': '100%';?>">
  	</div>
  <script type="text/javascript">
      var FC_PLUGIN_URL = "<?php echo $gfcpluginpath; ?>";
      var rec_skin = <?php echo render_skin();?>;	
      rec_skin['HEADER_TEXT'] = "<?php  echo $wg_options['FriendConnectRecommendations_header']?>";
      rec_skin['RECOMMENDATIONS_PER_PAGE'] = '<?php  echo $wg_options['FriendConnectRecommendations_rows']?>';
      google.friendconnect.container.renderOpenSocialGadget(
          { id: 'div-2154215263602388786',
            url:'http://www.google.com/friendconnect/gadgets/recommended_pages.xml',
            site: '<?php echo $options['gfcid']?>',
            'view-params':{"docId":"recommendedPages"}
          },
          rec_skin);
    </script>
  <?php
  echo $after_widget;
}
/**
 * renders the recommendations widget controls
 * @return void
 */
function fc_recommendations_widget_control() {
  $data = get_option("GFC Recommendations");
  ?>
  <p><label for="FriendConnectRecommendations_title">Title: <input
    type="text" name="FriendConnectRecommendations_title" id="FriendConnectRecommendations_title"
    value="<?php echo (isset($data['FriendConnectRecommendations_title']) ? $data['FriendConnectRecommendations_title'] : '' )?>"
     /> </label></p>
  <p><label for="FriendConnectRecommendations_header">Header: <input
  	type="text" name="FriendConnectRecommendations_header" id="FriendConnectRecommendations_header"
  	value="<?php echo (isset($data['FriendConnectRecommendations_header']) ? $data['FriendConnectRecommendations_header'] : 'Recommended Posts' )?>"
  	 /> </label></p>
  <p><label for="FriendConnectRecommendations_btntxt">Button Text: <input
  	type="text" name="FriendConnectRecommendations_btntxt" id="FriendConnectRecommendations_btntxt"
  	value="<?php echo (isset($data['FriendConnectRecommendations_btntxt']) ? $data['FriendConnectRecommendations_btntxt'] : 'Recommend it!' )?>"
  	 /> </label></p>
  <p><label for="FriendConnectRecommendations_width">Width: <input type="text"
  	name="FriendConnectRecommendations_width" id="FriendConnectRecommendations_width"
  	value="<?php echo (isset($data['FriendConnectRecommendations_width']) ? $data['FriendConnectRecommendations_width'] : 200 )?>" 
  	style="width: 40px;"/>px
  </label></p>
  <p><label for="FriendConnectRecommendations_rows">Recommendations per page: <input
  	type="text" name="FriendConnectRecommendations_rows" id="FriendConnectRecommendations_rows"
  	value="<?php echo (isset($data['FriendConnectRecommendations_rows']) ? $data['FriendConnectRecommendations_rows'] : 5 )?>"
  	style="width: 30px;" /> </label></p>
  <?php
  if(isset($_POST['FriendConnectRecommendations_btntxt'])) {
    $data['FriendConnectRecommendations_rows'] = attribute_escape((isset($_POST['FriendConnectRecommendations_rows']) 
      && $_POST['FriendConnectRecommendations_rows'] > 0 ) ? $_POST['FriendConnectRecommendations_rows']:5);
    $data['FriendConnectRecommendations_width'] = attribute_escape($_POST['FriendConnectRecommendations_width']);
    $data['FriendConnectRecommendations_header'] = attribute_escape($_POST['FriendConnectRecommendations_header']);
    $data['FriendConnectRecommendations_btntxt'] = attribute_escape($_POST['FriendConnectRecommendations_btntxt']);
    $data['FriendConnectRecommendations_title'] = attribute_escape($_POST['FriendConnectRecommendations_title']);
    update_option('GFC Recommendations', $data);
  }
}

/**
 * fc_site_wide_comments_widget
 * renders fc site wide comments widget
 * @return unknown_type
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_site_wide_comments_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Site Wide Comments");
  echo $before_widget;
  echo $before_title.$wg_options['FCSiteWideComments_title'].$after_title;
  ?>
  <div id="div-3351075849475840924" 
  	style="width: <?php echo $wg_options['FCSiteWideComments_width'] 
  	  ? $wg_options['FCSiteWideComments_width'].'px;' : '100%;'?>" class="widget">
  </div>
  <script type="text/javascript">
  	  var swc_skin = <?php echo render_skin();?>;	
      var FC_PLUGIN_URL = "<?php echo $gfcpluginpath; ?>";
      swc_skin['POSTS_PER_PAGE'] = '<?php echo $wg_options['FCSiteWideComments_rows'];?>';
      swc_skin['HEADER_TEXT'] = '<?php echo $wg_options['FCSiteWideComments_header'];?>';
      swc_skin['DEFAULT_COMMENT_TEXT'] = '<?php echo $wg_options['FCSiteWideComments_dflt'];?>';
      
      google.friendconnect.container.renderWallGadget(
    		  { id: 'div-3351075849475840924',
    		    site: '<?php echo $options['gfcid']?>',
    		    'view-params':{"disableMinMax":"false",
        		    "scope":"SITE","allowAnonymousPost":"<?php echo $wg_options['FCSiteWideComments_allowanon'] ? 'true':'false'?>",
        		    "features":"<?php echo $wg_options['FCSiteWideComments_allowvideo'] ? 'video,':''?>comment","startMaximized":"true"}
    		  },
    		  swc_skin);
    </script>
  <?php
  echo $after_widget;
}
/**
 * fc_site_wide_comments_widget_control
 * renders the controls for the site wide comments widget
 * in the admin panel.
 * 
 * @return void
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_site_wide_comments_widget_control() {
 $data = get_option("GFC Site Wide Comments");
  ?>
  <p><label for="FCSiteWideComments_title">Title: <input
    type="text" name="FCSiteWideComments_title" id="FCSiteWideComments_title"
    value="<?php echo (isset($data['FCSiteWideComments_title']) ? $data['FCSiteWideComments_title'] : '' )?>"
     /> </label></p>
  <p><label for="FCSiteWideComments_header">Header: <input
  	type="text" name="FCSiteWideComments_header" id="FCSiteWideComments_header"
  	value="<?php echo (isset($data['FCSiteWideComments_header']) ? $data['FCSiteWideComments_header'] : 'Comments' )?>"
  	 /> </label></p>
  <p><label for="FCSiteWideComments_dflt">Default comment text: <input
  	type="text" name="FCSiteWideComments_dflt" id="FCSiteWideComments_dflt"
  	value="<?php echo (isset($data['FCSiteWideComments_dflt']) ? $data['FCSiteWideComments_dflt'] : 'Enter your comment here' )?>"
  	 /> </label></p>
  <p><label for="FCSiteWideComments_allowvideo">Allow video: <input
  	type="checkbox" name="FCSiteWideComments_allowvideo" id="FCSiteWideComments_allowvideo"
  	value="1" <?php echo (isset($data['FCSiteWideComments_allowvideo']) ? 'checked="checked"' : '' )?>
  	 /> </label></p>
  <p><label for="FCSiteWideComments_allowanon">Allow anonymous posts: <input
  	type="checkbox" name="FCSiteWideComments_allowanon" id="FCSiteWideComments_allowanon"
  	value="1" <?php echo (isset($data['FCSiteWideComments_allowanon']) ? 'checked="checked"' : '' )?>
  	 /> </label></p>
  <p><label for="FCSiteWideComments_width">Width: <input type="text"
  	name="FCSiteWideComments_width" id="FCSiteWideComments_width"
  	value="<?php echo (isset($data['FCSiteWideComments_width']) ? $data['FCSiteWideComments_width'] : 200 )?>" 
  	style="width: 40px;"/>px
  </label></p>
  <p><label for="FCSiteWideComments_rows">Posts per page: <input
  	type="text" name="FCSiteWideComments_rows" id="FCSiteWideComments_rows"
  	value="<?php echo (isset($data['FCSiteWideComments_rows']) ? $data['FCSiteWideComments_rows'] : 5 )?>"
  	style="width: 30px;" /> </label></p>
  <?php
  if(isset($_POST['FCSiteWideComments_header'])) {
    $data['FCSiteWideComments_rows'] = attribute_escape((isset($_POST['FCSiteWideComments_rows']) 
      && $_POST['FCSiteWideComments_rows'] > 0 ) ? $_POST['FCSiteWideComments_rows']:5);
    $data['FCSiteWideComments_title'] = attribute_escape($_POST['FCSiteWideComments_title']);
    $data['FCSiteWideComments_width'] = attribute_escape($_POST['FCSiteWideComments_width']);
    $data['FCSiteWideComments_header'] = attribute_escape($_POST['FCSiteWideComments_header']);
    $data['FCSiteWideComments_dflt'] = attribute_escape($_POST['FCSiteWideComments_dflt']);
    $data['FCSiteWideComments_allowvideo'] = $_POST['FCSiteWideComments_allowvideo'] ? true : false;
    $data['FCSiteWideComments_allowanon'] = $_POST['FCSiteWideComments_allowanon'] ? true : false;
    update_option('GFC Site Wide Comments', $data);
  } 
}
/**
 * fc_polls_widget
 * renders fc polls widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_polls_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Polls");
  echo $before_widget;
  echo $before_title.$wg_options['FCPolls_title'].$after_title;
  ?>
  <div id="friendconnect_polls"></div>
    <!-- Render the gadget into a div. -->
    <script type="text/javascript">
    var GFC_polls_skin = <?php echo render_skin();?>;
    var FC_PLUGIN_URL = "<?php echo $gfcpluginpath; ?>";
     google.friendconnect.container.renderOpenSocialGadget(
     { id: 'friendconnect_polls',
       url:'http://www.google.com/friendconnect/gadgets/poll.xml',
       site: '<?php echo $options['gfcid']?>' },
      GFC_polls_skin);
    </script>  
  <?php 
  echo $after_widget;
}

/**
 * fc_polls_widget_control
 * renders the controls for the interests polls widget
 * in the admin panel.
 * 
 * @return void
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_polls_widget_control() {
  $data = get_option("GFC Polls");
  ?>
  <p><label for="FCPolls_title">Title: <input
    type="text" name="FCPolls_title" id="FCPolls_title"
    value="<?php echo (isset($data['FCPolls_title']) ? $data['FCPolls_title'] : '' )?>"
     /> </label></p>
  <?php
  if(isset($_POST['FCPolls_title'])) {
    $data['FCPolls_title'] = attribute_escape($_POST['FCPolls_title']); 
    update_option('GFC Polls', $data);
  }
}
/**
 * fc_newsletter_widget
 * renders fc newsletter widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_newsletter_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Newsletter");
  echo $before_widget;
  echo $before_title.$wg_options['Newsletter_title'].$after_title;
  ?>
  <div id="friendconnect_newsletter"></div>
    <!-- Render the gadget into a div. -->
    <script type="text/javascript">
    var GFC_newsletter_skin = <?php echo render_skin();?>;
    google.friendconnect.container.renderOpenSocialGadget(
     { id: 'friendconnect_newsletter',
       url:'http://www.google.com/friendconnect/gadgets/newsletterSubscribe.xml',
       site: '<?php echo $options['gfcid']?>',
       'view-params':{},
       'prefs':{
         "newsletterHeadlineText":"<?php echo $wg_options['Newsletter_headline_txt']?>",
         "newsletterStandardText":"<?php echo $wg_options['Newsletter_std_txt']?>"}
   },
    GFC_newsletter_skin);
      </script>
  <?php 
  echo $after_widget;
}
/**
 * fc_newsletter_widget_control
 * renders the controls for the newsletter widget
 * in the admin panel.
 * 
 * @return void
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_newsletter_widget_control() {
 $data = get_option("GFC Newsletter");
  ?>
  <p><label for=""Newsletter_title"">Title: <input
    type="text" name="Newsletter_title" id="Newsletter_title"
    value="<?php echo (isset($data['Newsletter_title']) ? $data['Newsletter_title'] : '' )?>"
     /> </label></p>
  <p><label for="Newsletter_headline_txt">Header: <input
    type="text" name="Newsletter_headline_txt" id="Newsletter_headline_txt"
    value="<?php echo (isset($data['Newsletter_headline_txt']) ? $data['Newsletter_headline_txt'] : 'Newsletter Sign up' )?>"
     /> </label></p>
  <p><label for="Newsletter_std_txt">Standard text: <input
    type="text" name="Newsletter_std_txt" id="Newsletter_std_txt"
    value="<?php echo (isset($data['Newsletter_std_txt']) ? $data['Newsletter_std_txt'] : 'Sign up for our newsletter' )?>"
     /> </label></p>
  <?php
  if(isset($_POST['Newsletter_headline_txt'])) {
    $data['Newsletter_headline_txt'] = attribute_escape($_POST['Newsletter_headline_txt']); 
    $data['Newsletter_std_txt'] = attribute_escape($_POST['Newsletter_std_txt']);
    $data['Newsletter_title'] = attribute_escape($_POST['Newsletter_title']);
    update_option('GFC Newsletter', $data);
  } 
}
/**
 * fc_featured_content_widget
 * renders fc featured content widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_featured_content_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Featured content");
  echo $before_widget;
  echo $before_title.$wg_options['Featured_content_title'].$after_title;
  ?>
  <div id="friendconnect_featured_content"></div>
    <!-- Render the gadget into a div. -->
    <script type="text/javascript">
    var GFC_featured_skin = <?php echo render_skin();?>;
    google.friendconnect.container.renderOpenSocialGadget(
     { id: 'friendconnect_featured_content',
       url:'http://www.google.com/friendconnect/gadgets/content_reveal.xml',
       site: '<?php echo $options['gfcid']?>',
       'prefs':{
         "showHeaderTitle":"1",
         "customSiteRestriction":"",
         "customHeaderTitle":"<?php echo $wg_options['Featured_content_headline_txt']?>"}
   },
   GFC_featured_skin);
      </script>
  <?php 
  echo $after_widget;
}
/**
 * fc_featured_content_widget_control
 * renders the controls for the featured content widget
 * in the admin panel.
 * 
 * @return void
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_featured_content_widget_control() {
  $data = get_option("GFC Featured content");
  ?>
  <p><label for="Featured_content_title">Title: <input
    type="text" name="Featured_content_title" id="Featured_content_title"
    value="<?php echo (isset($data['Featured_content_title']) ? $data['Featured_content_title'] : '' )?>"
     /> </label></p>
  <p><label for="Featured_content_headline_txt">Header: <input
    type="text" name="Featured_content_headline_txt" id="Featured_content_headline_txt"
    value="<?php echo (isset($data['Featured_content_headline_txt']) ? $data['Featured_content_headline_txt'] : 'Featured Content' )?>"
     /> </label></p>
  <?php
  if(isset($_POST['Featured_content_headline_txt'])) {
    $data['Featured_content_headline_txt'] = attribute_escape($_POST['Featured_content_headline_txt']); 
    $data['Featured_content_title'] = attribute_escape($_POST['Featured_content_title']);
    update_option('GFC Featured content', $data);
  } 
}
/**
 * fc_adsense_widget
 * renders fc featured content widget
 * @return void
 *
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_adsense_widget($args) {
  extract($args);
  global $gfcpluginpath;
  $options = get_option("FriendConnect");
  $wg_options = get_option("GFC Adsense");
  $size = $wg_options['Adsense_widget_ad_size'];
  $height = substr($size, strpos($size, 'x')+1,strlen($size));
  echo $before_widget;
  echo $before_title.$wg_options['Adsense_widget_title'].$after_title;
  ?>
  <div id="friendconnect_adsense_content"></div>
    <!-- Render the gadget into a div. -->
    <script type="text/javascript">
    var GFC_adsense_skin = <?php echo render_skin();?>;
    google.friendconnect.container.renderAdsGadget(
     { id: 'friendconnect_adsense_content',
       height:'<?php echo $height?>',
       site: '<?php echo $options['gfcid']?>',
       'prefs':{
         "google_ad_client":"<?php echo $wg_options['Adsense_widget_ad_client']?>",
         "google_ad_host":"<?php echo $wg_options['Adsense_widget_ad_host']?>",
         "google_ad_format":"<?php echo $wg_options['Adsense_widget_ad_size']?>"}
   },
   GFC_adsense_skin);
      </script>
  <?php 
  echo $after_widget;
}
/**
 * fc_adsense_widget_control
 * renders the controls for the adsense widget
 * in the admin panel.
 * 
 * @return void
 * @author Mauro Gonzalez <gmaurol@gmail.com>
 */
function fc_adsense_widget_control() {
  $data = get_option("GFC Adsense");
  $opts = array('728x90','468x60','300x250','160x600','120x600',
   '336x280','250x250','234x60','180x150','200x200','125x125',
   '120x240');
  ?>
  <p><label for="Adsense_widget_title">Title: <input
    type="text" name="Adsense_widget_title" id="Adsense_widget_title"
    value="<?php echo (isset($data['Adsense_widget_title']) ? $data['Adsense_widget_title'] : '' )?>"
     /> </label></p>
  <p><label for="Adsense_widget_ad_client">Google Ad Client: <input
    type="text" name="Adsense_widget_ad_client" id="Adsense_widget_ad_client"
    value="<?php echo (isset($data['Adsense_widget_ad_client']) ? $data['Adsense_widget_ad_client'] : '' )?>"
     /> </label></p>
  <p><label for="Adsense_widget_ad_host">Google Ad Host: <input
    type="text" name="Adsense_widget_ad_host" id="Adsense_widget_ad_host"
    value="<?php echo (isset($data['Adsense_widget_ad_host']) ? $data['Adsense_widget_ad_host'] : '' )?>"
     /> </label></p>
  <p><label for="Adsense_widget_ad_size">Ad Size: 
    <select name="Adsense_widget_ad_size">
      <?php 
        foreach($opts as $opt) {
          echo '<option value="'.$opt.'"';
          if($data['Adsense_widget_ad_size'] == $opt) {
            echo ' selected="selected" ';
          }
          echo '>'.$opt.'</option>'; 
        }
      ?>
    </select>
  </label></p>
  <?php
  if(isset($_POST['Adsense_widget_ad_size'])) {
    $data['Adsense_widget_title'] = attribute_escape($_POST['Adsense_widget_title']); 
    $data['Adsense_widget_ad_host'] = attribute_escape($_POST['Adsense_widget_ad_host']);
    $data['Adsense_widget_ad_client'] = attribute_escape($_POST['Adsense_widget_ad_client']);
    $data['Adsense_widget_ad_size'] = attribute_escape($_POST['Adsense_widget_ad_size']);  
    update_option('GFC Adsense', $data);
  } 
}
/**
 * initializes the fc social bar.
 * @return unknown_type
 */
function init_fc_socialbar() {

  $options = get_option("FriendConnect");
  if($options['enablesocialbar']) {
    ?>
    <!-- gfc social bar -->
    <div id="div-2503554072911684285"></div>
    <!-- Render the gadget into a div. -->
    <script type="text/javascript">
    	var sb_skin = <?php echo render_skin();?>;	
        sb_skin['POSITION'] = "<?php echo $options['socialbarpos']==SOCIALBAR_POS_TOP ? 'top':'bottom'?>";
        google.friendconnect.container.renderSocialBar(
         { id: 'div-2503554072911684285',
           site: '<?php echo $options['gfcid']?>',
           'view-params':
           {"scope":"SITE","features":"video,comment","showWall":"true"}
         },
         sb_skin);
    </script>
    <?php
  }
}
/*
 * Admin User Interface
 */

if ( is_admin() && ! class_exists( 'GoogleFriendConnect_Admin' ) ) {

  class GoogleFriendConnect_Admin {

    function add_config_page() {
      global $wpdb;
      if ( function_exists('add_submenu_page') ) {
        add_options_page('FriendConnect Configuration', 'FriendConnect', 9, basename(__FILE__), array('GoogleFriendConnect_Admin','config_page'));
        add_filter( 'plugin_action_links', array( 'GoogleFriendConnect_Admin', 'filter_plugin_actions'), 10, 2 );
        add_filter( 'ozh_adminmenu_icon', array( 'GoogleFriendConnect_Admin', 'add_ozh_adminmenu_icon' ) );
      }
    }

    function add_ozh_adminmenu_icon( $hook ) {
      global $gfcpluginpath;
      static $fcicon;
      if (!$fcicon) {
        $fcicon = $gfcpluginpath . 'google.png';
      }
      if ($hook == 'fc_plugin.php') return $fcicon;
      return $hook;
    }

    function filter_plugin_actions( $links, $file ){
      //Static so we don't call plugin_basename on every plugin row.
      static $this_plugin;
      if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);

      if ( $file == $this_plugin ){
        $settings_link = '<a href="options-general.php?page=gfc_plugin.php">' . __('Settings') . '</a>';
        array_unshift( $links, $settings_link ); // before other links
      }
      return $links;
    }

    function config_page() {
      global $gfcpluginpath;
      if (!current_user_can('manage_options')) die(__('You cannot edit the Google FriendConnect options.'));

      $defoptions = array();
      $defoptions['gfcid'] = '';
      $defoptions['gfcurl'] = get_option('siteurl').'/';
      $defoptions['usegfccss'] = true;
      $defoptions['pagestoo'] = true;
      $defoptions['overridecomments'] = false;
      $defoptions['commentsheadertxt'] = 'Comments';
      $defoptions['commentsdflttxt'] = 'Enter your comment here';
      $defoptions['commentsppage'] = 5;
      $defoptions['commentswidgetwidth'] = 432;
      $defoptions['enablesocialbar'] = false;
      $defoptions['socialbarpos']=0;
      $defoptions['allowanonymouscomments']=false;
      //
      $defoptions['FONT_FAMILY'] = 'default';
      $defoptions['BORDER_COLOR'] = '';
      $defoptions['ENDCAP_BG_COLOR'] = '';
      $defoptions['ENDCAP_TEXT_COLOR'] = '333333';
      $defoptions['ENDCAP_LINK_COLOR'] = '0000cc';
      $defoptions['ALTERNATE_BG_COLOR'] = '';
      $defoptions['CONTENT_BG_COLOR'] = '';
      $defoptions['CONTENT_LINK_COLOR'] = '0000cc';
      $defoptions['CONTENT_TEXT_COLOR'] = '333333';
      $defoptions['CONTENT_SECONDARY_LINK_COLOR'] = '7777cc';
      $defoptions['CONTENT_SECONDARY_TEXT_COLOR'] = '666666';
      $defoptions['CONTENT_HEADLINE_COLOR'] = '333333';
      //
      $options = get_option("FriendConnect");
      if (!is_array($options)) {
        $options = $defoptions;
        add_option("FriendConnect",$options);
      }

      if ( isset($_POST['submit']) ) {
        check_admin_referer('FriendConnect-config');
        // boolean Settings
        foreach (array('overridecomments','enablesocialbar','allowanonymouscomments','allowvideocomments','enablerecommendations') as $option_name) {
          if (isset($_POST[$option_name])) {
            $options[$option_name] = true;
          } else {
            $options[$option_name] = false;
          }
        }
        $str_settings = array(
        	'gfcid', 
        	'gfcurl',
        	'commentsheadertxt',
        	'commentsdflttxt',
            'FONT_FAMILY',
            'BORDER_COLOR',
            'ENDCAP_BG_COLOR',
            'ENDCAP_TEXT_COLOR',
            'ENDCAP_LINK_COLOR',
            'ALTERNATE_BG_COLOR',
            'CONTENT_BG_COLOR',
            'CONTENT_LINK_COLOR',
            'CONTENT_TEXT_COLOR',
            'CONTENT_SECONDARY_LINK_COLOR',
            'CONTENT_SECONDARY_TEXT_COLOR',
            'CONTENT_HEADLINE_COLOR'
            );
            // string Settings
            foreach ($str_settings as $option_name) {
              if (isset($_POST[$option_name]) && $_POST[$option_name] != "") {
                $options[$option_name] = trim($_POST[$option_name]);
              } else {
                $options[$option_name] = $defoptions[$option_name];
              }
            }
            // integer Settings
            foreach(array('commentswidgetwidth','commentsppage','socialbarpos')as $option_name) {
              if (isset($_POST[$option_name]) && $_POST[$option_name] != "") {
                $options[$option_name] = (int) $_POST[$option_name];
              } else {
                $options[$option_name] = (int) $defoptions[$option_name];
              }
            }

            update_option("FriendConnect",$options);

            if ($options['gfcid'] != ""){
              echo "<div id=\"message\" class=\"updated\"><p>FriendConnect settings updated</p></div>\n";
            }
      }

      if ($options['gfcid'] == ""){
        echo "<div id=\"message\" class=\"error\"><p>Enter your FriendConnect ID to allow FriendConnect login to work.</p></div>\n";
      }
       
      ?>
<div class="wrap">
<style>
#FriendConnect-conf label {
	float: left;
	width: 300px;
}

.fc-palette-swatch {
	border: 1px solid black;
	cursor: pointer;
	display: block;
	height: 20px;
	width: 20px;
}
</style>
<script type="text/javascript"
	src="<?php echo $gfcpluginpath?>/jscolor/jscolor.js"></script>
<h2>FriendConnect Configuration</h2>
<form action="" method="post" id="FriendConnect-conf">
<table class="form-table" style="width: 600px;">
	<tbody>
	<?php
	if ( function_exists('wp_nonce_field') )
	wp_nonce_field('FriendConnect-config');
	?>
		<tr>
			<th><label for="gfcid">Google FriendConnect ID:</label></th>
			<td valign="top"><input style="width: 300px;" type="text"
				name="gfcid"
				value="<?php if (isset($options['gfcid'])) { echo $options['gfcid']; } ?>"
				id="gfcid" /><br />
			<p><small>If you don't have a FriendConnect ID, you can set up your
			site with FriendConnect, in the process of which you'll get the ID,
			by going <a href="http://www.google.com/friendconnect/">here</a>. </small></p>
			</td>
		</tr>
		<tr>
			<th><label for="gfcurl">Google FriendConnect URL:</label></th>
			<td valign="top"><input style="width: 300px;" type="text"
				name="gfcurl"
				value="<?php if (isset($options['gfcurl'])) { echo $options['gfcurl']; } else { echo get_option('siteurl')."/"; }?>"
				id="gfcurl" /><br />
			<p><small>Usually you won't have to change this, this is the URL you
			used to setup your site with FriendConnect.</small></p>
			</td>
		</tr>
		<tr>
			<th valign="top"><label for="enablerecommendations">Enable FriendConnect
			Recommendations:</label></th>
			<th valign="top"><input type="checkbox" name="enablerecommendations"
				id="enablerecommendations"
	<?php if ($options['enablerecommendations']) { echo 'checked="checked"'; } ?> /><br/>
			<small>You need to add the recommendations widget to display the recommended posts.</small>
			</th>
		</tr>
		<tr>
			<th valign="top"><label for="overridecomments">Enable FriendConnect
			Comments:</label></th>
			<th valign="top"><input type="checkbox" name="overridecomments"
				id="overridecomments"
				onclick="document.getElementById('FriendConnect-comments_settings').style.display = this.checked ? 'block':'none';"
	<?php if ($options['overridecomments']) { echo 'checked="checked"'; } ?> />
			</th>
		</tr>
		<tr>
			<td colspan="2">
			<table id="FriendConnect-comments_settings"
			<?php echo $options['overridecomments'] ? 'style="display:block"' : 'style="display:none"' ?>>
				<tbody>
					<tr>
						<th><label for="commentsheadertxt">Comments Header Text</label></th>
						<td valign="top"><input style="width: 300px;" type="text"
							name="commentsheadertxt"
							value="<?php if (isset($options['commentsheadertxt'])) { echo $options['commentsheadertxt']; }?>"
							id="commentsheadertxt" /><br />
						</td>
					</tr>
					<tr>
						<th><label for="commentsdflttxt">Comments box</label></th>
						<td valign="top"><input style="width: 300px;" type="text"
							name="commentsdflttxt"
							value="<?php if (isset($options['commentsdflttxt'])) { echo $options['commentsdflttxt']; } else { echo 'Enter your comment here'; }?>"
							id="commentsdflttxt" /><br />
						<p><small>Default text to be displayed at FriendConnect comments
						box.</small></p>
						</td>
					</tr>
					<tr>
						<th valign="top"><label for="allowanonymouscomments">Allow
						anonymous comments:</label></th>
						<td valign="top"><input type="checkbox"
							name="allowanonymouscomments" id="allowanonymouscomments"
							<?php if ($options['allowanonymouscomments']) { echo 'checked="checked"'; } ?> />
						</td>
					</tr>

					<tr>
						<th valign="top"><label for="allowvideocomments">Allow video on
						comments:</label></th>
						<td valign="top"><input type="checkbox" name="allowvideocomments"
							id="allowvideocomments"
							<?php if ($options['allowvideocomments']) { echo 'checked="checked"'; } ?> />
						</td>
					</tr>
					<tr>
						<th><label for="commentsppage">Comments per page</label></th>
						<td valign="top"><input style="width: 30px;" type="text"
							name="commentsppage"
							value="<?php if (isset($options['commentsppage'])) { echo $options['commentsppage']; } ?>"
							id="commentsppage" /><br />
						</td>
					</tr>
					<tr>
						<th><label for="commentswidgetwidth">Comments gadget width</label></th>
						<td valign="top"><input style="width: 40px;" type="text"
							name="commentswidgetwidth"
							value="<?php if (isset($options['commentswidgetwidth'])) { echo $options['commentswidgetwidth']; } ?>"
							id="commentswidgetwidth" />px<br />
						<p><small>FriendConnect comments gadget width (numbers only)</small></p>
						</td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
		<tr>
			<th><label for="enablesocialbar">Enable FriendConnect
			Social Bar:</label></th>
			<th valign="top"><input type="checkbox" name="enablesocialbar"
				id="enablesocialbar"
				onclick="document.getElementById('FriendConnect-socialbar_settings').style.display = this.checked ? 'block':'none';"
							<?php if ($options['enablesocialbar']) { echo 'checked="checked"'; } ?> />
			</th>
		</tr>
		<tr>
			<td colspan="2">
			<table id="FriendConnect-socialbar_settings"
			<?php echo $options['enablesocialbar'] ? 'style="display:block"' : 'style="display:none"' ?>>
				<tbody>
					<tr>
						<td valign="top"><label>Position:</label></td>
						<td valign="top">
						<label for="socialbarpos_top"> 
						<input type="radio"
							name="socialbarpos" 
							<?php if ($options['socialbarpos']==SOCIALBAR_POS_TOP) { echo 'checked="checked"'; } ?>
							value="<?php echo SOCIALBAR_POS_TOP?>" id="socialbarpos_top" />
						 Top</label><br />
						<label for="socialbarpos_bottom"><input type="radio" name="socialbarpos"
						<?php if ($options['socialbarpos']==SOCIALBAR_POS_BOTTOM) { echo 'checked="checked"'; } ?>
							value="<?php echo SOCIALBAR_POS_BOTTOM?>" id="socialbarpos_bottom" /> Bottom</label>

						</td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
		<tr>
			<td colspan="2">
			<table class="fc-palette-table">
				<tbody>
					<tr>
						<td style="text-align: right; font-weight: bold;">Endcap
						background #</td>
						<?php ?>
						<td><input class="color {required:false}" type="text" size="7"
							value="<?php echo $options['ENDCAP_BG_COLOR']?>"
							name="ENDCAP_BG_COLOR" id="ENDCAP_BG_COLOR" /> Empty for transparent.</td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Endcap text #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['ENDCAP_TEXT_COLOR']?>"
							name="ENDCAP_TEXT_COLOR" /></td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Endcap links #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['ENDCAP_LINK_COLOR']?>"
							name="ENDCAP_LINK_COLOR" /></td>
					</tr>
					<tr>
						<td colspan="2">
						<div style="height: 4px; line-height: 4px;" />
						
						</td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Border #</td>
						<td><input class="color {required:false}" type="text" size="7"
							value="<?php echo $options['BORDER_COLOR']?>" name="BORDER_COLOR" />  Empty for transparent.</td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Content
						headlines #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['CONTENT_HEADLINE_COLOR']?>"
							name="CONTENT_HEADLINE_COLOR" /></td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Content
						background* #</td>
						<td><input class="color {required:false}" type="text" size="7"
							value="<?php echo $options['CONTENT_BG_COLOR']?>"
							name="CONTENT_BG_COLOR" /> Empty for transparent.</td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Alternate
						background* #</td>
						<td><input class="color {required:false}" type="text" size="7"
							value="<?php echo $options['ALTERNATE_BG_COLOR']?>"
							name="ALTERNATE_BG_COLOR" /> Empty for transparent.</td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Content text #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['CONTENT_TEXT_COLOR']?>"
							name="CONTENT_TEXT_COLOR" /></td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Content
						secondary text #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['CONTENT_SECONDARY_TEXT_COLOR']?>"
							name="CONTENT_SECONDARY_TEXT_COLOR" /></td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Content links #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['CONTENT_LINK_COLOR']?>"
							name="CONTENT_LINK_COLOR" /></td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Content
						secondary links #</td>
						<td><input class="color" type="text" size="7"
							value="<?php echo $options['CONTENT_SECONDARY_LINK_COLOR']?>"
							name="CONTENT_SECONDARY_LINK_COLOR" /></td>
					</tr>
					<tr>
						<td colspan="2">
						<div style="height: 4px; line-height: 4px;" />
						
						</td>
					</tr>
					<tr>
						<td style="text-align: right; font-weight: bold;">Font:</td>
						<td>
						<select name="FONT_FAMILY">
							<option value="default"
							<?php if($options['FONT_FAMILY']=='default') { echo ' selected="selected"';}?>>Arial</option>
							<option value="sans-serif"
							<?php if($options['FONT_FAMILY']=='sans-serif') { echo ' selected="selected"';}?>>Sans
							Serif</option>
							<option value="serif"
							<?php if($options['FONT_FAMILY']=='serif') { echo ' selected="selected"';}?>>Serif</option>
							<option value="monospace"
							<?php if($options['FONT_FAMILY']=='monospace') { echo ' selected="selected"';}?>>Monospace</option>
							<option value="arial black,sans-serif"
							<?php if($options['FONT_FAMILY']=='arial black,sans-serif') { echo ' selected="selected"';}?>>Wide</option>
							<option value="arial narrow,sans-serif"
							<?php if($options['FONT_FAMILY']=='arial narrow,sans-serif') { echo ' selected="selected"';}?>>Narrow</option>
							<option value="comic sans ms,sans-serif"
							<?php if($options['FONT_FAMILY']=='comic sans ms,sans-serif') { echo ' selected="selected"';}?>>Comic
							Sans MS</option>
							<option value="courier new,monospace"
							<?php if($options['FONT_FAMILY']=='courier new,monospace') { echo ' selected="selected"';}?>>Courier
							New</option>
							<option value="garamond,serif"
							<?php if($options['FONT_FAMILY']=='aramond,serif') { echo ' selected="selected"';}?>>Garamond</option>
							<option value="georgia,serif"
							<?php if($options['FONT_FAMILY']=='georgia,serif') { echo ' selected="selected"';}?>>Georgia</option>
							<option value="tahoma,sans-serif"
							<?php if($options['FONT_FAMILY']=='tahoma,sans-serif') { echo ' selected="selected"';}?>>Tahoma</option>
							<option value="trebuchet ms,sans-serif"
							<?php if($options['FONT_FAMILY']=='trebuchet ms,sans-serif') { echo ' selected="selected"';}?>>Trebuchet
							MS</option>
							<option value="verdana,sans-serif"
							<?php if($options['FONT_FAMILY']=='verdana,sans-serif') { echo ' selected="selected"';}?>>Verdana</option>
						</select></td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
	</tbody>
</table>
<p style="border: 0;" class="submit"><input type="submit" name="submit"
	value="Save configuration" /></p>
</form>
</div>
							<?php
    } // end config_page()
  } // end class GoogleFriendConnect_Admin
  // adds the menu item to the admin interface
  add_action('admin_menu', array('GoogleFriendConnect_Admin','add_config_page'));
}
?>
