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

/* a new gallery's auto-draft has its ID already and keeps it on the first save. The copy is
   asset/metabox/gallery-tools.js: a click on the field or the button */
$shortcode = '[robo-gallery id="' . (int) $galleryId . '"]';
?>
<div class="robo-gallery-tool">
	<label class="robo-gallery-tool-title" for="robo-gallery-shortcode"><?php esc_html_e('Shortcode', 'robo-gallery'); ?></label>
	<div class="robo-gallery-copy"
	     data-copied="<?php esc_attr_e('Copied to the clipboard', 'robo-gallery'); ?>"
	     data-select-hint="<?php esc_attr_e('Press Ctrl+C to copy', 'robo-gallery'); ?>">
		<input type="text" id="robo-gallery-shortcode" class="robo-gallery-copy-text" value="<?php echo esc_attr($shortcode); ?>" readonly="readonly" />
		<button type="button" class="robo-gallery-copy-button" aria-label="<?php esc_attr_e('Copy shortcode', 'robo-gallery'); ?>" title="<?php esc_attr_e('Copy shortcode', 'robo-gallery'); ?>">
			<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
		</button>
	</div>
	<p class="robo-gallery-copy-status" role="status" aria-live="polite"></p>
	<p class="robo-gallery-tool-description"><?php esc_html_e('Paste it into a page, post or widget to show the gallery there.', 'robo-gallery'); ?></p>
</div>
