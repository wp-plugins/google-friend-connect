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
// render the "recommend it" button
$options = get_option("FriendConnect");
$rec_options = get_option("GFC Recommendations");
?>
<?php if($options['enablerecommendations']):?>
<div id="div-6549465092499238338-<?php echo $post->ID?>" style="width:100%;"></div>

<script type="text/javascript">
var recbtn_skin =<?php echo render_skin();?>;	
recbtn_skin['HEIGHT'] = '21';
recbtn_skin['BUTTON_STYLE'] = 'compact';
recbtn_skin['BUTTON_TEXT'] = '<?php echo $rec_options['FriendConnectRecommendations_btntxt']?>';
recbtn_skin['BUTTON_ICON'] = 'default';
google.friendconnect.container.renderOpenSocialGadget(
 { id: 'div-6549465092499238338-<?php echo $post->ID?>',
   url:'http://www.google.com/friendconnect/gadgets/recommended_pages.xml',
   height: 21,
   site: '<?php echo $options['gfcid']?>',
   'view-params':{"pageUrl":"<?php echo $post->guid?>","pageTitle":"<?php echo $post->post_title?>","docId":"recommendedPages"}
 },
 recbtn_skin);
</script>
<?php endif;?>