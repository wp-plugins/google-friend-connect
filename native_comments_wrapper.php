<?php 
// include the recommendation button below the post 
require_once realpath(dirname(__FILE__))."/recommendation_button.php";
?>
<?php
// include the regular comments template
if ( file_exists( TEMPLATEPATH . $file ) ) {
  require( TEMPLATEPATH .  $file );
} else {
  require( get_theme_root() . '/default/comments.php');
}
?>
<!-- login button  -->
<div id="div-175458888026420902" style="display:block;"></div>
<br />
<script type="text/javascript">
  var FC_ELEMENT_ID = 'div-175458888026420902';	
  GFC.fixUrls();
  google.friendconnect.container.initOpenSocialApi({site: SITE_ID,onload: function(securityToken) { GFC.init(securityToken); }});
</script>
<!-- /login button  -->
