<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('WPINC') || exit;

/*
 * "Current Gallery Type" box of the gallery editor (config/metabox/gallery_type.php):
 * the type's logo and name, and its source - or, for Fusion Grid, a description.
 * A stored type without an entry here (old sites keep such values) is shown as grid.
 */
$typeBoxes = array(
	'grid'         => array('grid_active.svg', __("Gallery Grid", 'robo-gallery')),
	'gridpro'      => array('grid_pro_active.svg', __("Gallery Grid Pro", 'robo-gallery')),
	'masonry'      => array('masonry_active.svg', __("Gallery Masonry", 'robo-gallery')),
	'masonrypro'   => array('masonry_pro_active.svg', __("Gallery Masonry Pro", 'robo-gallery')),
	'mosaic'       => array('mosaic_active.svg', __("Gallery Mosaic", 'robo-gallery')),
	'mosaicpro'    => array('mosaic_pro_active.svg', __("Gallery Mosaic Pro", 'robo-gallery')),
	'polaroid'     => array('polaroid_active.svg', __("Gallery Polaroid", 'robo-gallery')),
	'polaroidpro'  => array('polaroid_pro_active.svg', __("Gallery Polaroid Pro", 'robo-gallery')),
	'wallstylepro' => array('wallstyle_pro_active.svg', __("Gallery WallStyle Pro", 'robo-gallery')),
	'youtube'      => array('youtube_active.svg', __("Gallery Youtube", 'robo-gallery')),
	'youtubepro'   => array('youtube_pro_active.svg', __("Gallery Youtube Pro", 'robo-gallery')),
	'slider'       => array('slider_active.svg', __("Slider", 'robo-gallery')),
	'custom'       => array('masonry_active.svg', __("Gallery Custom", 'robo-gallery')),
	'robogrid'     => array('robogrid_active.svg', __("Gallery Fusion Grid", 'robo-gallery')),
);

$typeBoxType = rbsGalleryUtils::getTypeGallery();
if (!isset($typeBoxes[$typeBoxType])) {
	$typeBoxType = 'grid';
}
list($typeBoxLogo, $typeBoxTitle) = $typeBoxes[$typeBoxType];
$typeBoxIsFusion = 'robogrid' === $typeBoxType;
?>
<div id="roboGalleryThemeTypeDiv">
	<img class="type-logo" src="<?php echo esc_url(ROBO_GALLERY_URL . 'app/extensions/galleryType/build/grids/' . $typeBoxLogo); ?>" style="width: 100px; height: 100px;<?php echo $typeBoxIsFusion ? ' margin: 20px 20px 20px 0; padding: 0;' : ''; ?>" />
	<h4><?php echo esc_html($typeBoxTitle); ?></h4>
	<p>
	<?php if ($typeBoxIsFusion) : ?>
	 <?php esc_html_e("
	 A next-generation gallery experience! Fusion Grid brings dynamic layouts,
	 smooth animations, and ultimate flexibility to showcase your images like never before.
	 Create stunning albums with interactive hovers, seamless mobility,
	 and innovative designs - all in one powerful gallery.", 'robo-gallery'); ?>
	<?php else : ?>
		<?php esc_html_e("Type", 'robo-gallery'); ?>: <?php echo esc_html(rbsGalleryUtils::getFullSourceGallery()); ?>
	<?php endif; ?>
	</p>
</div>
