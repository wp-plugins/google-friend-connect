<?php
// include the recommendation button below the post 
require_once realpath(dirname(__FILE__))."/recommendation_button.php";
?>
<br style="clear: both;"/>
<?php
// render GFC comments gadget associated to the post
if (!empty($_SERVER['SCRIPT_FILENAME']) && 'fc_comments_template_wrapper.php' == basename($_SERVER['SCRIPT_FILENAME']))
die ('Please do not load this page directly. Thanks!');

  $options = get_option("FriendConnect");
  ?>
<?php if ( comments_open() ) : ?>  
<div id="div-8162253135099409065" style="width:<?php $options['commentswidgetwidth']?>px;"></div>
<script type="text/javascript">
	   var pcomments_skin = <?php echo render_skin();?>;	
	   pcomments_skin['DEFAULT_COMMENT_TEXT'] = '<?php echo $options['commentsdflttxt']?>';
	   pcomments_skin['HEADER_TEXT'] = '<?php echo preg_replace('/\[post_title\]/',$post->post_title,$options['commentsheadertxt'])?>';
	   pcomments_skin['POSTS_PER_PAGE'] = '<?php echo $options['commentsppage']?>';
       
       google.friendconnect.container.renderWallGadget(
        { id: 'div-8162253135099409065',
          site: "<?php echo $options['gfcid']; ?>",
         'view-params':{
            "disableMinMax":"false",
            "scope":"ID",
            "features":"<?php echo $options['allowvideocomments']? 'video,':"" ?>comment",
            "docId":"8162253135099409065_<?php echo $post->ID ?>",
            "startMaximized":"true"
            <?php echo $options['allowanonymouscomments']? ',"allowAnonymousPost":"true"':"" ?> 
             
            }
        }, pcomments_skin);
    </script>
<?php else: ?>
<!-- If comments are closed. -->
		<br/><p class="nocomments">Comments are closed.</p>
<?php endif;?>
<!-- login button  -->
<br style="clear: both;"/>
<div id="div-175458888026420902" style="display:block;"></div>
<script type="text/javascript">
var si_skin = <?php echo render_skin();?>;	
si_skin['ALIGNMENT'] = 'left';
google.friendconnect.container.renderSignInGadget(
 { id: 'div-175458888026420902',
   site: '<?php echo $options['gfcid']; ?>' },
  si_skin);
</script>
<!-- /login button  -->