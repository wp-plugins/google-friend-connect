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
