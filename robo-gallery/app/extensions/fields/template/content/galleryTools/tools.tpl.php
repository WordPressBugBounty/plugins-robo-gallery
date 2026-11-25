<?php
/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('WPINC') || exit;

/* the tools of the "Gallery Tools" box (config/metabox/gallery_tools.php), in this order: a new tool is a template
   in tool/ and its name here. The box is printed on the edit screen, where the global post is the gallery
   (a new one's auto-draft too) */
$galleryTools = array('shortcode', 'watermark');
$galleryId    = (int) get_the_ID();
$toolView     = new roboGalleryFieldsView();
?>
<div class="robo-gallery-tools">
	<?php foreach ($galleryTools as $galleryTool) : ?>
		<?php $toolView->render('content/galleryTools/tool/' . $galleryTool, array('galleryId' => $galleryId)); ?>
	<?php endforeach; ?>
</div>
